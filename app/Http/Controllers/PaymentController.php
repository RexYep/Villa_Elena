<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Helpers\BookingMailHelper;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        $depositPct = (float) \App\Models\Setting::get('deposit_percentage', 50);
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
    //
    // Ang bawat hakbang dito ay tungkol sa IISANG tanong: paano
    // masisiguro na ang isang guest na nag-double-click, nag-refresh, o
    // bumalik sa page dahil mabagal ang internet ay hindi masisingil
    // nang dalawang beses.
    //
    // Ang guard laban sa dobleng PAGTATALA ng iisang bayad ay nasa
    // recordPaymongoPayment() (at sa unique index sa ilalim nito). Ito
    // naman ang guard laban sa dalawang MAGKAIBANG singil — na hindi
    // kayang hulihin ng idempotency, dahil dalawang tunay na bayad
    // iyon na may magkaibang `pay_xxx`.
    public function createCheckout(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        $request->validate([
            'payment_type' => 'required|in:deposit,full_payment',
        ]);

        // Serialisado kada booking. Ang dalawang sabay na POST (ang
        // klasikong dobleng pindot sa mabagal na koneksiyon) ay
        // parehong makakakita ng "walang session pa" at parehong
        // gagawa ng isa — dalawang buhay na QR para sa iisang booking.
        // Hindi ito puwedeng DB transaction: may HTTP call sa PayMongo
        // sa loob, at ang pagpapatakbo niyon sa loob ng transaction ang
        // eksaktong pagkakamaling nakalista sa §PayMongo ng CLAUDE.md.
        $lock = Cache::lock("paymongo-checkout:{$booking->id}", 20);

        if (! $lock->get()) {
            return back()->with('info', 'Your payment is already being set up — please wait a moment before trying again.');
        }

        try {
            // Sariwang basa sa loob ng lock: baka nakarating na ang
            // webhook mula noong na-render ang page na ito.
            $booking->refresh()->load(['property', 'user']);

            // Hindi lang sa showPaymentPage() dapat ito.
            //
            // Dati, ang tsekeng ito ay nasa GET lang — kaya ang isang
            // lumang tab o ang Back button ay makakapag-POST pa rin
            // dito para sa isang BAYAD NA booking. At dahil ang deposit
            // ay kinukuwenta mula sa `total_amount` (hindi sa balanse),
            // ang resulta ay isang bagong ₱2,000 na singil sa isang
            // booking na walang na ngang utang. Iyon ay dobleng bayad
            // na hindi na kailangan pa ng anumang race para mangyari.
            if ($booking->balance_due <= 0) {
                return redirect()->route('customer.bookings.show', $booking)
                    ->with('info', "Booking {$booking->booking_ref} is already fully paid — there is nothing left to pay.");
            }

            if (in_array($booking->status, ['cancelled', 'checked_out'], true)) {
                return redirect()->route('customer.bookings.show', $booking)
                    ->with('error', 'This booking can no longer be paid for.');
            }

            // Anti-abuse: server-side re-check (hindi lang basta umaasa sa
            // UI) — kung naka-flag ang guest sa excessive cancellations,
            // hindi papayagang "deposit" ang piliin kahit i-bypass ang form.
            if ($request->payment_type === 'deposit'
                && $booking->amount_paid == 0
                && Booking::hasExcessiveCancellations($booking->user_id)) {
                return back()->with('error', 'Dahil sa cancellation history mo, kailangan ng full payment para sa booking na ito — hindi available ang deposit option.');
            }

            // May bukas pa bang session? Ibalik iyon sa halip na gumawa
            // ng bago — ganito nagiging hindi nakakapinsala ang dobleng
            // pindot: iisang QR ang nakikita ng guest sa dalawang
            // pagkakataon, kaya kahit anong gawin niya, iisang singil.
            if ($reuse = $this->reusableCheckout($booking, $request->payment_type)) {
                return $reuse;
            }

            $depositPct = (float) \App\Models\Setting::get('deposit_percentage', 50);
            $depositAmount = round($booking->total_amount * $depositPct / 100, 2);

            // Ang deposit ay kinukuwenta mula sa `total_amount`, kaya
            // kailangan itong takpan ng natitirang balanse — kung hindi,
            // ang isang guest na may bahagyang bayad na ay masisingil ng
            // mas malaki pa sa utang niya.
            $amount = $request->payment_type === 'deposit'
                ? min($depositAmount, (float) $booking->balance_due)
                : (float) $booking->balance_due;

            $description = $request->payment_type === 'deposit'
                ? "Deposit ({$depositPct}%) for {$booking->property->property_name} — {$booking->booking_ref}"
                : "Full balance for {$booking->property->property_name} — {$booking->booking_ref}";

            try {
                $session = $this->paymongo->createCheckoutSession([
                    'amount' => $amount,
                    'description' => $description,
                    'guest_name' => $booking->user->full_name,
                    'guest_email' => $booking->user->email,
                    'guest_phone' => $booking->user->phone ?? '',
                    'reference_number' => $booking->booking_ref,
                    'booking_id' => $booking->id,
                    'payment_type' => $request->payment_type,
                    'success_url' => route('payment.success', $booking->id),
                    'cancel_url' => route('payment.cancel', $booking->id),
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
                return back()->with('error', 'Payment gateway error: '.$e->getMessage());
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Ang naunang checkout session ng booking na ito, kung magagamit pa.
     *
     * Tatlong posibleng sagot:
     *   - Bukas pa at pareho ang uri  → i-redirect doon (walang bagong singil)
     *   - Bayad na                    → papuntahin sa success page, na siyang
     *                                   magtatala nito (baka hindi pa nakakarating
     *                                   ang webhook — ito ang eksaktong kaso ng
     *                                   guest na nag-scan sa ibang device tapos
     *                                   nag-refresh dahil walang nangyari)
     *   - Wala/expired/iba ang uri    → null, gagawa ng bago ang caller
     *
     * Kapag hindi maabot ang PayMongo, `null` din ang isinasagot: mas
     * mabuti nang gumawa ng bagong session (na kayang bayaran ng guest)
     * kaysa mag-error nang tuluyan. Ang tunay na proteksiyon laban sa
     * dobleng singil sa ganoong sitwasyon ay ang balance check sa itaas.
     */
    private function reusableCheckout(Booking $booking, string $paymentType)
    {
        if (! $booking->paymongo_session_id || $booking->paymongo_payment_type !== $paymentType) {
            return null;
        }

        try {
            $session = $this->paymongo->getCheckoutSession($booking->paymongo_session_id);
        } catch (\Throwable $e) {
            \Log::warning("Could not re-read checkout session {$booking->paymongo_session_id} for {$booking->booking_ref}: ".$e->getMessage());

            return null;
        }

        $attributes = $session['attributes'] ?? [];

        $alreadyPaid = collect($attributes['payments'] ?? [])
            ->contains(fn ($p) => ($p['attributes']['status'] ?? null) === 'paid');

        if ($alreadyPaid) {
            return redirect()->route('payment.success', $booking);
        }

        return ! empty($attributes['checkout_url'])
            ? redirect($attributes['checkout_url'])
            : null;
    }

    // ── Success Callback ───────────────────────────────────────────
    // GET /pay/{booking}/success
    public function success(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        $booking->load(['property', 'user']);

        // Get session ID from booking record (stored during createCheckout)
        $sessionId = $booking->paymongo_session_id;

        if (! $sessionId) {
            // Nalinis na ang session_id ng recordPaymongoPayment(), kaya
            // naitala na ito — o wala talagang session mula sa umpisa.
            return view('payment.success', [
                'booking' => $booking,
                'amountPaid' => $booking->amount_paid,
                'paymentType' => $booking->paymongo_payment_type ?? 'payment',
                'paymentConfirmed' => $booking->amount_paid > 0,
            ]);
        }

        // 'deposit' (the checkout page's request-level choice, meaning
        // "pay less than the full total") maps to the payments table's
        // 'partial' payment_type — 'deposit' isn't a valid payment_type
        // value on its own.
        $paymentType = $booking->paymongo_payment_type === 'full_payment' ? 'full_payment' : 'partial';

        try {

            $session = $this->paymongo->getCheckoutSession($sessionId);
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
                'booking' => $booking,
                'amountPaid' => $amountPaid,
                'paymentType' => $paymentType,
                'paymentConfirmed' => (bool) $paidPayment,
            ]);

        } catch (\Exception $e) {
            // Hindi na maabot ang PayMongo — hindi natin masasabing bayad
            // na, kaya ipapakita ang "hinihintay pa" na estado. Ligtas ito
            // sa dalawang direksyon: kung nabayaran nga, darating pa rin ito
            // sa webhook at maaabisuhan ang guest.
            \Log::error('PayMongo success callback failed for booking '
                .$booking->booking_ref.': '.$e->getMessage());

            return view('payment.success', [
                'booking' => $booking,
                'amountPaid' => 0,
                'paymentType' => $paymentType,
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

        $service = app(\App\Services\RefundTransferService::class);
        $processed = 0;
        $failed = 0;

        foreach ($query->get() as $transfer) {
            // Bawat transfer ay hiwalay na sinasalo. Kapag walang
            // tinukoy na id, LAHAT ng pending ang nililibot dito — at
            // ang isang sumabog na transfer ay hindi dapat pumigil sa
            // iba, ni magpabalik ng 500 (tingnan ang webhook()).
            try {
                $service->syncStatus($transfer);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::error("PayMongo transfer callback: failed syncing transfer {$transfer->id}: ".$e->getMessage());
            }
        }

        \Log::info('PayMongo transfer callback handled', [
            'claimed_transfer' => $transferId,
            'synced' => $processed,
            'failed' => $failed,
        ]);

        // Palaging 200 — ang isang callback para sa transfer na hindi
        // natin kilala ay hindi pagkakamali ng PayMongo, at ang pag-
        // sagot ng error ay mag-uudyok lang ng walang saysay na retry.
        return response()->json(['received' => true, 'synced' => $processed, 'failed' => $failed]);
    }

    /**
     * LAGING 200 ANG ISINASAGOT NG ENDPOINT NA ITO. Sinasadya iyon.
     *
     * Awtomatikong DINI-DISABLE ng PayMongo ang isang webhook na
     * paulit-ulit na sumasagot ng 4xx o 5xx, at HINDI ito bumabalik
     * nang kusa. Habang naka-disable, wala nang kahit anong event na
     * dumarating — kaya ang BAWAT online na bayad pagkatapos noon ay
     * tahimik na hindi naitatala. Ang halaga ng maling 200 ay isang
     * naitalang babala sa log; ang halaga ng maling 401 ay ang buong
     * endpoint. Malayong mas mahal ang pangalawa.
     *
     * Ganito nga ang nangyari (v6.1): may test-mode na webhook na
     * nakaturo sa produksyon habang ibang mode ang secret na hawak
     * doon, kaya bumagsak ang signature ng bawat delivery, 401 ang
     * naisasagot, at dini-disable ito ng PayMongo — na siyang ipinaalam
     * nila sa email.
     *
     * DALAWA ang inayos: (1) hindi na kailanman non-2xx ang sagot dito,
     * at (2) mode-aware na ang signature check kaya puwede nang
     * magkasabay na naka-rehistro ang test at live na webhook sa iisang
     * URL (tingnan ang PayMongoService::verifyWebhook()).
     *
     * Ang kapalit ng laging-200: hindi na uulitin ng PayMongo ang isang
     * event na bumagsak sa gitna ng pagproseso. Kaya inaabisuhan ang
     * admin sa bawat ganoong pagkakataon — may perang natanggap na
     * kailangang itala nang manwal, at ang tahimik na pagkawala niyon
     * ang tanging bagay na mas masahol pa sa isang retry loop.
     */
    public function webhook(Request $request)
    {
        try {
            $result = $this->handleWebhookEvent($request);
        } catch (\Throwable $e) {
            \Log::error('PayMongo webhook: unhandled error while processing event', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'payload' => Str::limit($request->getContent(), 2000),
            ]);

            $this->announceWebhookFailure($request, $e);

            $result = ['received' => true, 'handled' => false, 'reason' => 'processing_error'];
        }

        return response()->json($result, 200);
    }

    /**
     * Ang aktwal na pagproseso ng event.
     *
     * Malayang magtapon ito ng exception — sinasalo ito ng webhook() at
     * ginagawang 200. Ang hiwalay na method ang siyang dahilan kung
     * bakit walang paraan na makalusot ang isang non-2xx mula rito:
     * walang `response()` sa loob, arrays lang ang ibinabalik.
     *
     * @return array<string, mixed>
     */
    private function handleWebhookEvent(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        if (! $this->paymongo->verifyWebhook($payload, $signature)) {
            // Kinukuha ang pagkakakilanlan ng event para sa LOG LAMANG —
            // hindi pinagkakatiwalaan at walang ginagawa batay dito.
            //
            // Dating "invalid signature attempt" + IP lang ang naitatala,
            // kaya kinailangan pang tingnan ang ngrok inspector para
            // malaman kung ano ang tinanggihan. Ang `livemode` ang
            // karaniwang sagot: ang test event ay pumipirma sa `te=` slot
            // gamit ang TEST na webhook secret, at ang live sa `li=` gamit
            // ang LIVE — MAGKAIBANG secret sila, kaya kailangang nakatakda
            // ang tugma sa mode ng webhook na naka-rehistro.
            $peek = json_decode($payload, true);
            $livemode = data_get($peek, 'data.attributes.livemode');

            \Log::warning('PayMongo webhook: invalid signature — event ignored', [
                'ip' => $request->ip(),
                'event_id' => data_get($peek, 'data.id'),
                'type' => data_get($peek, 'data.attributes.type'),
                'livemode' => $livemode,
                'hint' => match (true) {
                    ! $this->paymongo->hasWebhookSecret() => 'No webhook secret is configured at all — set PAYMONGO_WEBHOOK_SECRET (or the _TEST / _LIVE variant).',
                    $livemode === false => 'Test-mode event: it signs the te= slot with that TEST webhook\'s secret. Put it in PAYMONGO_WEBHOOK_SECRET_TEST.',
                    $livemode === true => 'Live-mode event: it signs the li= slot with that LIVE webhook\'s secret. Put it in PAYMONGO_WEBHOOK_SECRET_LIVE.',
                    default => 'Check that the configured secret matches the webhook registered for this mode.',
                },
            ]);

            // 200 pa rin — tingnan ang paliwanag sa webhook(). Hindi
            // pinoproseso ang event; tinatanggihan lang nang tahimik.
            return ['received' => true, 'handled' => false, 'reason' => 'invalid_signature'];
        }

        $data = $request->json('data');

        // Hindi laging array ang laman nito: ang isang malformed o
        // hindi-JSON na body ay nagbibigay ng null o string dito, at ang
        // pag-index doon ay TypeError — ibig sabihin 500, ibig sabihin
        // patungo sa pagka-disable. Sinusuri bago hawakan.
        if (! is_array($data)) {
            \Log::warning('PayMongo webhook: payload had no usable `data` object', [
                'payload' => Str::limit($payload, 500),
            ]);

            return ['received' => true, 'handled' => false, 'reason' => 'malformed_payload'];
        }

        $eventType = data_get($data, 'attributes.type', '');

        if ($eventType !== 'payment.paid') {
            return ['received' => true, 'handled' => false, 'reason' => 'unhandled_event'];
        }

        $paymentObject = data_get($data, 'attributes.data');
        $paymentObject = is_array($paymentObject) ? $paymentObject : [];
        $paymentData = is_array($paymentObject['attributes'] ?? null) ? $paymentObject['attributes'] : [];
        $metadata = is_array($paymentData['metadata'] ?? null) ? $paymentData['metadata'] : [];

        // Ang PayMongo payment ID (`pay_xxx`) — ito rin ang isinusulat
        // ng success callback sa `reference_number`, kaya ito ang
        // nagsisilbing duplicate guard kapag pareho silang tumakbo.
        $paymentRef = $paymentObject['id'] ?? null;
        $amount = ($paymentData['amount'] ?? 0) / 100;

        $booking = $this->resolveWebhookBooking($metadata, $paymentData);

        if (! $booking || ! $paymentRef) {
            \Log::warning('PayMongo webhook: payment.paid could not be matched to a booking', [
                'payment_ref' => $paymentRef,
                'metadata' => $metadata,
            ]);

            // Hindi maaayos ng pag-uulit ng PayMongo ang isang hindi
            // matukoy na booking — pero may perang natanggap na walang
            // kinakabitan, kaya kailangan itong makita ng tao.
            $this->notifyAdminsQuietly(
                'Unmatched online payment',
                'A PayMongo payment.paid event ('.($paymentRef ?: 'no reference').', ₱'.
                number_format($amount, 2).') could not be matched to any booking. '.
                'Check PayMongo and record it manually if the money arrived.',
            );

            return ['received' => true, 'handled' => false, 'reason' => 'unmatched_booking'];
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
            'amount' => $amount,
            'payment_ref' => $paymentRef,
            // false = naunahan na ng success callback, normal lang ito
            'recorded' => $recorded,
        ]);

        return ['received' => true, 'handled' => true, 'recorded' => $recorded];
    }

    /**
     * Ipinapaalam sa admin ang isang event na bumagsak sa pagproseso.
     *
     * Kailangan ito dahil sa laging-200: hindi na uulitin ng PayMongo
     * ang event, kaya kung may perang dumating, ito na lang ang tanging
     * senyales bukod sa log.
     */
    private function announceWebhookFailure(Request $request, \Throwable $e): void
    {
        $peek = json_decode($request->getContent(), true);

        $this->notifyAdminsQuietly(
            'Online payment webhook failed',
            'A PayMongo webhook event ('.(data_get($peek, 'data.attributes.type') ?: 'unknown type').
            ') could not be processed: '.Str::limit($e->getMessage(), 180).
            ' PayMongo will not retry it — check the payment and record it manually if needed.',
        );
    }

    /**
     * Nag-a-abiso sa admin nang hindi kailanman nagtatapon.
     *
     * Ito ay tinatawag mula sa mga failure path ng webhook, kabilang ang
     * catch-all — at kung ang database mismo ang bumagsak, sasabog din
     * ang notification na ito. Hindi dapat iyon pumatay sa 200 na sagot,
     * kaya nasasalo rin ang sarili niyang pagkabigo.
     */
    private function notifyAdminsQuietly(string $title, string $message): void
    {
        try {
            NotificationHelper::notifyAdmin(
                $title,
                $message,
                route('admin.payments.index', [], false),
            );
        } catch (\Throwable $inner) {
            \Log::error('PayMongo webhook: could not notify admins of the failure: '.$inner->getMessage());
        }
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
     * @return bool true kung bagong payment ang naitala nito
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

        // Unang sala: ang naitala na ba ang BAYAD NA ITO?
        //
        // Ito ang normal na daan — sabay na dumarating ang webhook at
        // ang success callback para sa iisang `pay_xxx`. Isang SELECT
        // lang ito, kaya hindi ito sapat mag-isa: dalawang request na
        // sabay tumatakbo ay parehong makakakita ng "wala pa". Kaya may
        // UNIQUE (booking_id, reference_number) sa ilalim nito, na
        // sinasalo sa baba. Panatilihin silang dalawa: ang SELECT ang
        // umiiwas sa halos lahat ng kaso nang walang exception, at ang
        // index ang sumasagip sa natitira.
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
        $known = ['qrph', 'cash'];
        $stored = in_array($method, $known, true) ? $method : 'qrph';
        $notes = 'PayMongo online payment';

        if ($stored !== $method) {
            $notes .= " (reported by PayMongo as '{$method}')";
            \Log::warning("PayMongo returned an unmapped payment method '{$method}' for booking {$booking->booking_ref}; stored as '{$stored}'.");
        }

        try {
            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'payment_method' => $stored,
                'payment_type' => $paymentType,
                'status' => 'success',
                'payment_date' => today(),
                'reference_number' => $reference,
                'notes' => $notes,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Nauna sa atin ang kabilang path sa pagitan ng SELECT sa
            // itaas at ng INSERT na ito. Naitala na ang bayad — walang
            // nawala, at walang dapat gawin. Hindi ito error.
            \Log::info("PayMongo payment {$reference} for {$booking->booking_ref} was already recorded concurrently — duplicate insert refused by the unique index.");

            return false;
        }

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
            'type' => 'in_app',
            'title' => 'Payment Received!',
            'message' => 'Payment of ₱'.number_format($amount, 2).
                " for booking {$booking->booking_ref} confirmed.",
            'link' => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Confirmation / resibo — ipinapadala sa BAWAT matagumpay na
        // bayad, hindi lang sa una. Dati, naka-gate ito sa $wasPending,
        // kaya ang guest na nagbayad ng 50% downpayment ay nakatanggap
        // ng email pero ang pagbabayad ng natitirang balanse ay tahimik
        // na — walang resibo, at walang kumpirmasyon na bayad na nang
        // buo. Ang $wasPending ngayon ay pumipili na lang ng ANYO ng
        // email (kumpirmasyon vs. resibo), hindi na kung ipapadala ba.
        BookingMailHelper::paymentRecorded($booking, (float) $amount, $wasPending);

        return true;
    }
}
