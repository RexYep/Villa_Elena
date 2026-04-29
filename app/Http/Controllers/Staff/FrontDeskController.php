<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\StaffLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;


class FrontdeskController extends Controller
{
    // ── Main Frontdesk View ────────────────────────────────────────
    public function index()
    {
        $today = today();

        $checkIns = Booking::where('check_in_date', $today)
            ->where('status', 'confirmed')
            ->with(['property', 'user'])
            ->orderBy('created_at')
            ->get();

        $checkOuts = Booking::where('check_out_date', $today)
            ->where('status', 'checked_in')
            ->with(['property', 'user'])
            ->orderBy('check_out_date')
            ->get();

        $currentGuests = Booking::where('status', 'checked_in')
            ->with(['property', 'user'])
            ->orderBy('check_out_date')
            ->get();

        $pendingBookings = Booking::where('status', 'pending')
            ->with(['property', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $pendingTasks = HousekeepingTask::where('status', 'pending')
            ->with('property')
            ->orderBy('scheduled_date')
            ->get();

        $inProgressTasks = HousekeepingTask::where('status', 'in_progress')
            ->with('property')
            ->get();

        $properties = Property::with(['currentBooking.user'])
            ->orderBy('property_name')
            ->get();

        // Available properties for walk-in form
        $availableProperties = Property::where('status', 'available')
            ->orderBy('property_name')
            ->get();

        $stats = [
            'check_ins_today'  => $checkIns->count(),
            'check_outs_today' => $checkOuts->count(),
            'occupied'         => Property::where('status', 'occupied')->count(),
            'available'        => Property::where('status', 'available')->count(),
            'pending_tasks'    => $pendingTasks->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
        ];

        return view('staff.frontdesk', compact(
            'checkIns', 'checkOuts', 'currentGuests', 'pendingBookings',
            'pendingTasks', 'inProgressTasks', 'properties',
            'availableProperties', 'stats'
        ));
    }

    // ── Walk-in Booking Form Page ──────────────────────────────────
    public function walkinForm()
    {
        $availableProperties = Property::where('status', 'available')
            ->orderBy('property_name')
            ->get();

        $customers = User::where('role', 'customer')
            ->orderBy('full_name')
            ->get();

        return view('staff.walkin', compact('availableProperties', 'customers'));
    }

    // ── Store Walk-in Booking ──────────────────────────────────────
    public function storeWalkin(Request $request)
    {
        $request->validate([
            'guest_type'       => 'required|in:existing,new',
            'user_id'          => 'required_if:guest_type,existing|nullable|exists:users,id',
            'full_name'        => 'required_if:guest_type,new|nullable|string|max:255',
            'email'            => 'required_if:guest_type,new|nullable|email|unique:users,email',
            'phone'            => 'nullable|string|max:20',
            'property_id'      => 'required|exists:properties,id',
            'check_in_date'    => 'required|date|after_or_equal:today',
            'check_out_date'   => 'required|date|after:check_in_date',
            'num_guests'       => 'required|integer|min:1',
            'special_requests' => 'nullable|string|max:500',
            'payment_amount'   => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:cash,gcash,bank_transfer,credit_card',
            'payment_type'     => 'nullable|in:deposit,full_payment,partial',
        ]);

        // Create new guest if needed
        if ($request->guest_type === 'new') {
            $user = User::create([
                'full_name' => $request->full_name,
                'email'     => $request->email,
                'phone'     => $request->phone,
                'password'  => bcrypt('VillaElena@2026'),
                'role'      => 'customer',
                'status'    => 1,
            ]);
        } else {
            $user = User::findOrFail($request->user_id);
        }

        $property  = Property::findOrFail($request->property_id);
        $checkin   = Carbon::parse($request->check_in_date);
        $checkout  = Carbon::parse($request->check_out_date);
        $nights    = $checkin->diffInDays($checkout);

        // Calculate total
        $baseAmount = 0;
        for ($i = 0; $i < $nights; $i++) {
            $baseAmount += $property->getPriceForDate($checkin->copy()->addDays($i));
        }

        $amountPaid = (float) ($request->payment_amount ?? 0);
        $balanceDue = $baseAmount - $amountPaid;

        $paymentStatus = 'unpaid';
        if ($amountPaid >= $baseAmount) $paymentStatus = 'paid';
        elseif ($amountPaid > 0)        $paymentStatus = 'partial';

        // Create booking — walk-ins are confirmed immediately
        $booking = Booking::create([
            'user_id'          => $user->id,
            'property_id'      => $property->id,
            'check_in_date'    => $checkin,
            'check_out_date'   => $checkout,
            'num_nights'       => $nights,
            'num_guests'       => $request->num_guests,
            'base_amount'      => $baseAmount,
            'extras_amount'    => 0,
            'discount_amount'  => 0,
            'total_amount'     => $baseAmount,
            'amount_paid'      => $amountPaid,
            'balance_due'      => max(0, $balanceDue),
            'status'           => 'confirmed',
            'payment_status'   => $paymentStatus,
            'source'           => 'walk_in',
            'special_requests' => $request->special_requests,
        ]);

        NotificationHelper::walkInBooking($booking->load(['user','property']), auth()->user()->full_name);
 

        // Record payment if any amount was paid
        if ($amountPaid > 0 && $request->payment_method) {
            Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $amountPaid,
                'payment_method' => $request->payment_method,
                'payment_type'   => $request->payment_type ?? 'deposit',
                'payment_date'   => today(),
                'received_by'    => auth()->id(),
                'notes'          => 'Walk-in payment recorded by ' . auth()->user()->full_name,
            ]);
        }

        // Notify guest
        Notification::create([
            'user_id' => $user->id,
            'type'    => 'in_app',
            'title'   => 'Booking Confirmed!',
            'message' => "Your walk-in booking {$booking->booking_ref} for {$property->property_name} has been confirmed. Check-in: {$checkin->format('M d, Y')}.",
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('walkin_booking', 'bookings', $booking->id,
            "Walk-in booking {$booking->booking_ref} created by " . auth()->user()->full_name . " for guest {$user->full_name}");

        return redirect()->route('staff.frontdesk')
            ->with('success', "✅ Walk-in booking {$booking->booking_ref} created for {$user->full_name}. " .
                ($amountPaid > 0 ? "Payment of ₱" . number_format($amountPaid, 2) . " recorded." : ""));
    }

    // ── Record Payment for Existing Booking ───────────────────────
    public function recordPayment(Request $request, Booking $booking)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,gcash,bank_transfer,credit_card',
            'payment_type'   => 'required|in:deposit,full_payment,partial,balance',
            'notes'          => 'nullable|string|max:300',
        ]);

        Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type'   => $request->payment_type,
            'payment_date'   => today(),
            'received_by'    => auth()->id(),
            'notes'          => $request->notes ?? 'Recorded by ' . auth()->user()->full_name,
        ]);

        // Recalculate
        $totalPaid  = Payment::where('booking_id', $booking->id)
            ->where('payment_type', '!=', 'refund')
            ->sum('amount');
        $totalPaid -= Payment::where('booking_id', $booking->id)
            ->where('payment_type', 'refund')
            ->sum('amount');

        $balanceDue    = max(0, $booking->total_amount - $totalPaid);
        $paymentStatus = 'unpaid';
        if ($totalPaid >= $booking->total_amount)  $paymentStatus = 'paid';
        elseif ($totalPaid > 0)                    $paymentStatus = 'partial';

        $booking->update([
            'amount_paid'    => $totalPaid,
            'balance_due'    => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);

        StaffLog::record('payment_recorded', 'bookings', $booking->id,
            "Payment ₱{$request->amount} recorded for booking {$booking->booking_ref} by " . auth()->user()->full_name);

        return back()->with('success', "✅ Payment of ₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref}.");
    }

    // ── Check In ──────────────────────────────────────────────────
    public function checkIn(Booking $booking)
    {
        if ($booking->status !== 'confirmed') {
            return back()->with('error', 'Booking must be confirmed before check-in.');
        }

        $booking->update(['status' => 'checked_in', 'actual_check_in' => now()]);
        $booking->property->update(['status' => 'occupied']);
        NotificationHelper::guestCheckedIn($booking->load(['user','property']));

        HousekeepingTask::create([
            'property_id'    => $booking->property_id,
            'booking_id'     => $booking->id,
            'task_type'      => 'checkout_clean',
            'scheduled_date' => $booking->check_out_date,
            'status'         => 'pending',
            'notes'          => "Post-checkout cleaning for booking {$booking->booking_ref}",
        ]);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Welcome to Villa Elena!',
            'message' => "You have successfully checked in to {$booking->property->property_name}. Enjoy your stay! Check-out: {$booking->check_out_date->format('F d, Y')}.",
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('check_in', 'bookings', $booking->id,
            "Checked in {$booking->user->full_name} for {$booking->booking_ref}");

        return back()->with('success', "✅ {$booking->user->full_name} checked in to {$booking->property->property_name}.");
    }

    // ── Check Out ─────────────────────────────────────────────────
    public function checkOut(Booking $booking)
    {
        if ($booking->status !== 'checked_in') {
            return back()->with('error', 'Guest must be checked in first.');
        }

        $booking->update(['status' => 'checked_out', 'actual_check_out' => now()]);
        $booking->property->update(['status' => 'available']);
        NotificationHelper::guestCheckedOut($booking->load(['user','property']));

        HousekeepingTask::where('booking_id', $booking->id)
            ->where('task_type', 'checkout_clean')
            ->update(['status' => 'in_progress']);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Check-out Complete',
            'message' => "Thank you for staying at Villa Elena! Booking {$booking->booking_ref} is now complete. We hope to see you again!",
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('check_out', 'bookings', $booking->id,
            "Checked out {$booking->user->full_name} for {$booking->booking_ref}");

        return back()->with('success', "✅ {$booking->user->full_name} checked out. Housekeeping task activated.");
    }

    // ── Housekeeping Tasks ─────────────────────────────────────────
    public function startTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'in_progress']);
        StaffLog::record('task_started', 'housekeeping_tasks', $task->id,
            "Started task for {$task->property->property_name}");
        return back()->with('success', "Task started.");
    }

    public function completeTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        StaffLog::record('task_completed', 'housekeeping_tasks', $task->id,
            "Completed task for {$task->property->property_name}");
        return back()->with('success', "✅ Housekeeping task marked as complete.");
    }
}