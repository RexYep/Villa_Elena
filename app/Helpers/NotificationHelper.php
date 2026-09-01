<?php

namespace App\Helpers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    // ── Create + Broadcast a Notification ──────────────────────────
    protected static function create(int $userId, string $title, string $message, ?string $link, string $type): void
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
    public static function notifyGuest(int $userId, string $title, string $message, ?string $link = null, string $type = 'in_app'): void
    {
        self::create($userId, $title, $message, $link, $type);
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
    public static function refundIssued($booking, float $amount, string $reason): void
    {
        // Sinasadyang "needs to be sent" — hindi "refunded". Walang refund
        // API ang sistema, kaya ang refund na ito ay naitala pa lang;
        // manu-manong ipapadala ng admin ang pera bago ito matapos.
        // Dating "refunded" ang nakasulat dito, kaya mukhang tapos na ang
        // isang bagay na hindi pa naman talaga nagagawa.
        self::notifyAdmin(
            "Refund To Send — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " needs to be sent to the guest for booking {$booking->booking_ref}. " .
            "Reason: {$reason}. Mark it as paid out on the Payments page once the money has actually been sent.",
            route('admin.payments.index', ['status' => 'awaiting_payout'], false)
        );
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
    public static function bookingCancelledForGuest($booking, float $refundAmount = 0, ?int $refundPercentage = null, $refund = null): void
    {
        $message = "Your booking {$booking->booking_ref} has been cancelled.";

        if ($refundAmount > 0) {
            $message .= " A refund of ₱" . number_format($refundAmount, 2)
                . ($refundPercentage !== null ? " ({$refundPercentage}% of what you paid)" : '')
                . " has been approved. The money hasn't been sent yet — we'll notify you again once it's on its way.";
        } else {
            $message .= " Based on our cancellation policy, this booking is no longer eligible for a refund.";
        }

        [$message, $link] = self::withRefundDestinationPrompt($message, $booking, $refund);

        self::notifyGuest(
            $booking->user_id,
            "Booking Cancelled — {$booking->booking_ref}",
            $message,
            $link
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

    // ── Preset: Refund Approved — hindi pa naipapadala (guest) ────
    // Sinasadyang "approved", hindi "processed"/"refunded". Ang refund
    // row ay ginagawa bilang 'pending' — wala pang perang lumalabas sa
    // puntong ito. Dating sinasabi ng notification na tapos na ito,
    // kaya hinahanap ng guest sa GCash niya ang perang hindi pa naman
    // talaga ipinapadala.
    public static function refundApprovedForGuest($booking, float $amount, string $reason, $refund = null): void
    {
        $message = "A refund of ₱" . number_format($amount, 2) . " has been approved for booking {$booking->booking_ref}. "
            . "Reason: {$reason}. The money hasn't been sent yet — we'll notify you again once it's on its way.";

        [$message, $link] = self::withRefundDestinationPrompt($message, $booking, $refund);

        self::notifyGuest(
            $booking->user_id,
            "Refund Approved — {$booking->booking_ref}",
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

    public static function refundPaidOut($payment): void
    {
        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $amount = number_format($payment->amount, 2);
        $method = $payment->method_label;

        self::notifyGuest(
            $booking->user_id,
            "Refund Sent — {$booking->booking_ref}",
            "Your refund of ₱{$amount} for booking {$booking->booking_ref} has been sent via {$method}. " .
            "Please allow a few banking days for it to show up in your account.",
            route('customer.bookings.show', $booking, false)
        );

        self::notifyAdmin(
            "Refund Paid Out — {$booking->booking_ref}",
            "₱{$amount} refund for {$booking->booking_ref} was marked as sent via {$method}. " .
            "Nothing further is pending on this refund.",
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
}
