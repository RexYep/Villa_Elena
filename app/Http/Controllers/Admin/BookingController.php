<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Models\Payment;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;
use App\Events\BookingCreated;

class BookingController extends Controller
{
    // ── List All Bookings ──────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'property'])->latest();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_ref', 'like', "%$search%")
                  ->orWhereHas('user', fn($u) => $u->where('full_name', 'like', "%$search%")
                                                    ->orWhere('email', 'like', "%$search%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('check_in_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('check_in_date', '<=', $request->date_to);
        }

        $bookings   = $query->paginate(15)->withQueryString();
        $properties = Property::orderBy('property_name')->get();

        $stats = [
            'total'       => Booking::count(),
            'pending'     => Booking::where('status', 'pending')->count(),
            'confirmed'   => Booking::where('status', 'confirmed')->count(),
            'checked_in'  => Booking::where('status', 'checked_in')->count(),
            'cancelled'   => Booking::where('status', 'cancelled')->count(),
            'today_checkins'  => Booking::whereDate('check_in_date', today())->whereIn('status', ['confirmed'])->count(),
            'today_checkouts' => Booking::whereDate('check_out_date', today())->where('status', 'checked_in')->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'properties', 'stats'));
    }

    // ── Show Create Form ───────────────────────────────────────────
    public function create()
    {
        $properties = Property::where('status', 'available')->orderBy('property_name')->get();
        $customers  = User::where('role', 'customer')->orderBy('full_name')->get();
        return view('admin.bookings.create', compact('properties', 'customers'));
    }

    // ── Store New Booking ──────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'property_id'      => 'required|exists:properties,id',
            'check_in_date'    => 'required|date|after_or_equal:today',
            'check_out_date'   => 'required|date|after:check_in_date',
            'num_guests'       => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'source'           => 'required|in:online,walk_in,phone,partner',
        ]);

        $property = Property::findOrFail($request->property_id);

        // Check availability
        $conflict = Booking::where('property_id', $request->property_id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('check_in_date',  [$request->check_in_date, $request->check_out_date])
                  ->orWhereBetween('check_out_date', [$request->check_in_date, $request->check_out_date])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('check_in_date',  '<=', $request->check_in_date)
                         ->where('check_out_date', '>=', $request->check_out_date);
                  });
            })->exists();

        if ($conflict) {
            return back()->withErrors(['check_in_date' => 'This property is already booked for the selected dates.'])->withInput();
        }

        // Calculate pricing
        $checkIn  = \Carbon\Carbon::parse($request->check_in_date);
        $checkOut = \Carbon\Carbon::parse($request->check_out_date);
        $nights   = $checkIn->diffInDays($checkOut);

        $baseAmount = 0;
        for ($i = 0; $i < $nights; $i++) {
            $date = $checkIn->copy()->addDays($i);
            $baseAmount += $property->getPriceForDate($date);
        }

        $booking = Booking::create([
            'user_id'          => $request->user_id,
            'property_id'      => $request->property_id,
            'check_in_date'    => $request->check_in_date,
            'check_out_date'   => $request->check_out_date,
            'num_nights'       => $nights,
            'num_guests'       => $request->num_guests,
            'base_amount'      => $baseAmount,
            'extras_amount'    => 0,
            'discount_amount'  => 0,
            'total_amount'     => $baseAmount,
            'amount_paid'      => 0,
            'balance_due'      => $baseAmount,
            'status'           => 'confirmed',
            'payment_status'   => 'unpaid',
            'source'           => $request->source,
            'special_requests' => $request->special_requests,
        ]);

        event(new BookingCreated($booking));

        NotificationHelper::notifyGuest(
    $booking->user_id,
    'Booking Confirmed!',
    "Your booking {$booking->booking_ref} has been created by our team."
);
 

        StaffLog::record('created_booking', 'bookings', $booking->id,
            "Admin created booking {$booking->booking_ref}");

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', "Booking {$booking->booking_ref} created successfully.");
    }

    // ── Show Booking Detail ────────────────────────────────────────
    public function show(Booking $booking)
    {
        $booking->load(['user', 'property.images', 'payments', 'extras', 'review', 'housekeepingTasks']);
        return view('admin.bookings.show', compact('booking'));
    }

    // ── Show Edit Form ─────────────────────────────────────────────
    public function edit(Booking $booking)
    {
        $properties = Property::orderBy('property_name')->get();
        $customers  = User::where('role', 'customer')->orderBy('full_name')->get();
        return view('admin.bookings.edit', compact('booking', 'properties', 'customers'));
    }

    // ── Update Booking ─────────────────────────────────────────────
    public function update(Request $request, Booking $booking)
    {
        $request->validate([
            'special_requests' => 'nullable|string',
            'num_guests'       => 'required|integer|min:1',
        ]);

        $booking->update([
            'num_guests'       => $request->num_guests,
            'special_requests' => $request->special_requests,
        ]);

        StaffLog::record('updated_booking', 'bookings', $booking->id,
            "Updated booking {$booking->booking_ref}");

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking updated successfully.');
    }

    // ── Delete Booking ─────────────────────────────────────────────
    public function destroy(Booking $booking)
    {
        $ref = $booking->booking_ref;
        $booking->delete();

        StaffLog::record('deleted_booking', 'bookings', null, "Deleted booking {$ref}");

        return redirect()->route('admin.bookings.index')
            ->with('success', "Booking {$ref} has been deleted.");
    }

    // ── Update Status ──────────────────────────────────────────────
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,checked_in,checked_out,cancelled,no_show',
            'cancellation_reason' => 'nullable|string|required_if:status,cancelled',
        ]);

        $oldStatus = $booking->status;
        $newStatus = $request->status;

        $updates = ['status' => $newStatus];

        if ($newStatus === 'cancelled') {
            $updates['cancelled_at']          = now();
            $updates['cancellation_reason']   = $request->cancellation_reason;
            // Free up the property
            $booking->property->update(['status' => 'available']);
        }

        if ($newStatus === 'checked_in') {
            $booking->property->update(['status' => 'occupied']);
            // Auto-create checkout housekeeping task
            HousekeepingTask::create([
                'property_id' => $booking->property_id,
                'booking_id'  => $booking->id,
                'task_type'   => 'checkout_clean',
                'due_date'    => $booking->check_out_date,
                'status'      => 'pending',
                'notes'       => "Post-checkout cleaning for booking {$booking->booking_ref}",
            ]);
        }

        if ($newStatus === 'checked_out') {
            $booking->property->update(['status' => 'available']);
        }

        $booking->update($updates);

        // Notify guest
        Notification::create([
            'user_id' => $booking->user_id,
            'type' => 'in_app',
            'title'   => 'Booking Status Updated',
            'message' => "Your booking {$booking->booking_ref} status has been updated to: " . ucfirst(str_replace('_', ' ', $newStatus)),
            'sent_at' => now(),
            'status'  => 'sent',
        ]);

        StaffLog::record('updated_booking_status', 'bookings', $booking->id,
            "Status changed from {$oldStatus} to {$newStatus} for {$booking->booking_ref}");

        return back()->with('success', "Booking status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".");
    }

    // ── Record Payment ─────────────────────────────────────────────
    public function recordPayment(Request $request, Booking $booking)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:gcash,paymaya,card,cash,bank_transfer',
            'payment_type'   => 'required|in:deposit,full_payment,partial,refund',
            'notes'          => 'nullable|string',
        ]);

        Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type'   => $request->payment_type,
            'status'         => 'success',
            'processed_by'   => auth()->id(),
            'notes'          => $request->notes,
            'payment_date'   => now(),
        ]);

        

        // Recalculate payment totals
        $totalPaid = $booking->payments()->where('status', 'success')
            ->where('payment_type', '!=', 'refund')->sum('amount');
        $totalRefunded = $booking->payments()->where('status', 'success')
            ->where('payment_type', 'refund')->sum('amount');
        $amountPaid = $totalPaid - $totalRefunded;
        $balanceDue = $booking->total_amount - $amountPaid;

        $paymentStatus = 'unpaid';
        if ($amountPaid >= $booking->total_amount) {
            $paymentStatus = 'paid';
        } elseif ($amountPaid > 0) {
            $paymentStatus = 'partial';
        }

        $booking->update([
            'amount_paid'    => $amountPaid,
            'balance_due'    => max(0, $balanceDue),
            'payment_status' => $paymentStatus,
        ]);

        StaffLog::record('recorded_payment', 'payments', $booking->id,
            "Recorded ₱{$request->amount} payment for {$booking->booking_ref}");

        return back()->with('success', "Payment of ₱" . number_format($request->amount, 2) . " recorded successfully.");
    }
}