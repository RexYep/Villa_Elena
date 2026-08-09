<?php

namespace App\Helpers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

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

        event(new NotificationCreated($notification));
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
        $methodLabel = ucfirst(str_replace('_', ' ', $method));

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
        $methodLabel = ucfirst(str_replace('_', ' ', $method));

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
        self::notifyAdmin(
            "Refund Issued — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " refunded for booking {$booking->booking_ref}. " .
            "Reason: {$reason}",
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
