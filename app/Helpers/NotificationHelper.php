<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

class NotificationHelper
{
    // ── Notify Admin(s) ────────────────────────────────────────────
    public static function notifyAdmin(string $title, string $message, string $type = 'in_app'): void
    {
        // Notify all admin users
        $admins = User::where('role', 'admin')->pluck('id');

        foreach ($admins as $adminId) {
            Notification::create([
                'user_id' => $adminId,
                'type'    => $type,
                'title'   => $title,
                'message' => $message,
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        }
    }

    // ── Notify a Specific Guest ────────────────────────────────────
    public static function notifyGuest(int $userId, string $title, string $message, string $type = 'in_app'): void
    {
        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
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
            "Total: ₱" . number_format($booking->total_amount, 2) . ". Status: Pending."
        );
    }

    // ── Preset: Payment Received ───────────────────────────────────
    public static function paymentReceived($booking, float $amount, string $method): void
    {
        $guestName   = $booking->user->full_name ?? 'Guest';
        $methodLabel = ucfirst(str_replace('_', ' ', $method));

        self::notifyAdmin(
            "Payment Received — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " received from {$guestName} via {$methodLabel}. " .
            "Balance due: ₱" . number_format($booking->balance_due, 2) . "."
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
            ($reason ? "Reason: {$reason}" : "No reason provided.")
        );
    }

    // ── Preset: New Guest Registered ──────────────────────────────
    public static function newGuestRegistered($user): void
    {
        self::notifyAdmin(
            "New Guest Registered",
            "{$user->full_name} ({$user->email}) just created an account."
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
            "Check-in: {$booking->check_in_date->format('M d, Y')}."
        );
    }

    // ── Preset: Refund Issued ──────────────────────────────────────
    public static function refundIssued($booking, float $amount, string $reason): void
    {
        self::notifyAdmin(
            "Refund Issued — {$booking->booking_ref}",
            "₱" . number_format($amount, 2) . " refunded for booking {$booking->booking_ref}. " .
            "Reason: {$reason}"
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
            "Check-out: {$booking->check_out_date->format('M d, Y')}."
        );
    }

    public static function guestCheckedOut($booking): void
    {
        $guestName    = $booking->user->full_name ?? 'Guest';
        $propertyName = $booking->property->property_name ?? 'Property';

        self::notifyAdmin(
            "Guest Checked Out",
            "{$guestName} has checked out of {$propertyName}. " .
            "Booking {$booking->booking_ref} is now complete."
        );
    }
}