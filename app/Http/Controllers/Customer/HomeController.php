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

        return view('customer.home', compact(
            'upcomingBookings', 'recentBookings', 'stats', 'unreadNotifications'
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
        $booking->load(['property.images', 'payments.refundTransfers', 'extras']);

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
        abort_if($booking->user_id !== Auth::id(), 403);

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
            'cancelled_by'        => 'guest',
            // Cancelled na ang booking — wala nang balance na dapat
            // pang bayaran kahit anong tier ang na-apply.
            'balance_due'         => 0,
        ]);

        // Free up property
        $booking->property->update(['status' => 'available']);

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

        // Kung may eligible refund amount, gumawa ng refund Payment
        // record — pareho ang pattern na ginagamit ng
        // Admin\BookingController para consistent ang audit trail kahit
        // saan pa galing ang cancellation.
        // Hawak dahil kailangan ito ng notification sa ibaba para
        // maituro ang guest sa form na nagtatanong kung saan ipapadala
        // ang pera. Nananatiling null kung walang refund — at tama
        // lang iyon: walang itatanong kung walang ipadadala.
        $refundPayment = null;

        if ($refundAmount > 0) {
            $originalMethod = optional(
                $booking->payments()->where('payment_type', '!=', 'refund')->latest()->first()
            )->payment_method ?? 'cash';

            $refundPayment = Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $refundAmount,
                'payment_method' => $originalMethod,
                'payment_type'   => 'refund',
                // 'pending' — inaprubahan na ang refund, pero manu-mano
                // pang ipapadala ng admin ang pera.
                'status'         => 'pending',
                'payment_date'   => today(),
                'notes'          => "Auto-computed refund ({$refundPercentage}% policy) — guest self-cancelled booking.",
            ]);

            $booking->recalculateFinancials();

            NotificationHelper::refundIssued(
                $booking->fresh(),
                $refundAmount,
                "Self-cancelled booking ({$refundPercentage}% refund policy)"
            );
        }

        NotificationHelper::bookingCancelled($booking->load(['user','property']), $request->cancellation_reason);

        // Ang guest mismo ang nag-cancel, pero siya lang ang walang
        // natatanggap na notification dito dati — puro notifyAdmin() ang
        // dalawang tawag sa itaas. Flash message lang ang meron siya, at
        // nawawala iyon pagkatapos ng isang page load, kaya walang
        // matitirang patunay kung magkano (at kailan) ang refund niya.
        // Iisa lang ang notification na ito para sa cancellation AT sa
        // refund — nakapaloob na sa preset ang refund line, kaya hindi
        // na kailangan ng hiwalay na refundApprovedForGuest() dito.
        NotificationHelper::bookingCancelledForGuest(
            $booking,
            $refundAmount,
            $refundAmount > 0 ? $refundPercentage : null,
            $refundPayment
        );

        StaffLog::record('guest_cancelled_booking', 'bookings', $booking->id,
            "Guest cancelled booking {$booking->booking_ref}. Refund eligibility: {$refundPercentage}% (₱" . number_format($refundAmount, 2) . ").");

        $message = "Booking {$booking->booking_ref} has been cancelled.";
        if ($refundAmount > 0) {
            $message .= " ₱" . number_format($refundAmount, 2) . " ({$refundPercentage}% ng iyong nabayaran) ay irerefund sa loob ng ilang araw.";
        } else {
            $message .= " Based on our cancellation policy, this is no longer eligible for a refund.";
        }

        // Kung may irerefund, dalhin agad siya sa form na nagtatanong
        // kung saan ipapadala. Nandito na siya at hinihintay ang pera
        // niya — dito ang pinakamataas na tsansang masagot ito.
        //
        // SINASADYANG redirect ito, hindi mga field sa loob mismo ng
        // cancel form. Ang paglalagay ng tatlong required na field sa
        // cancellation ay nangangahulugang kayang HARANGAN ng isang
        // validation error ang isang cancellation — hindi iyon
        // katanggap-tanggap. Natatapos muna ang pag-cancel; saka lang
        // tayo nagtatanong. Kung aalis siya, naroon pa rin ang link sa
        // notification.
        if ($refundPayment && $refundPayment->needsRefundDestination()) {
            return redirect()
                ->route('customer.refunds.destination', $refundPayment)
                ->with('success', $message . ' Please tell us where to send it.');
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