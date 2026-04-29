<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
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

        return view('customer.booking_detail', compact('booking'));
    }

    // ── Cancel Booking ─────────────────────────────────────────────
    public function cancelBooking(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->with('error', 'This booking can no longer be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|min:5',
        ]);

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        NotificationHelper::bookingCancelled($booking->load(['user','property']), $request->cancellation_reason);

        // Free up property
        $booking->property->update(['status' => 'available']);

        StaffLog::record('guest_cancelled_booking', 'bookings', $booking->id,
            "Guest cancelled booking {$booking->booking_ref}");

        return redirect()->route('customer.bookings')
            ->with('success', "Booking {$booking->booking_ref} has been cancelled.");
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
}