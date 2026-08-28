<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Services\PayMongoService;
use App\Mail\BookingConfirmedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private PayMongoService $paymongo) {}

    // ── Guest initiates payment from customer portal ───────────────
    // GET /pay/{booking}
    public function showPaymentPage(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        abort_if($booking->balance_due <= 0, 400, 'No balance due for this booking.');
        abort_if(in_array($booking->status, ['cancelled', 'checked_out']), 400, 'Cannot pay for this booking.');

        $booking->load('property');

        // Deposit amount from settings — minimum 50% ngayon (dating 30%).
        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 50);
        $depositAmount = round($booking->total_amount * $depositPct / 100, 2);
        $isDepositOnly = $booking->amount_paid == 0; // first payment = deposit

        // Anti-abuse: kung 3+ na ang cancellation ng guest na ito sa
        // loob ng 30 araw, full payment na lang ang pinapayagan —
        // walang deposit option, para hindi na sila makapag-hold ng
        // slot nang mura lang tapos ica-cancel din lang pala ulit.
        $forceFullPayment = $isDepositOnly && Booking::hasExcessiveCancellations($booking->user_id);

        return view('payment.checkout', compact('booking', 'depositAmount', 'isDepositOnly', 'depositPct', 'forceFullPayment'));
    }

    // ── Create PayMongo Checkout Session ──────────────────────────
    // POST /pay/{booking}/checkout
    public function createCheckout(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        $request->validate([
            'payment_type' => 'required|in:deposit,full_payment',
        ]);

        // Anti-abuse: server-side re-check (hindi lang basta umaasa sa
        // UI) — kung naka-flag ang guest sa excessive cancellations,
        // hindi papayagang "deposit" ang piliin kahit i-bypass ang form.
        if ($request->payment_type === 'deposit'
            && $booking->amount_paid == 0
            && Booking::hasExcessiveCancellations($booking->user_id)) {
            return back()->with('error', 'Dahil sa cancellation history mo, kailangan ng full payment para sa booking na ito — hindi available ang deposit option.');
        }

        $booking->load(['property', 'user']);

        $depositPct    = (float) \App\Models\Setting::get('deposit_percentage', 50);
        $depositAmount = round($booking->total_amount * $depositPct / 100, 2);

        $amount      = $request->payment_type === 'deposit' ? $depositAmount : $booking->balance_due;
        $description = $request->payment_type === 'deposit'
            ? "Deposit ({$depositPct}%) for {$booking->property->property_name} — {$booking->booking_ref}"
            : "Full balance for {$booking->property->property_name} — {$booking->booking_ref}";

        try {
            $session = $this->paymongo->createCheckoutSession([
                'amount'           => $amount,
                'description'      => $description,
                'guest_name'       => $booking->user->full_name,
                'guest_email'      => $booking->user->email,
                'guest_phone'      => $booking->user->phone ?? '',
                'reference_number' => $booking->booking_ref,
                'booking_id'       => $booking->id,
                'payment_type'     => $request->payment_type,
                'success_url' => route('payment.success', $booking->id),
                'cancel_url'       => route('payment.cancel',  $booking->id),
            ]);

            // Store session ID in booking for verification later
            $booking->update([
                'paymongo_session_id' => $session['id'],
                'paymongo_payment_type' => $request->payment_type,
            ]);

            // Redirect to PayMongo hosted checkout page
            $checkoutUrl = $session['attributes']['checkout_url'];
            return redirect($checkoutUrl);

        } catch (\Exception $e) {
            return back()->with('error', 'Payment gateway error: ' . $e->getMessage());
        }
    }

    // ── Success Callback ───────────────────────────────────────────
    // GET /pay/{booking}/success
   public function success(Request $request, Booking $booking)
{
    abort_if($booking->user_id !== Auth::id(), 403);
    $booking->load(['property', 'user']);

    // Get session ID from booking record (stored during createCheckout)
    $sessionId = $booking->paymongo_session_id;

    if (!$sessionId) {
        // Nalinis na ang session_id ng recordPaymongoPayment(), kaya
        // naitala na ito — o wala talagang session mula sa umpisa.
        return view('payment.success', [
            'booking'          => $booking,
            'amountPaid'       => $booking->amount_paid,
            'paymentType'      => $booking->paymongo_payment_type ?? 'payment',
            'paymentConfirmed' => $booking->amount_paid > 0,
        ]);
    }

    // 'deposit' (the checkout page's request-level choice, meaning
    // "pay less than the full total") maps to the payments table's
    // 'partial' payment_type — 'deposit' isn't a valid payment_type
    // value on its own.
    $paymentType = $booking->paymongo_payment_type === 'full_payment' ? 'full_payment' : 'partial';

    try {

        $session    = $this->paymongo->getCheckoutSession($sessionId);
        $attributes = $session['attributes'];

        // Ang AKTWAL na payment object sa loob ng session ang awtoridad
        // kung bayad na — hindi ang session status.
        //
        // Dating tinatanggap ang status na `['paid','active']`, pero ang
        // 'active' ay nangangahulugang BUKAS pa ang session, hindi bayad
        // — at ang halaga ay kinukuha sa `line_items`, na siyang
        // SISINGILIN, hindi ang naibayad. Magkasama, puwedeng magtala
        // ng buong bayad para sa isang session na walang anumang
        // natanggap na pera.
        //
        // Bihira itong tumama noong GCash pa: hindi ka makakabalik dito
        // nang hindi dumadaan sa wallet authorization. Sa QR Ph, normal
        // na normal na — nakikita ng guest ang QR sa isang device at
        // ini-scan ito sa iba, kaya kayang marating ang page na ito nang
        // wala pang bayad, o habang nagse-settle pa.
        $paidPayment = collect($attributes['payments'] ?? [])
            ->first(fn ($p) => ($p['attributes']['status'] ?? null) === 'paid');

        $amountPaid = $paidPayment
            ? ($paidPayment['attributes']['amount'] ?? 0) / 100
            : 0;

        if ($paidPayment && $amountPaid > 0) {
            $this->recordPaymongoPayment(
                $booking,
                $amountPaid,
                $paidPayment['attributes']['source']['type']
                    ?? $attributes['payment_method_used']
                    ?? 'qrph',
                $paidPayment['id'] ?? $session['id'],
                $paymentType,
            );

            $booking->refresh();
        }

        return view('payment.success', [
            'booking'          => $booking,
            'amountPaid'       => $amountPaid,
            'paymentType'      => $paymentType,
            'paymentConfirmed' => (bool) $paidPayment,
        ]);

    } catch (\Exception $e) {
        // Hindi na maabot ang PayMongo — hindi natin masasabing bayad
        // na, kaya ipapakita ang "hinihintay pa" na estado. Ligtas ito
        // sa dalawang direksyon: kung nabayaran nga, darating pa rin ito
        // sa webhook at maaabisuhan ang guest.
        \Log::error('PayMongo success callback failed for booking '
            . $booking->booking_ref . ': ' . $e->getMessage());

        return view('payment.success', [
            'booking'          => $booking,
            'amountPaid'       => 0,
            'paymentType'      => $paymentType,
            'paymentConfirmed' => false,
        ]);
    }
}

    // ── Cancel Callback ────────────────────────────────────────────
    // GET /pay/{booking}/cancel
    public function cancel(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        $booking->update(['paymongo_session_id' => null]);
        return redirect()->route('customer.bookings.show', $booking)
            ->with('error', 'Payment was cancelled. Your booking is still reserved — you can try again anytime.');
    }

    // ── PayMongo Webhook ───────────────────────────────────────────
    // POST /webhooks/paymongo  (no auth middleware)
    /**
     * Callback ng Send Money — tinatawag ng PayMongo sa `callback_url`
     * ng isang transfer kapag na-settle na ito.
     *
     * SINASADYANG hindi pinagkakatiwalaan ang laman nito. Hindi
     * dokumentado ang hugis ng payload na ito, at hindi rin malinaw
     * kung pirmado ito gaya ng ordinaryong webhook — kaya ang tanging
     * ginagawa nito ay tanungin kung ALING transfer ang nagbago, at
     * ang tunay na estado ay kinukuha sa isang authenticated na GET.
     *
     * Ibig sabihin: kahit gawa-gawa ang tawag na ito, walang mangyayari
     * maliban sa isang pagtatanong sa PayMongo tungkol sa isang
     * transfer na atin naman talaga. Hindi kayang magmarka ng refund
     * bilang naipadala ang sinumang tumawag dito.
     */
    public function transferCallback(Request $request)
    {
        $transferId = data_get($request->all(), 'data.id')
            ?? data_get($request->all(), 'id')
            ?? $request->input('transfer_id');

        $query = \App\Models\RefundTransfer::where('status', 'pending');

        // Kung tinukoy kung alin, iyon lang ang tingnan — pero
        // hinahanap pa rin ito sa SARILING talaan natin, kaya hindi
        // makakapagpasok ng ibang transfer ang tumatawag.
        if ($transferId) {
            $query->where('transfer_id', $transferId);
        }

        $service   = app(\App\Services\RefundTransferService::class);
        $processed = 0;

        foreach ($query->get() as $transfer) {
            $service->syncStatus($transfer);
            $processed++;
        }

        \Log::info('PayMongo transfer callback handled', [
            'claimed_transfer' => $transferId,
            'synced'           => $processed,
        ]);

        // Palaging 200 — ang isang callback para sa transfer na hindi
        // natin kilala ay hindi pagkakamali ng PayMongo, at ang pag-
        // sagot ng error ay mag-uudyok lang ng walang saysay na retry.
        return response()->json(['received' => true, 'synced' => $processed]);
    }

    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        if (!$this->paymongo->verifyWebhook($payload, $signature)) {
            // Kinukuha ang pagkakakilanlan ng event para sa LOG LAMANG —
            // hindi pinagkakatiwalaan at walang ginagawa batay dito.
            //
            // Dating "invalid signature attempt" + IP lang ang naitatala,
            // kaya kinailangan pang tingnan ang ngrok inspector para
            // malaman kung ano ang tinanggihan. Ang `livemode` ang
            // karaniwang sagot: ang mga test event mula sa PayMongo
            // dashboard ay pumipirma sa `te=` slot gamit ang test secret,
            // kaya tama lang na hindi tumugma habang naka-live keys —
            // inaasahan iyon, hindi problema. Sinasabi na ito ng log.
            $peek = json_decode($payload, true);

            \Log::warning('PayMongo webhook: invalid signature — rejected', [
                'ip'       => $request->ip(),
                'event_id' => data_get($peek, 'data.id'),
                'type'     => data_get($peek, 'data.attributes.type'),
                'livemode' => data_get($peek, 'data.attributes.livemode'),
                'hint'     => data_get($peek, 'data.attributes.livemode') === false
                    ? 'Test-mode event while running live keys — expected, safe to ignore.'
                    : 'Check that PAYMONGO_WEBHOOK_SECRET matches the webhook registered for this mode.',
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data      = $request->json('data');
        $eventType = $data['attributes']['type'] ?? '';

        if ($eventType !== 'payment.paid') {
            return response()->json(['received' => true]);
        }

        $paymentObject = $data['attributes']['data'] ?? [];
        $paymentData   = $paymentObject['attributes'] ?? [];
        $metadata      = $paymentData['metadata'] ?? [];

        // Ang PayMongo payment ID (`pay_xxx`) — ito rin ang isinusulat
        // ng success callback sa `reference_number`, kaya ito ang
        // nagsisilbing duplicate guard kapag pareho silang tumakbo.
        $paymentRef = $paymentObject['id'] ?? null;
        $amount     = ($paymentData['amount'] ?? 0) / 100;

        $booking = $this->resolveWebhookBooking($metadata, $paymentData);

        if (! $booking || ! $paymentRef) {
            \Log::warning('PayMongo webhook: payment.paid could not be matched to a booking', [
                'payment_ref' => $paymentRef,
                'metadata'    => $metadata,
            ]);

            // 200 pa rin — ang isang hindi matukoy na booking ay hindi
            // maaayos ng pag-uulit ng PayMongo, at ang non-2xx ay
            // magti-trigger lang ng walang kwentang retry loop.
            return response()->json(['received' => true]);
        }

        // 'deposit' ang tawag dito sa checkout request level; 'partial'
        // ang katumbas nito sa payments.payment_type enum.
        $paymentType = ($metadata['payment_type'] ?? $booking->paymongo_payment_type) === 'full_payment'
            ? 'full_payment'
            : 'partial';

        $recorded = $this->recordPaymongoPayment(
            $booking,
            $amount,
            $paymentData['source']['type'] ?? 'qrph',
            $paymentRef,
            $paymentType,
        );

        \Log::info('PayMongo webhook: payment.paid handled', [
            'booking_ref' => $booking->booking_ref,
            'amount'      => $amount,
            'payment_ref' => $paymentRef,
            // false = naunahan na ng success callback, normal lang ito
            'recorded'    => $recorded,
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Hinahanap kung aling booking ang tinutukoy ng isang webhook event.
     *
     * Ang `metadata.booking_id` ang pangunahing paraan (itinatakda natin
     * ito sa createCheckoutSession), pero hindi lahat ng PayMongo flow ay
     * nagpo-propagate ng session metadata pababa sa payment object. Kaya
     * may dalawang fallback batay sa booking_ref, na ipinapasa naman
     * natin bilang `reference_number` ng checkout session.
     */
    private function resolveWebhookBooking(array $metadata, array $paymentData): ?Booking
    {
        if (! empty($metadata['booking_id'])) {
            $booking = Booking::find($metadata['booking_id']);

            if ($booking) {
                return $booking;
            }
        }

        $ref = $metadata['booking_ref']
            ?? $paymentData['external_reference_number']
            ?? null;

        return $ref ? Booking::where('booking_ref', $ref)->first() : null;
    }

    /**
     * Itinatala ang isang matagumpay na PayMongo payment.
     *
     * DALAWA ang puwedeng pasukan nito: ang success callback (kapag
     * bumalik ang guest sa site pagkatapos magbayad) at ang webhook
     * (kapag hindi siya bumalik — isinara ang browser, nawalan ng
     * signal, atbp.). Dati, ang success callback lang ang gumagawa ng
     * Payment row, kaya kapag hindi bumalik ang guest, nakuha na ng
     * PayMongo ang pera pero "hindi pa bayad" pa rin siya sa sistema.
     *
     * Iisang kopya ang money-math, notification, auto-confirm at email
     * dito mismo — kung hiwalay ang kopya ng dalawang path, tiyak na
     * mag-iiba rin ang kinalabasan nila sa paglipas ng panahon.
     *
     * Idempotent: ang `reference_number` (PayMongo `pay_xxx`) ang guard
     * laban sa dobleng pagtatala kapag pareho silang tumakbo.
     *
     * @return bool  true kung bagong payment ang naitala nito
     */
    private function recordPaymongoPayment(
        Booking $booking,
        float $amount,
        string $method,
        string $reference,
        string $paymentType,
    ): bool {
        if ($amount <= 0) {
            return false;
        }

        $alreadyRecorded = Payment::where('booking_id', $booking->id)
            ->where('reference_number', $reference)
            ->exists();

        if ($alreadyRecorded) {
            return false;
        }

        // Ang payments.payment_method ay 'qrph'/'cash' na lang. Kung
        // magpadala ang PayMongo ng ibang source type, sasabog ang
        // INSERT sa ENUM constraint — at dahil non-2xx ang isasagot ng
        // webhook, uulit-ulitin iyon ng PayMongo habang tuluyang hindi
        // naitatala ang bayad. Mas mabuti nang itala ito sa ilalim ng
        // pinakamalapit na valid na value, itago ang totoong value sa
        // notes, at i-log — kaysa mawala ang buong payment.
        //
        // Dito rin nahuhuli ang eksaktong string na ibinabalik ng
        // PayMongo para sa QR Ph. Kung hindi pala 'qrph' ang literal na
        // value, magpapatuloy pa rin ang bayad at lalabas ito sa log
        // bilang babala — sa halip na tahimik na mabigo.
        $known  = ['qrph', 'cash'];
        $stored = in_array($method, $known, true) ? $method : 'qrph';
        $notes  = 'PayMongo online payment';

        if ($stored !== $method) {
            $notes .= " (reported by PayMongo as '{$method}')";
            \Log::warning("PayMongo returned an unmapped payment method '{$method}' for booking {$booking->booking_ref}; stored as '{$stored}'.");
        }

        Payment::create([
            'booking_id'       => $booking->id,
            'amount'           => $amount,
            'payment_method'   => $stored,
            'payment_type'     => $paymentType,
            'status'           => 'success',
            'payment_date'     => today(),
            'reference_number' => $reference,
            'notes'            => $notes,
        ]);

        // Dating hindi binibilang ng kopyang ito ang mga refund, kaya
        // kung may naunang refund ang booking na ito, babalik sa dating
        // mataas na halaga ang amount_paid pagkatapos ng susunod na
        // online payment.
        $booking->update(['paymongo_session_id' => null]);
        $booking->recalculateFinancials();

        // MAHALAGA ang pagkakasunod: dati, nauuna ang notification kaysa
        // sa recalculateFinancials(), kaya ang "Balance due" na iniulat
        // sa admin ay LAGING ISANG HAKBANG NA HULI — ang balanse BAGO
        // ang bayad na katatanggap pa lang. Napatunayan sa live test:
        // ₱2 na bayad sa ₱4 na booking ay nag-ulat ng "Balance due:
        // ₱4.00"; ang panghuling ₱2 ay nag-ulat ng "₱2.00" gayong
        // bayad na nang buo. Tumatawag pagkatapos ng recompute.
        NotificationHelper::paymentReceived($booking->fresh(), $amount, $stored);

        // Auto-confirm pending bookings — WALANG admin approval step.
        // Kapag successful ang unang bayad (deposit o full), automatic
        // nang "confirmed" ang booking, at doon din ipapadala ang
        // confirmation email.
        //
        // Iisa ang kahulugan ng "kino-confirm ng unang bayad" —
        // pinagsasaluhan na ito ng tatlong manwal na record-payment path
        // sa pamamagitan ng Booking::confirmOnFirstPayment().
        $wasPending = $booking->confirmOnFirstPayment();

        // Notify guest (in-app)
        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Payment Received!',
            'message' => "Payment of ₱" . number_format($amount, 2) .
                " for booking {$booking->booking_ref} confirmed.",
            'link'    => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // Confirmation Email — ipinapadala LANG kapag ito yung unang
        // beses na naging "confirmed" ang booking (hindi kada
        // partial/balance payment pagkatapos).
        if ($wasPending) {
            try {
                Mail::to($booking->user->email)
                    ->send(new BookingConfirmedMail($booking->fresh(['user', 'property'])));
            } catch (\Exception $mailException) {
                // Hindi dapat i-fail ang buong request kung may isyu ang
                // email delivery — naka-log lang, dahil matagumpay
                // naman talaga ang bayad at booking.
                \Log::error('Failed sending booking confirmation email: ' . $mailException->getMessage());
            }
        }

        return true;
    }
}