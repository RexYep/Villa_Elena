<?php

namespace App\Helpers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    // ── Create + Broadcast a Notification ──────────────────────────
    protected static function create(int $userId, string $title, string $message, ?string $link, string $type, bool $broadcast = true): void
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // `$broadcast = false` ay para sa MARAMIHANG blast lang.
        //
        // Ang NotificationCreated ay ShouldBroadcast, at ang
        // QUEUE_CONNECTION ay `sync` — kaya ang bawat abiso ay isang
        // HUMAHARANG na HTTP call sa Pusher sa loob mismo ng request.
        // Sa 8 guest, ~12 segundo iyon; sa 500, isang timeout. Iyon ang
        // dahilan kung bakit mukhang nag-hang ang pag-save ng promo at
        // napindot itong muli, kaya dalawang promo at dalawang blast
        // ang nagawa (v7.50).
        //
        // Ang kapalit ay tahasan: ang isang guest na nakabukas ang
        // portal ay HINDI makakakita ng toast para sa isang anunsyo —
        // kukunin ito ng bell sa susunod na page load. Tama ang palitan
        // na iyon para sa marketing; HUWAG itong gamitin para sa bayad,
        // booking o issue-report na abiso, kung saan ang realtime ang
        // mismong punto.
        if (! $broadcast) {
            return;
        }

        // This helper gets called from registration, bookings, payments,
        // etc. — a broadcast hiccup (bad/missing Pusher config, API
        // outage) should never take down the flow that triggered it. The
        // notification row above is already saved either way, so the
        // in-app bell still picks it up on next page load even if the
        // realtime push fails.
        try {
            event(new NotificationCreated($notification));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast notification: ' . $e->getMessage());
        }
    }

    // ── Notify Admin(s) ────────────────────────────────────────────
    public static function notifyAdmin(string $title, string $message, ?string $link = null, string $type = 'in_app'): void
    {
        // Notify all admin users
        $admins = User::where('role', 'admin')->pluck('id');

        foreach ($admins as $adminId) {
            self::create($adminId, $title, $message, $link, $type);
        }
    }

    // ── Notify a Specific Guest ────────────────────────────────────
    public static function notifyGuest(int $userId, string $title, string $message, ?string $link = null, string $type = 'in_app', bool $broadcast = true): void
    {
        self::create($userId, $title, $message, $link, $type, $broadcast);
    }

    // ── Preset: New Booking Received ───────────────────────────────
    public static function newBooking($booking): void
    {
        $guestName   = $booking->user->full_name ?? 'Guest';
        $propertyName= $booking->property->property_name ?? 'Property';
        $source      = ucfirst(str_replace('_', ' ', $booking->source ?? 'online'));

        self::notifyAdmin(
            "New Booking — {$booking->booking_ref}",
            "{$guestName} placed a {$source} booking for {$propertyName}. " .
            "Check-in: {$booking->check_in_date->format('M d, Y')}. " .
            "Total: ₱" . number_format($booking->total_amount, 2) . ". Status: Pending.",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Payment Received (online/automatic) ────────────────
    public static function paymentReceived($booking, float $amount, string $method): void
    {
        $guestName   = $booking->user->full_name ?? 'Guest';
        // Dating `ucfirst(str_replace(...))` — "Qrph" ang lumalabas.
        $methodLabel = \App\Models\Payment::methodLabelFor($method);

        self::notifyAdmin(
            "Payment Received — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " received from {$guestName} via {$methodLabel}. " .
            "Balance due: ₱" . number_format($booking->balance_due, 2) . ".",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Payment Recorded Manually By Admin/Staff ────────────
    public static function paymentRecorded($booking, float $amount, string $method): void
    {
        $guestName   = $booking->user->full_name ?? 'Guest';
        $methodLabel = \App\Models\Payment::methodLabelFor($method);

        self::notifyAdmin(
            "Payment Recorded — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " manually recorded for {$guestName} via {$methodLabel}. " .
            "Balance due: ₱" . number_format($booking->balance_due, 2) . ".",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Booking Cancelled by Guest ────────────────────────
    public static function bookingCancelled($booking, string $reason = ''): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Booking Cancelled — {$booking->booking_ref}",
            "{$guestName} cancelled their booking for {$propertyName}. " .
            ($reason ? "Reason: {$reason}" : "No reason provided."),
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Booking Auto-Cancelled by the System (unpaid hold) ──
    public static function bookingAutoCancelled($booking, string $holdLabel): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Booking Auto-Cancelled — {$booking->booking_ref}",
            "{$guestName}'s booking for {$propertyName} was automatically cancelled by the system — " .
            "the 50% downpayment wasn't completed within the {$holdLabel} hold window. No action needed; " .
            "the slot has been freed.",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Review Submitted (guest-facing receipt) ─────────────
    // Dati, flash message lang ang natatanggap ng guest pagka-submit —
    // nawawala pagkatapos ng isang page load, walang matitirang record
    // kung na-publish agad o hinihintay pa ang approval. Ito ang
    // lasting na kopya, iisa lang para sa dalawang resulta.
    public static function reviewSubmitted($review): void
    {
        $propertyName = $review->property->property_name ?? 'Property';

        $message = $review->status === 'approved'
            ? "Your {$review->rating}-star review for {$propertyName} is now live. Thank you for sharing your experience!"
            : "Your {$review->rating}-star review for {$propertyName} was submitted and is awaiting a quick review before it goes live.";

        self::notifyGuest(
            $review->user_id,
            'Review Submitted',
            $message,
            route('customer.reviews.index', [], false)
        );
    }

    // ── Preset: New Guest Registered ──────────────────────────────
    public static function newGuestRegistered($user): void
    {
        self::notifyAdmin(
            "New Guest Registered",
            "{$user->full_name} ({$user->email}) just created an account.",
            route('admin.users.show', $user, false)
        );
    }

    // ── Preset: Walk-in Booking Created ───────────────────────────
    public static function walkInBooking($booking, string $staffName): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Walk-in Booking — {$booking->booking_ref}",
            "Walk-in booking created by {$staffName} for {$guestName} at {$propertyName}. " .
            "Check-in: {$booking->check_in_date->format('M d, Y')}.",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Refund Issued ──────────────────────────────────────
    public static function refundIssued($booking, float $amount, string $reason, $refund = null): void
    {
        // Sinasadyang "needs to be sent" — hindi "refunded". Naitala pa
        // lang ang refund na ito; wala pang perang lumalabas. Dating
        // "refunded" ang nakasulat dito, kaya mukhang tapos na ang isang
        // bagay na hindi pa naman talaga nagagawa.
        //
        // Ang link ay papunta sa refund MISMO kapag ibinigay ito, hindi sa
        // listahan: doon nakikita ang tamang button para sa kalagayan nito.
        self::notifyAdmin(
            "Refund To Send — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " needs to be sent to the guest for booking {$booking->booking_ref}. " .
            "Reason: {$reason}. " . self::refundNextStepForAdmin($refund),
            $refund
                ? route('admin.payments.show', $refund, false)
                : route('admin.payments.index', ['status' => 'awaiting_payout'], false)
        );
    }

    // ── Preset: Ibinigay na ng guest ang account niya (admin) ─────
    /**
     * Dating walang nakakaalam nito: tahimik lang nawawala ang "NEEDS
     * DETAILS" sa listahan, at ang paalalang "still unsent" ay tatlong
     * araw pa bago dumating. Sinabihan na ang guest na "we'll notify you
     * when it's sent" — at, kapag tinanggihan ang dati niyang account,
     * na "we'll send it again" — kaya may dapat makaalam na puwede na.
     *
     * Ang tumatawag ang nagpapasya kung may NAGBAGO talaga; ang muling
     * pag-save ng parehong detalye ay hindi balita.
     */
    public static function refundDetailsProvided($payment, $destination): void
    {
        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $guestName = $booking->user->full_name ?? 'The guest';

        self::notifyAdmin(
            "Refund Ready To Send — {$booking->booking_ref}",
            "{$guestName} gave account details for the ₱" . number_format($payment->amount, 2)
            . " refund on booking {$booking->booking_ref}: " . \App\Models\Payment::accountPhrase($destination) . '. '
            . self::refundNextStepForAdmin($payment),
            route('admin.payments.show', $payment, false)
        );
    }

    /**
     * Ano ang susunod na gagawin ng admin sa isang bagong refund.
     *
     * Iisang pangungusap ito para sa abiso sa itaas at sa flash message
     * ng Issue Refund, at kapareho ng sinasabi ng banner sa Payments
     * page (v7.65). Dating "mark it as paid out once the money has been
     * sent" ang dalawa — ang manu-manong daan bago nagkaroon ng Send
     * Refund — habang ang banner ay nagsasabing Send Refund ang gamitin.
     * Ang magkasalungat na tagubilin ang mapanganib dito: ang Mark Paid
     * Out ay HINDI nagpapadala ng pera, at napindot na ito nang walang
     * perang gumalaw (project.md §6.11).
     */
    public static function refundNextStepForAdmin($refund = null): string
    {
        if ($refund && $refund->payment_method === 'cash') {
            return 'This one is cash: hand it to the guest, then record it with Mark Paid Out.';
        }

        $first = ($refund && ! $refund->needsRefundDestination())
            ? 'Open the refund and use Send Refund — the system transfers the money itself.'
            : 'The guest has been asked where to send it. Once the account details are in, '
                . 'open the refund and use Send Refund — the system transfers the money itself.';

        return $first . ' Mark Paid Out is only for money you already sent by hand.';
    }

    // ══════════════════════════════════════════════════════════════
    //  GUEST-FACING PRESETS
    //
    //  Lahat ng preset sa itaas ay para sa admin. Ang guest naman ay
    //  umaasa dati sa flash message lang — nawawala ito pagkatapos ng
    //  isang page load, kaya walang natitirang record na natanggap nga
    //  ang cancellation o naaprubahan ang refund niya. Dito nakatira
    //  ang guest-facing na kopya para iisa lang ang wording kahit saan
    //  pang entry point galing ang cancellation/refund.
    // ══════════════════════════════════════════════════════════════

    // ── Preset: Cancellation Confirmed (guest) ────────────────────
    // Para lang ito sa pag-cancel NG GUEST, kaya wala na itong refund
    // (v7.52 — non-refundable ang lahat ng bayad). `$forfeited` ang
    // halagang naibayad na hindi na maibabalik; ₱0 kung wala pa siyang
    // naibabayad, at doon ay walang dapat sabihin tungkol sa pera.
    public static function bookingCancelledForGuest($booking, float $forfeited = 0): void
    {
        $message = "Your booking {$booking->booking_ref} has been cancelled.";

        if ($forfeited > 0) {
            $message .= ' The ₱' . number_format($forfeited, 2)
                . ' you paid is non-refundable under our booking policy, so no refund will be sent.';
        }

        self::notifyGuest(
            $booking->user_id,
            "Booking Cancelled — {$booking->booking_ref}",
            $message,
            route('customer.bookings.show', $booking, false)
        );
    }

    /**
     * Idinadagdag ang tanong na "saan ipapadala?" kapag ito ang aktwal
     * na kulang, at inililipat ang notification link papunta sa form.
     *
     * Iisang lugar ito para sa dalawang guest-facing na refund preset —
     * ang buong punto ng mga preset na ito ay para hindi maghiwalay ang
     * pananalita sa bawat call site (tingnan ang v5.6 sa project.md).
     *
     * Ang link ay RELATIVE (`route(..., false)`), gaya ng lahat ng iba
     * dito: ang absolute URL ay nagbe-bake ng kung anumang APP_URL o
     * tunnel host ang aktibo noong nilikha ito, at namamatay kapag
     * nagbago iyon.
     */
    private static function withRefundDestinationPrompt(string $message, $booking, $refund): array
    {
        if ($refund && $refund->needsRefundDestination()) {
            return [
                $message . " First, please tell us where to send it — open this to add your "
                    . "GCash, Maya, or bank account details.",
                route('customer.refunds.destination', $refund, false),
            ];
        }

        return [$message, route('customer.bookings.show', $booking, false)];
    }

    /**
     * Ang iisang pangungusap na pareho sa lahat ng "may darating na
     * refund" na abiso: wala pang perang lumalabas. Ang refund row ay
     * ginagawa bilang 'pending', kaya kung hindi ito sasabihin ay
     * hahanapin ng guest sa GCash niya ang perang hindi pa naipapadala.
     *
     * "When it's sent", hindi "once it's on its way": ang kasunod na
     * abiso ay maaaring "On Its Way", "Received", "Sent" o "Paid in
     * Cash" — tingnan ang refundPaidOut().
     */
    private const REFUND_NOT_SENT_YET = "The money hasn't been sent yet — we'll notify you again when it's sent.";

    // ── Preset: Refund Coming — hindi pa naipapadala (guest) ──────
    // Dating "Refund Approved" ito para sa lahat (v5.6). Ang "approved"
    // ay nagpapahiwatig na may HINILING ang guest — pero mula v7.52 ay
    // hindi na siya makakahiling ng refund, kaya ang bawat refund dito
    // ay pasya ng resort. Mas malala pa para sa guest na nag-cancel:
    // kakasabi pa lang sa kanya na "no refund will be sent".
    //
    // Kaya ang pangungusap ay pinipili ng URI ng refund
    // (`Payment::REFUND_KINDS`, pinili ng admin), hindi ng malayang
    // text. Ang tinype ng admin ay panloob na tala na lang — dating
    // ipinapakita ito nang buo sa guest ("Reason: …") nang walang
    // nagsasabi sa admin na mababasa niya ito.
    //
    // Ang "bakit" ay galing sa `Payment::refundReasonForGuest()` — ang
    // parehong pangungusap na ipinapakita ng refund panel sa booking
    // details, kaya hindi puwedeng magkaiba ang abiso at ang pahina.
    public static function refundComingForGuest($booking, float $amount, string $kind, $refund = null): void
    {
        $ref    = $booking->booking_ref;
        $reason = \App\Models\Payment::refundReasonForGuest($kind);

        $message = 'A refund of ₱' . number_format($amount, 2) . " is coming for booking {$ref}. "
            . ($reason ? $reason . ' ' : '')
            . self::REFUND_NOT_SENT_YET;

        self::emailRefundComing($booking, 'Refund Coming', $message, $amount, $refund);

        [$message, $link] = self::withRefundDestinationPrompt($message, $booking, $refund);

        self::notifyGuest(
            $booking->user_id,
            "Refund Coming — {$ref}",
            $message,
            $link
        );
    }

    /**
     * Ang email na katapat ng dalawang "may darating na refund" na abiso
     * (v7.68). Ang `$message` ay ang pangungusap BAGO idagdag ng
     * withRefundDestinationPrompt() ang "open this to add your … details":
     * walang "this" na mabubuksan sa isang email, kaya button ang
     * pumapalit doon, na may sariling pangungusap sa itaas nito.
     */
    private static function emailRefundComing($booking, string $heading, string $message, float $amount, $refund): void
    {
        $needsDetails = $refund && $refund->needsRefundDestination();

        BookingMailHelper::refundNotice(
            $booking,
            $heading,
            $message,
            $amount,
            $needsDetails
                ? route('customer.refunds.destination', $refund, false)
                : route('customer.bookings.show', $booking, false),
            $needsDetails ? 'Add account details' : 'View your booking',
            $needsDetails
                ? 'Before we can send it, we need to know which bank or e-wallet account should receive it.'
                : null
        );
    }

    // ── Preset: Kinansela ng RESORT ang booking — buong refund (guest) ──
    // Ito ang pinakamahalagang abiso sa buong refund flow, at dating
    // dumarating ito bilang "Booking Status Updated … status has been
    // updated to: Cancelled" — isang inline `Notification::create()` sa
    // Admin\BookingController na hindi rin dumadaan sa broadcast, kaya
    // walang toast. Iisa na ang pananalita nito at ng iba pang refund.
    public static function bookingCancelledByResortForGuest($booking, float $refundAmount, $refund = null): void
    {
        $message = "We're sorry — we had to cancel your booking {$booking->booking_ref} on our side. "
            . 'The full ₱' . number_format($refundAmount, 2) . ' you paid will be refunded. '
            . self::REFUND_NOT_SENT_YET;

        self::emailRefundComing($booking, 'Booking Cancelled by the Resort', $message, $refundAmount, $refund);

        [$message, $link] = self::withRefundDestinationPrompt($message, $booking, $refund);

        self::notifyGuest(
            $booking->user_id,
            "Booking Cancelled by the Resort — {$booking->booking_ref}",
            $message,
            $link
        );
    }

    // ── Preset: Refund Aktwal Nang Naipadala (guest + admin) ──────
    // Ito ang kabilang dulo ng refund lifecycle. Dati, ang pagpindot ng
    // "Mark as Paid Out" ay tahimik — walang nalalaman ang guest na
    // naipadala na ang pera niya, at walang kumpirmasyon ang admin na
    // sarado na ang refund na iyon.
    /**
     * Ang refund ay naipadala na pero hindi pa dumarating.
     *
     * Ito ang nawawalang yugto. Sa InstaPay ay 2 segundo lang ito, kaya
     * hindi kailangan — pero ang GCash ay dumaraan sa PESONet, at doon
     * ay maghihintay ang guest nang ilang ORAS o hanggang sa susunod
     * na banking day. Kung walang magsasabi nito, ang tanging alam
     * niya ay "inaprubahan" tapos katahimikan — at ang katahimikan sa
     * usaping pera ay parang nawawala ang refund.
     *
     * Tinatawag LAMANG ito kapag hindi agad na-settle, para hindi
     * makatanggap ng dalawang magkasunod na abiso ang guest sa mga
     * transfer na dumarating agad.
     */
    public static function refundOnTheWay($payment, $transfer): void
    {
        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $amount = number_format($payment->amount, 2);
        $where  = $transfer->institution_name;

        $when = $transfer->provider === 'instapay'
            ? 'It should arrive shortly.'
            : "Transfers to {$where} clear in batches on banking days (11am, 2pm and 5pm), "
              . 'so expect it later today or on the next banking day.';

        self::notifyGuest(
            $booking->user_id,
            "Refund On Its Way — {$booking->booking_ref}",
            "Your refund of ₱{$amount} for booking {$booking->booking_ref} is on its way to your "
            . "{$where} account. {$when} We will let you know once it has landed.",
            route('customer.bookings.show', $booking, false)
        );
    }

    // ── Preset: Tinanggihan ang account ng guest (guest) ──────────
    /**
     * Sinubukang ipadala ang refund at tinanggihan ng bangko ang ACCOUNT
     * (`RefundTransfer::isAccountDetailProblem()`).
     *
     * Dating admin lang ang inaabisuhan ("Check the details with the
     * guest and try again") — pero ang guest lang ang makakapag-ayos
     * nito, at wala siyang nalalaman. Ang link ay diretso sa form.
     *
     * Ang pangungusap ay kapareho ng nasa refund panel
     * (`Payment::DETAILS_REJECTED_NOTE`).
     */
    public static function refundDetailsRejectedForGuest($payment, $transfer): void
    {
        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $amount  = number_format($payment->amount, 2);
        $message = "We tried to send your ₱{$amount} refund for booking {$booking->booking_ref} to your "
            . \App\Models\Payment::accountPhrase($transfer) . ', ' . \App\Models\Payment::DETAILS_REJECTED_NOTE;
        $link    = route('customer.refunds.destination', $payment, false);

        self::notifyGuest(
            $booking->user_id,
            "Refund Could Not Be Delivered — {$booking->booking_ref}",
            $message,
            $link
        );

        BookingMailHelper::refundNotice(
            $booking, 'Refund Could Not Be Delivered', $message, (float) $payment->amount, $link, 'Check account details'
        );
    }

    /**
     * Sarado na ang refund. TATLONG magkaibang pangyayari ang dumadaan
     * dito, at magkaiba ang totoo sa bawat isa (v7.63):
     *
     *   may `$transfer` — ipinadala ng sistema at `succeeded` na sa
     *                     PayMongo: DUMATING na ang pera.
     *   cash            — iniabot nang personal; walang account.
     *   iba pa          — ipinadala ng admin sa sarili niyang app at
     *                     itinala sa "Mark Paid Out": hindi natin alam
     *                     kung dumating na.
     *
     * Dating iisang "Refund Sent … allow a few banking days" ang
     * tatlo — mali sa una (nasa account na niya), salungat sa
     * refundOnTheWay() na nangakong sasabihin kapag "landed" na, at
     * walang saysay sa cash ("sent via Cash … in your account").
     *
     * HUWAG ibalik ang `$payment->method_label` dito. Ang refund row ay
     * kumokopya ng `payment_method` ng ORIHINAL na bayad, kaya "sent
     * via QR Ph" ang lumalabas — hindi naipapadala ang refund sa QR Ph.
     * Ang destinasyon ang sinasabi, at galing ito sa `$transfer` kapag
     * mayroon: iyon ang hindi nababagong tala ng aktwal na pinuntahan.
     */
    public static function refundPaidOut($payment, $transfer = null): void
    {
        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $amount = number_format($payment->amount, 2);
        $ref    = $booking->booking_ref;

        // Huling 4 na digit lang — financial account data ito.
        $where = \App\Models\Payment::accountPhrase($transfer ?: $payment->refundDestination);

        if ($transfer) {
            $title     = "Refund Received — {$ref}";
            $guestText = "Your refund of ₱{$amount} for booking {$ref} has arrived in your {$where}. "
                . "Transfer reference: {$transfer->receipt_reference}.";
            $adminText = "was delivered to the guest's {$where} by PayMongo.";
        } elseif ($payment->payment_method === 'cash') {
            $title     = "Refund Paid in Cash — {$ref}";
            $guestText = "Your refund of ₱{$amount} for booking {$ref} has been paid to you in cash. "
                . 'If you did not receive it, please contact the resort.';
            $adminText = 'was recorded as paid to the guest in cash.';
        } else {
            $title     = "Refund Sent — {$ref}";
            $guestText = "Your refund of ₱{$amount} for booking {$ref} has been sent"
                . ($where ? " to your {$where}" : '') . '.'
                . ($payment->transaction_ref ? " Transfer reference: {$payment->transaction_ref}." : '')
                . ' It usually shows up within minutes, but a bank transfer can take until the next banking day.'
                . ' If it has not arrived by then, please contact the resort.';
            $adminText = 'was recorded as sent by hand' . ($where ? " to the guest's {$where}" : '') . '.';
        }

        self::notifyGuest(
            $booking->user_id,
            $title,
            $guestText,
            route('customer.bookings.show', $booking, false)
        );

        // Ang pamagat ng email ay walang " — {ref}": idinadagdag iyon ng
        // subject ng RefundNoticeMail.
        BookingMailHelper::refundNotice(
            $booking,
            \Illuminate\Support\Str::before($title, ' — '),
            $guestText,
            (float) $payment->amount,
            route('customer.bookings.show', $booking, false),
            'View your booking'
        );

        self::notifyAdmin(
            "Refund Paid Out — {$ref}",
            "₱{$amount} refund for {$ref} {$adminText} Nothing further is pending on this refund.",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Check-in / Check-out ──────────────────────────────
    public static function guestCheckedIn($booking): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Guest Checked In",
            "{$guestName} has checked in to {$propertyName}. " .
            "Check-out: {$booking->check_out_date->format('M d, Y')}.",
            route('admin.bookings.show', $booking, false)
        );
    }

    public static function guestCheckedOut($booking): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Guest Checked Out",
            "{$guestName} has checked out of {$propertyName}. " .
            "Booking {$booking->booking_ref} is now complete.",
            route('admin.bookings.show', $booking, false)
        );
    }

    // ── Preset: Housekeeping (v7.11) ──────────────────────────────
    // Ang staff ay isang pinagsasaluhang account na walang notification
    // bell — nakakatanggap nila sa frontdesk sa pamamagitan ng
    // `FrontdeskBroadcast`. Ang mga ito ay para sa admin.
    public static function issueReported($report): void
    {
        $description = $report->description ? " — \"{$report->description}\"" : ".";

        self::notifyAdmin(
            "Issue Reported — {$report->category_label}",
            "{$report->source_label} reported a {$report->category_label} problem{$description} " .
            "Staff were alerted at the frontdesk.",
            route("admin.housekeeping.index", [], false)
        );
    }

    public static function housekeepingDone(string $what, string $status): void
    {
        self::notifyAdmin(
            "Housekeeping {$status}",
            "{$what} was marked {$status} at the frontdesk.",
            route("admin.housekeeping.index", ["view" => "closed"], false)
        );
    }
}
