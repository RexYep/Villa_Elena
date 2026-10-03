<?php

namespace App\Http\Controllers\Customer;

use App\Events\PropertyAvailabilityChanged;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Property;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    // ── Guest Dashboard ────────────────────────────────────────────
    public function index()
    {
        $user = Auth::user();

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

        // Naka-check-in ngayon → shortcut sa "Report an issue" (v7.11)
        $currentStay = Booking::where('user_id', $user->id)
            ->where('status', 'checked_in')
            ->with(['property', 'issueReports' => fn ($q) => $q->latest()])
            ->first();

        return view('customer.home', compact(
            'upcomingBookings', 'recentBookings', 'stats', 'unreadNotifications', 'currentStay'
        ));
    }

    // ── All My Bookings ────────────────────────────────────────────
    public function bookings(Request $request)
    {
        $query = Booking::where('user_id', Auth::id())
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
        abort_if($booking->user_id !== Auth::id(), 403);

        // Kasama ang `payments.refundTransfers` para hindi maging isang
        // query kada refund ang `refundStage()` sa badge ng pahina.
        $booking->load(['property.images', 'payments.refundTransfers', 'extras', 'issueReports' => fn ($q) => $q->latest()]);

        return view('customer.booking_detail', compact('booking'));
    }

    // ── Cancel Booking ─────────────────────────────────────────────
    public function cancelBooking(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        if (!$booking->isCancellable()) {
            return back()->with('error', 'This booking can no longer be cancelled.');
        }

        // Ang pag-cancel ng bayad nang booking ay hindi na maibabalik AT
        // may halaga na ngayon: mawawala sa guest ang naibayad niya
        // (Booking::CANCELLATION_POLICY). Kaya kailangan niyang sabihing
        // naiintindihan niya iyon. Ang server ang hadlang, hindi ang
        // `required` sa checkbox — kayang laktawan iyon ng sinumang
        // direktang nagpapadala ng request. Walang hinihingi sa booking
        // na wala pang bayad: walang mawawala roon.
        $forfeits = (float) $booking->amount_paid;

        $request->validate([
            'cancellation_reason' => 'required|string|min:5',
            'accept_no_refund'    => $forfeits > 0 ? 'accepted' : 'nullable',
        ], [
            'accept_no_refund.accepted' => 'Please confirm that you understand the ₱'
                . number_format($forfeits, 2) . ' you have paid will not be refunded.',
        ]);

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_by'        => 'guest',
            // Cancelled na ang booking — wala nang balance na dapat
            // pang bayaran kahit anong tier ang na-apply.
            'balance_due'         => 0,
        ]);

        // NOT "free up the property" — that write is removed on purpose.
        //
        // `properties.status` tracks OCCUPANCY, and it is moved by the pair
        // that actually changes it: check-in sets `occupied`, check-out sets
        // `available` (FrontDeskController, AutoCheckInOutBookings). A guest
        // can only cancel a `pending` or `confirmed` booking — never a
        // `checked_in` one (Booking::isCancellable()) — so a cancellation here
        // can never be the thing that freed the villa. It had nothing to
        // release.
        //
        // What it DID do was let a guest overwrite a status an admin had set.
        // Cancel an old booking while the villa sits at `maintenance` and it
        // silently flipped back to `available`, with the admin's setting gone
        // and nothing in the log to say why. Real availability is derived from
        // the bookings themselves via Booking::hasConflict(), which never
        // consulted this column, so nothing downstream needs the write.

        // Realtime broadcast lang ito — hindi dapat maka-block sa
        // cancellation request (naka-commit na ito sa puntong ito) kung
        // mag-fail ang Pusher.
        try {
            event(new PropertyAvailabilityChanged(
                $booking->property_id,
                'freed',
                $booking->check_in_date->format('Y-m-d'),
                $booking->check_out_date->format('Y-m-d'),
                bookingId: $booking->id,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast PropertyAvailabilityChanged (customer cancel): ' . $e->getMessage());
        }

        // WALANG refund na ginagawa rito (v7.52). Dating may tiered na
        // kalkulasyon at isang 'pending' na refund Payment sa puntong
        // ito; ang patakaran ng may-ari ay non-refundable ang lahat ng
        // bayad kapag ang guest ang nag-cancel. Nananatili sa booking
        // ang `amount_paid` — pera iyon ng resort, at iyon din ang
        // makikita sa mga ulat.

        NotificationHelper::bookingCancelled($booking->load(['user','property']), $request->cancellation_reason);

        // Ang guest mismo ang nag-cancel, kaya siya rin ang dapat may
        // matitirang tala nito. Nawawala ang flash message pagkatapos ng
        // isang page load; ang notification ang nagsasabi kung magkano
        // ang hindi na maibabalik, sa parehong pananalitang pinayagan
        // niya bago mag-submit.
        NotificationHelper::bookingCancelledForGuest($booking, $forfeits);

        StaffLog::record('guest_cancelled_booking', 'bookings', $booking->id,
            "Guest cancelled booking {$booking->booking_ref}. No refund (non-refundable policy); ₱"
            . number_format($forfeits, 2) . ' paid is kept.');

        $message = "Booking {$booking->booking_ref} has been cancelled.";
        if ($forfeits > 0) {
            $message .= ' As you confirmed, the ₱' . number_format($forfeits, 2) . ' you paid is non-refundable.';
        }

        return redirect()->route('customer.bookings')->with('success', $message);
    }

    // ── Notifications ──────────────────────────────────────────────
    public function notifications()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        // Mark all as read
       Notification::where('user_id', Auth::id())
        ->where('is_read', 0)
        ->update(['is_read' => 1]);

        return view('customer.notifications', compact('notifications'));
    }

    // ── Open A Notification: Mark Read + Go To Its Destination ─────
    public function openNotification(Notification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->markAsRead();

        return redirect($notification->link ?? route('customer.notifications'));
    }
}