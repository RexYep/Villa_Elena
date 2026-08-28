<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundDestination;
use App\Models\StaffLog;
use App\Services\PayMongoService;
use App\Services\RefundTransferService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\NotificationHelper;
use App\Events\PaymentReceived;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    // ── Payments List ──────────────────────────────────────────────
    public function index(Request $request)
    {
        // Kasama ang `refundDestination` para hindi maging isang query
        // kada row ang `needsRefundDestination()` sa talahanayan.
        $query = Payment::with(['booking.user', 'booking.property', 'refundDestination', 'refundTransfers'])
            // Tingnan ang Customer\PaymentController — petsa lang ang
            // `payment_date`, kaya kailangan ng `id` na tiebreaker para
            // hindi mag-random ang pagkakasunod ng mga bayad sa iisang
            // araw.
            ->latest('payment_date')
            ->latest('id');

        // Filters
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('type')) {
            $query->where('payment_type', $request->type);
        }
        if ($request->get('status') === 'awaiting_payout') {
            $query->awaitingPayout();
        }
        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('booking', function ($q) use ($search) {
                $q->where('booking_ref', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('full_name', 'like', "%{$search}%"));
            });
        }

        $payments = $query->paginate(15)->withQueryString();

        // Summary stats
        $totalRevenue   = Payment::where('payment_type', '!=', 'refund')->sum('amount');
        $todayRevenue   = Payment::where('payment_type', '!=', 'refund')
            ->whereDate('payment_date', today())->sum('amount');
        $monthRevenue   = Payment::where('payment_type', '!=', 'refund')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date',  now()->year)->sum('amount');
        $totalRefunds   = Payment::where('payment_type', 'refund')->sum('amount');
        $pendingBalance = Booking::whereNotIn('status', ['cancelled', 'checked_out'])
            ->sum('balance_due');

        // Mga refund na inaprubahan na pero hindi pa naipapadala ang pera
        // — ito ang aktwal na utang ng resort sa mga guest ngayon.
        $pendingRefunds      = Payment::awaitingPayout()->sum('amount');
        $pendingRefundsCount = Payment::awaitingPayout()->count();

        // Sa mga hindi pa naipapadala, ilan ang HINDI PA MAIPAPADALA —
        // hindi natin alam kung saan. Iba ang kailangang gawin dito
        // (habulin ang guest) kaysa sa iba pa (ipadala na ang pera),
        // kaya hiwalay itong binibilang.
        $refundsNeedingDetails = Payment::awaitingPayout()
            ->where('payment_method', '!=', 'cash')
            ->whereDoesntHave('refundDestination')
            ->count();

        return view('admin.payments.index', compact(
            'payments', 'totalRevenue', 'todayRevenue',
            'monthRevenue', 'totalRefunds', 'pendingBalance',
            'pendingRefunds', 'pendingRefundsCount', 'refundsNeedingDetails'
        ));
    }

    // ── Payment Detail ─────────────────────────────────────────────
    public function show(Payment $payment)
    {
        $payment->load([
            'booking.user', 'booking.property', 'booking.payments',
            'refundDestination.providedBy',
            'refundTransfers.initiatedBy',
        ]);

        // Kung may transfer na naiwang `pending` (natapos ang paghihintay
        // bago pa mag-settle), ito ang tamang sandali para tanungin muli
        // ang PayMongo — bumubukas ang admin ng pahina para malaman ang
        // nangyari, at doon niya makikita ang sagot.
        if ($payment->hasTransferInFlight()) {
            $service = app(RefundTransferService::class);

            foreach ($payment->refundTransfers->where('status', 'pending') as $inFlight) {
                $service->syncStatus($inFlight);
            }

            $payment->refresh()->load(['refundTransfers.initiatedBy', 'refundDestination.providedBy']);
        }

        // Kinukuha lang ang listahan ng institusyon kapag talagang
        // maipapakita ang form — isa itong tawag sa labas, at walang
        // dahilan para gawin ito sa bawat pagtingin sa isang ordinaryong
        // payment.
        $institutions = ($payment->isAwaitingPayout() && $payment->payment_method !== 'cash')
            ? app(PayMongoService::class)->receivingInstitutions()
            : [];

        return view('admin.payments.show', compact('payment', 'institutions'));
    }

    // ── Refund Destination (ipinapasok ng admin para sa guest) ─────
    /**
     * Karaniwan ang guest mismo ang pumupuno nito, pero madalas naman
     * itong nakukuha sa text o sa tawag — kaya kailangang may paraan
     * ang admin na maipasok ito para sa kanya. Kung hindi, ang isang
     * guest na hindi na bumabalik sa site ay hindi na kailanman
     * makakatanggap ng refund niya.
     *
     * Pinagsasaluhan ang mga tuntunin sa guest-facing na form
     * (`RefundDestination::rules()`) para hindi maging mas maluwag ang
     * isang pinto kaysa sa isa.
     */
    public function setRefundDestination(Request $request, Payment $payment)
    {
        if (! $payment->isAwaitingPayout()) {
            return back()->with('error', 'Only a refund that has not been sent yet can have its destination changed.');
        }

        // Pareho ang dahilan sa guest-facing na form: kapag naglilinaw
        // na ang isang transfer, nakatakda na kung saan pupunta ang
        // pera. Ang pagpapalit ng talaan ngayon ay magsisinungaling
        // lang tungkol sa kung saan ito aktwal na napunta.
        if ($payment->load('refundTransfers')->hasTransferInFlight()) {
            return back()->with('error',
                'A transfer to the current account is still clearing — the destination is locked until it '
                . 'settles. If it fails, you can change these details and send again.');
        }

        if ($payment->payment_method === 'cash') {
            return back()->with('error', 'A cash refund is handed over at the front desk — it has no bank destination.');
        }

        $institutions = app(PayMongoService::class)->receivingInstitutions();
        $validBics    = array_column($institutions, 'bic');

        if (empty($validBics)) {
            return back()->with('error', 'Could not load the list of banks and e-wallets from PayMongo. Please try again shortly.');
        }

        $validated = $request->validate(
            RefundDestination::rules($validBics),
            RefundDestination::messages()
        );

        $institution = collect($institutions)->firstWhere('bic', $validated['institution_bic']);

        if ($error = RefundDestination::mobileNumberError($institution['bic'], $institution['name'], $validated['account_number'])) {
            return back()->withErrors(['account_number' => $error])->withInput();
        }

        RefundDestination::updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'institution_name' => $institution['name'],
                'institution_bic'  => $validated['institution_bic'],
                'account_number'   => $validated['account_number'],
                'account_name'     => trim($validated['account_name']),
                'provided_by'      => Auth::id(),
                'provided_at'      => now(),
            ]
        );

        // Walang account number o pangalan sa log — financial account
        // data ito. Ang layunin ng talaan ay ipakitang may naganap at
        // kung sino ang may gawa, hindi ulitin ang laman.
        StaffLog::record('refund_destination_set', 'payments', $payment->id,
            'Refund destination entered by staff on behalf of the guest for booking '
            . ($payment->booking->booking_ref ?? '#' . $payment->booking_id)
            . " ({$institution['name']})");

        return back()->with('success', '✅ Refund destination saved. This refund is now ready to send.');
    }

    // ── Record Manual Payment ──────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'booking_id'     => 'required|exists:bookings,id',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:qrph,cash',
            'payment_type'   => 'required|in:full_payment,partial,balance',
            'payment_date'   => 'required|date',
            'notes'          => 'nullable|string|max:300',
        ]);

        $booking = Booking::findOrFail($request->booking_id);

    $payment = Payment::create([
    'booking_id'     => $booking->id,
    'amount'         => $request->amount,
    'payment_method' => $request->payment_method,
    'payment_type'   => $request->payment_type,
    'status'         => 'success',
    'payment_date'   => $request->payment_date,
    'received_by'    => Auth::id(),
    'notes'          => $request->notes,
]);
        // Realtime broadcast lang ito — hindi dapat maka-block sa
        // pag-record ng payment (naka-commit na ito sa puntong ito) kung
        // mag-fail ang Pusher.
        try {
            event(new PaymentReceived($payment));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast PaymentReceived (manual record): ' . $e->getMessage());
        }

        // Recalculate booking
        $this->recalculateBooking($booking);
        // Tingnan ang Booking::confirmOnFirstPayment() — kung hindi ito
        // tatawagin, kakanselahin ng stale pending sweeper ang booking
        // na may hawak nang pera ng guest.
        $booking->confirmOnFirstPayment();

        // Pagkatapos ng recompute — kung hindi, ang "Balance due" sa
        // notification ay ang balanse BAGO ang bayad na ito.
        NotificationHelper::paymentRecorded($booking->fresh(), $request->amount, $request->payment_method);

        StaffLog::record('payment_recorded', 'bookings', $booking->id,
            "Manual payment ₱{$request->amount} recorded for {$booking->booking_ref}");

        return back()->with('success', "✅ Payment of ₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref}.");
    }

    // ── Issue Refund ───────────────────────────────────────────────
    public function refund(Request $request, Payment $payment)
    {
        $request->validate([
            'refund_amount' => 'required|numeric|min:1',
            'refund_reason' => 'required|string|min:5',
        ]);

        $booking = $payment->booking;

        // Naka-wrap sa transaction + lockForUpdate() para hindi ma-double
        // process ang refund (double-click, retry dahil sa slow network,
        // atbp.) — kinukumpara ang bagong refund laban sa AKTWAL na
        // natitirang refundable balance ng BUONG booking (total paid minus
        // total naunang na-refund), hindi lang sa orihinal na halaga ng
        // isang Payment record.
        $refundPayment = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $payment, $booking) {

            $totalPaid = Payment::where('booking_id', $booking->id)
                ->where('payment_type', '!=', 'refund')
                ->lockForUpdate()
                ->sum('amount');

            $totalRefunded = Payment::where('booking_id', $booking->id)
                ->where('payment_type', 'refund')
                ->lockForUpdate()
                ->sum('amount');

            $refundableBalance = $totalPaid - $totalRefunded;

            if ($request->refund_amount > $refundableBalance) {
                return null; // signal na lumampas sa refundable balance
            }

            $newRefund = Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $request->refund_amount,
                'payment_method' => $payment->payment_method,
                'payment_type'   => 'refund',
                // 'pending' — naitala na ang refund at nabawas na sa
                // booking, pero hindi pa naipapadala ang pera. Sa
                // Payments page ito minamarkahang "Paid Out" kapag
                // naisagawa na talaga.
                'status'         => 'pending',
                'payment_date'   => today(),
                'received_by'    => Auth::id(),
                'notes'          => "Refund: {$request->refund_reason}",
            ]);

            // Realtime broadcast lang ito — kailangan i-catch dito
            // dahil NASA LOOB TAYO NG DB::transaction() — kung hindi
            // ito ma-catch, ang isang Pusher failure ay maro-rollback
            // ang buong refund record kahit valid na siya, at
            // magmumukhang "hindi na-process" ang refund kahit
            // tama naman ang lahat.
            try {
                event(new PaymentReceived($newRefund));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to broadcast PaymentReceived (refund): ' . $e->getMessage());
            }

            NotificationHelper::refundIssued($booking, $request->refund_amount, $request->refund_reason);

            // Dating "Refund Processed" ang sinasabi nito sa guest, gayong
            // 'pending' pa lang ang refund row sa itaas — walang perang
            // lumabas. Naghahanap tuloy ang guest sa GCash niya ng bagay
            // na hindi pa naipapadala. "Approved" muna; may sunod na
            // notification kapag aktwal nang na-paid out.
            NotificationHelper::refundApprovedForGuest(
                $booking,
                (float) $request->refund_amount,
                $request->refund_reason,
                $newRefund
            );

            StaffLog::record('refund_issued', 'payments', $payment->id,
                "Refund ₱{$request->refund_amount} for booking {$booking->booking_ref}. Reason: {$request->refund_reason}");

            return $newRefund;
        });

        if (!$refundPayment) {
            return back()->with('error', 'The refund amount exceeds the remaining refundable balance for this booking. Please check the payment history first.');
        }

        // Recalculate (sa labas ng transaction, hindi problema dahil
        // consistent na ang Payment records nung nagsara na ang transaction)
        $this->recalculateBooking($booking);

        return back()->with('success', "✅ Refund of ₱" . number_format($request->refund_amount, 2)
            . " approved and recorded. Send the money to the guest, then mark it as paid out.");
    }

    // ── Mark a pending refund as actually paid out ─────────────────
    // Ang refund row ay nabubuo bilang 'pending' — inaprubahan na, pero
    // hindi pa naipapadala ang pera. Dito ito minamarkahang naibigay na,
    // pagkatapos talagang mai-send ng admin ang GCash/PayMaya o maiabot
    // ang cash. Hindi ito nagbabago ng anumang halaga — ang refund ay
    // nabilang na sa booking noon pang inaprubahan ito.
    public function markRefundPaidOut(Request $request, Payment $payment)
    {
        if (! $payment->isAwaitingPayout()) {
            return back()->with('error', 'This payment is not a refund awaiting payout.');
        }

        // HINDI mo maipapadala ang isang bagay kung hindi mo alam kung
        // saan. Kung walang naitalang destinasyon, kahit anong pinindot
        // dito ay hindi maaaring kumakatawan sa isang tunay na transfer.
        //
        // May tunay na escape hatch ito: kung nakuha ng admin ang
        // detalye sa text o tawag, mailalagay niya ito sa "Add Details"
        // bago bumalik dito. Kaya hindi ito hadlang — pagkakasunod.
        if ($payment->needsRefundDestination()) {
            return back()->with('error',
                'Add the refund destination first — there is no record of where this money should go. '
                . 'Use "Add Details" on this refund if the guest gave it to you by text or phone.');
        }

        // ANG MISMONG SAFEGUARD.
        //
        // Dating isang pindot lang ito, at nangyari na ang inaasahan:
        // napindot ang "Mark Paid Out" nang WALANG PERANG GUMAGALAW,
        // dahil nilaktawan ang out-of-band na hakbang at walang
        // humingi nito (project.md §6.11). Ang paghingi ng reference ng
        // aktwal na transfer ang gumagawa niyon halos imposible nang
        // hindi sinasadya: kung hindi mo ipinadala, wala kang
        // maipapasok.
        //
        // Ang cash refund ay walang transfer reference — inaabot ito
        // nang personal — kaya opsyonal doon ang OR/resibo.
        $isCash = $payment->payment_method === 'cash';

        $request->validate([
            'transfer_reference' => ($isCash ? 'nullable' : 'required') . '|string|min:4|max:100',
        ], [
            'transfer_reference.required' => 'Enter the reference number of the transfer you sent, so there is a record of it.',
            'transfer_reference.min'      => 'That reference looks too short — copy it exactly from your GCash/Maya/bank receipt.',
        ]);

        $payment->update([
            'status'       => 'success',
            'processed_by' => Auth::id(),
            // Dating NULL ito sa lahat ng 59 na row — bakante ang column
            // magmula nang gawin ito. Dito na ito nagkakalaman.
            'transaction_ref' => $request->transfer_reference,
        ]);

        // Dito nagtatapos ang refund lifecycle, kaya dito rin dapat
        // malaman ng guest na naipadala na ang pera niya — dati, tahimik
        // ang hakbang na ito: naaabisuhan siya nang "may refund ka"
        // noong inaprubahan pa lang, tapos wala nang kasunod kahit
        // kailan. Kailangan din ito ng admin bilang kumpirmasyon na
        // sarado na ang refund na ito.
        NotificationHelper::refundPaidOut($payment->fresh('booking'));

        // Ang reference ay ligtas na maitala — hindi ito account number
        // kundi ang ID ng transaksyon, na siya mismong bagay na
        // hahanapin mo kapag may pinagtatalunan.
        StaffLog::record('refund_paid_out', 'payments', $payment->id,
            "Refund ₱" . number_format($payment->amount, 2) . " marked as paid out for booking "
            . ($payment->booking->booking_ref ?? '#' . $payment->booking_id)
            . ($request->transfer_reference ? " (ref: {$request->transfer_reference})" : ' (cash, no reference)'));

        return back()->with('success', '✅ Refund of ₱' . number_format($payment->amount, 2) . ' marked as paid out.');
    }

    // ── Ipadala ang refund sa pamamagitan ng PayMongo ──────────────
    /**
     * Ang awtomatikong daan: ipinapadala ng sistema mismo ang pera sa
     * account na ibinigay ng guest, sa halip na ipadala ito ng admin
     * sa sariling GCash app at saka i-type ang reference.
     *
     * Hiwalay ito sa `markRefundPaidOut()` at hindi kapalit nito.
     * Nananatili ang manu-manong daan bilang fallback — para sa cash,
     * para sa mga institusyong hindi kayang abutin ng InstaPay, at
     * para sa tuwing may mali sa transfer.
     *
     * Hinihingi ang kumpirmasyon sa halaga bilang panghuling hadlang:
     * hindi na mababawi ang InstaPay kapag naipadala na, kaya
     * mabuting minsan pang tingnan ng admin ang bilang.
     */
    public function sendRefundTransfer(Request $request, Payment $payment)
    {
        if (! $payment->isAwaitingPayout()) {
            return back()->with('error', 'This payment is not a refund awaiting payout.');
        }

        if ($payment->payment_method === 'cash') {
            return back()->with('error',
                'Cash refunds are handed over in person — use "Mark Paid Out" once you have given the money.');
        }

        $payment->load(['refundDestination', 'refundTransfers']);

        if ($payment->needsRefundDestination()) {
            return back()->with('error',
                'Add the refund destination first — there is no record of where this money should go.');
        }

        if (! $payment->canSendTransfer()) {
            return back()->with('error', $payment->hasTransferInFlight()
                ? 'A transfer for this refund is already on its way. Refresh in a moment to see the result.'
                : 'This refund can no longer be sent automatically.');
        }

        $request->validate([
            'confirm_amount' => 'required|numeric',
        ], [
            'confirm_amount.required' => 'Confirm the amount before sending.',
        ]);

        // Ang halagang nakita ng admin ay dapat ang halagang ipapadala.
        // Kung nagbago ito sa pagitan ng pagbukas ng pahina at ng
        // pagpindot, mas mabuting tumigil kaysa magpadala ng ibang
        // bilang kaysa sa ipinakita.
        if (abs((float) $request->confirm_amount - (float) $payment->amount) > 0.001) {
            return back()->with('error',
                'The amount changed since this page was opened — nothing was sent. Reload and try again.');
        }

        try {
            $transfer = app(RefundTransferService::class)->send($payment, Auth::id());
        } catch (\Throwable $e) {
            // Ang mga ito ay mga hadlang bago pa gumalaw ang pera
            // (kulang na balanse, may nakaunang transfer) — ligtas
            // itong ipakita nang buo sa admin.
            return back()->with('error', $e->getMessage());
        }

        if ($transfer->isSucceeded()) {
            return back()->with('success',
                '✅ Sent ₱' . number_format((float) $payment->amount, 2) . ' to '
                . $transfer->institution_name . '. Reference: ' . $transfer->receipt_reference);
        }

        if ($transfer->hasFailed()) {
            // Dalawang magkaibang pagkabigo, dalawang magkaibang sagot.
            // Ang isang retryable na code ay nangangahulugang sinubukan
            // na ito nang paulit-ulit at panandalian lang ang sanhi —
            // walang aayusin, pindutin lang ulit. Ang iba ay tungkol
            // sa maling detalye, at ang pagsasabing "subukan mo ulit"
            // doon ay pagpapaulit ng parehong mali.
            return back()->with('error', $transfer->isRetryable()
                ? '❌ The transfer did not go through, even after retrying. ' . $transfer->failureReason()
                    . ' No fee was charged. This bank or e-wallet rejects at random, so pressing Send '
                    . 'again often works — otherwise send it by hand and record it with "Mark Paid Out".'
                : '❌ The transfer did not go through. ' . $transfer->failureReason()
                    . ' No fee was charged — fix the details and try again.');
        }

        // Ang PESONet ay batch-cleared sa mga banking day (11:00 /
        // 14:00 / 17:00), kaya oras ang tagal nito — hindi segundo.
        // Kung hindi ito sasabihin nang maaga, mukhang sirang-sira ang
        // sistema gayong tama naman ang lahat.
        if ($transfer->provider !== 'instapay') {
            return back()->with('success',
                '✅ Sent ₱' . number_format((float) $payment->amount, 2) . ' to '
                . $transfer->institution_name . ' over PESONet. It clears in batches on banking days '
                . '(11am, 2pm, 5pm), so it will arrive later today or the next banking day — not instantly. '
                . 'This page will show the result once it settles.');
        }

        return back()->with('success',
            '⏳ The transfer was accepted and is still clearing. Reopen this page in a moment to see the result.');
    }

    // ── Helper: Recalculate booking financials ─────────────────────
    private function recalculateBooking(Booking $booking): void
    {
        $booking->recalculateFinancials();
    }
}