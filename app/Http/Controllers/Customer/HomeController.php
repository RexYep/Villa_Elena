<?php

namespace App\Http\Controllers\Customer;

use App\Events\PropertyAvailabilityChanged;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;

class HomeController extends Controller
{
    // ── Guest Dashboard ────────────────────────────────────────────
    public function index()
    {
        $user = auth()->user();

        $upcomingBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in_date', '>=', today())
            ->with('property')
            ->orderBy('check_in_date')
            ->take(3)
            ->get();

        $recentBookings = Booking::where('user_id', $user->id)
            ->with('property')
            ->latest()
            ->take(5)
            ->get();

        $stats = [
            'total'     => Booking::where('user_id', $user->id)->count(),
            'upcoming'  => Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'pending'])
                ->where('check_in_date', '>=', today())->count(),
            'completed' => Booking::where('user_id', $user->id)
                ->where('status', 'checked_out')->count(),
            'total_spent' => Booking::where('user_id', $user->id)
                ->sum('amount_paid'),
        ];

        $unreadNotifications = Notification::where('user_id', $user->id)
            ->where('is_read', 0)
            ->latest()
            ->take(5)
            ->get();

        return view('customer.home', compact(
            'upcomingBookings', 'recentBookings', 'stats', 'unreadNotifications'
        ));
    }

    // ── All My Bookings ────────────────────────────────────────────
    public function bookings(Request $request)
    {
        $query = Booking::where('user_id', auth()->id())
            ->with('property')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(10)->withQueryString();

        return view('customer.bookings', compact('bookings'));
    }

    // ── Single Booking Detail ──────────────────────────────────────
    public function bookingDetail(Booking $booking)
    {
        // Ensure the booking belongs to this user
        abort_if($booking->user_id !== auth()->id(), 403);

        $booking->load(['property.images', 'payments', 'extras']);

        // Ipinapasa rin ang eligible refund preview (kung sakaling
        // i-cancel ng guest ang booking na ito ngayon) para maipakita
        // sa cancellation confirmation UI bago pa man sila mag-submit.
        $refundPreviewPct    = $booking->isCancellable() ? $booking->calculateRefundPercentage() : null;
        $refundPreviewAmount = $booking->isCancellable() ? $booking->calculateRefundAmount() : null;

        return view('customer.booking_detail', compact('booking', 'refundPreviewPct', 'refundPreviewAmount'));
    }

    // ── Cancel Booking ─────────────────────────────────────────────
    public function cancelBooking(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        if (!$booking->isCancellable()) {
            return back()->with('error', 'This booking can no longer be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|min:5',
        ]);

        // Kinukuha ang refund eligibility BAGO baguhin ang status —
        // batay ito sa tiered cancellation policy sa Booking model
        // (24-hour grace period, 7+/3-6/<3 araw bago ang check-in).
        $refundPercentage = $booking->calculateRefundPercentage();
        $refundAmount     = $booking->calculateRefundAmount();

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->cancellation_reason,
            // Cancelled na ang booking — wala nang balance na dapat
            // pang bayaran kahit anong tier ang na-apply.
            'balance_due'         => 0,
        ]);

        // Free up property
        $booking->property->update(['status' => 'available']);

        event(new PropertyAvailabilityChanged(
            $booking->property_id,
            'freed',
            $booking->check_in_date->format('Y-m-d'),
            $booking->check_out_date->format('Y-m-d'),
            bookingId: $booking->id,
        ));

        // Kung may eligible refund amount, gumawa ng refund Payment
        // record — pareho ang pattern na ginagamit ng
        // Admin\BookingController para consistent ang audit trail kahit
        // saan pa galing ang cancellation.
        if ($refundAmount > 0) {
            $originalMethod = optional(
                $booking->payments()->where('payment_type', '!=', 'refund')->latest()->first()
            )->payment_method ?? 'cash';

            Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $refundAmount,
                'payment_method' => $originalMethod,
                'payment_type'   => 'refund',
                'status'         => 'success',
                'payment_date'   => today(),
                'notes'          => "Auto-computed refund ({$refundPercentage}% policy) — guest self-cancelled booking.",
            ]);

            // I-recompute mula sa Payment records mismo (hindi direktang
            // pagbawas sa dating amount_paid) — parehong pattern ng
            // Admin\BookingController::recordPayment() para hindi
            // mag-drift ang totals kung sakaling may kasunod pang
            // manual adjustment.
            $totalPaid     = $booking->payments()->where('payment_type', '!=', 'refund')->sum('amount');
            $totalRefunded = $booking->payments()->where('payment_type', 'refund')->sum('amount');
            $amountPaid    = max(0, $totalPaid - $totalRefunded);

            $booking->update([
                'amount_paid'    => $amountPaid,
                'payment_status' => $amountPaid > 0 ? 'partial' : 'refunded',
            ]);

            NotificationHelper::refundIssued(
                $booking->fresh(),
                $refundAmount,
                "Self-cancelled booking ({$refundPercentage}% refund policy)"
            );
        }

        NotificationHelper::bookingCancelled($booking->load(['user','property']), $request->cancellation_reason);

        StaffLog::record('guest_cancelled_booking', 'bookings', $booking->id,
            "Guest cancelled booking {$booking->booking_ref}. Refund eligibility: {$refundPercentage}% (₱" . number_format($refundAmount, 2) . ").");

        $message = "Booking {$booking->booking_ref} has been cancelled.";
        if ($refundAmount > 0) {
            $message .= " ₱" . number_format($refundAmount, 2) . " ({$refundPercentage}% ng iyong nabayaran) ay irerefund sa loob ng ilang araw.";
        } else {
            $message .= " Based on our cancellation policy, this is no longer eligible for a refund.";
        }

        return redirect()->route('customer.bookings')->with('success', $message);
    }

    // ── Notifications ──────────────────────────────────────────────
    public function notifications()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        // Mark all as read
       Notification::where('user_id', auth()->id())
        ->where('is_read', 0)
        ->update(['is_read' => 1]);

        return view('customer.notifications', compact('notifications'));
    }

    // ── Open A Notification: Mark Read + Go To Its Destination ─────
    public function openNotification(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);

        $notification->markAsRead();

        return redirect($notification->link ?? route('customer.notifications'));
    }
}