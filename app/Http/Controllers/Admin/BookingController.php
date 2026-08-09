<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingExtra;
use App\Models\Property;
use App\Models\User;
use App\Models\Payment;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;
use App\Events\BookingCreated;
use App\Events\BookingUpdated;
use App\Events\PropertyAvailabilityChanged;

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

        $bookings = $query->paginate(15)->withQueryString();

        $stats = [
            'total'       => Booking::count(),
            'pending'     => Booking::where('status', 'pending')->count(),
            'confirmed'   => Booking::where('status', 'confirmed')->count(),
            'checked_in'  => Booking::where('status', 'checked_in')->count(),
            'cancelled'   => Booking::where('status', 'cancelled')->count(),
            'today_checkins'  => Booking::whereDate('check_in_date', today())->whereIn('status', ['confirmed'])->count(),
            'today_checkouts' => Booking::whereDate('check_out_date', today())->where('status', 'checked_in')->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'stats'));
    }

    // ── Show Create Form ───────────────────────────────────────────
    public function create()
    {
        // Isang Villa Elena record na lang ang dapat pipiliin dito.
        // Ang mga individual "Room" record ay hindi na direktang
        // bina-book (bahagi na sila ng buong Villa). Bug fix: `!=
        // maintenance` hindi `= available` — ang `status` ay real-time
        // occupancy lang (naka-flip habang may naka-check-in NGAYON),
        // hindi date-specific availability; kung `= available` ang gamit,
        // mawawala ang Villa dito kahit anong petsa/slot ang gustong
        // i-book habang may kasalukuyang naka-check-in. Ang totoong
        // per-date/slot check ay `Booking::hasConflict()` pa rin sa
        // `store()` sa ibaba.
        $properties = Property::where('status', '!=', 'maintenance')->where('type', 'villa')->orderBy('property_name')->get();
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
            'slot'             => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
            'num_guests'       => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'source'           => 'required|in:online,walk_in,phone,partner',
        ]);

        $property = Property::findOrFail($request->property_id);

        [$checkIn, $checkOut] = Booking::slotDateTimes($request->slot, $request->check_in_date);

        // Check availability — ang gap sa pagitan ng dalawang fixed slot
        // ang siya nang cleaning buffer, kaya walang hiwalay na buffer
        // check dito.
        if (Booking::hasConflict($request->property_id, $checkIn, $checkOut)) {
            return back()->withErrors(['check_in_date' => 'This villa is already booked for the selected date/slot.'])->withInput();
        }

        $nights = max(1, $checkIn->diffInDays($checkOut));

        // Flat/package price — base lang sa segment ng CHECK-IN (hindi
        // per-night).
        $baseAmount = $property->getPackagePrice($checkIn);

        $booking = Booking::create([
            'user_id'          => $request->user_id,
            'property_id'      => $request->property_id,
            'check_in_date'    => $checkIn->format('Y-m-d'),
            'check_in_time'    => $checkIn->format('H:i:s'),
            'check_out_date'   => $checkOut->format('Y-m-d'),
            'check_out_time'   => $checkOut->format('H:i:s'),
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
        event(new PropertyAvailabilityChanged(
            $booking->property_id,
            'blocked',
            $checkIn->format('Y-m-d'),
            $checkOut->format('Y-m-d'),
            checkInTime: $checkIn->format('g:i A'),
            checkOutTime: $checkOut->format('g:i A'),
            bookingId: $booking->id,
        ));

        NotificationHelper::notifyGuest(
    $booking->user_id,
    'Booking Confirmed!',
    "Your booking {$booking->booking_ref} has been created by our team.",
    route('customer.bookings.show', $booking, false)
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
        return view('admin.bookings.edit', compact('booking'));
    }

    // ── Update Booking ─────────────────────────────────────────────
    public function update(Request $request, Booking $booking)
    {
        $request->validate([
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
            'balance_arrangement' => 'nullable|in:deferred',
        ]);

        $oldStatus = $booking->status;
        $newStatus = $request->status;

        // Guard against double-cancellation (e.g. a resubmitted/duplicate
        // request re-triggering this branch) — without this, calling
        // cancel again on an already-cancelled (or checked-in/out) booking
        // would recompute and insert another 'refund' Payment row.
        if ($newStatus === 'cancelled' && !$booking->isCancellable()) {
            return back()->with('error', "Hindi na pwedeng i-cancel ang booking na ito — kasalukuyan itong '{$oldStatus}'.");
        }

        // Parehong guard na nasa Staff\FrontDeskController::checkIn() —
        // hindi dapat mag-check-in bago pa dumating ang naka-schedule na
        // oras, at kailangan ng explicit na "deferred" confirmation kung
        // may natitirang balance pa (para may accountability sa StaffLog
        // kung sino sa admin ang nag-authorize ng deferred balance).
        if ($newStatus === 'checked_in') {
            if ($booking->checkInDateTime()->isFuture()) {
                return back()->with('error', 'It is not yet time for check-in for this booking — it is scheduled for ' . $booking->checkInDateTime()->format('M d, Y g:i A') . '.');
            }

            if ($booking->balance_due > 0 && $request->balance_arrangement !== 'deferred') {
                return back()->with('error', 'There is a balance left of ₱' . number_format($booking->balance_due, 2) . ' — confirm the deferred check-in or record the payment before checking in.');
            }
        }

        // Parehong guard na nasa Staff\FrontDeskController::checkOut() —
        // hindi dapat ma-check-out ang isang booking bago pa man dumating
        // ang naka-schedule na check-out oras nito.
        if ($newStatus === 'checked_out' && $booking->checkOutDateTime()->isFuture()) {
            return back()->with('error', 'It is not yet time for check-out for this booking — it is scheduled for ' . $booking->checkOutDateTime()->format('M d, Y g:i A') . '.');
        }

        $updates = ['status' => $newStatus];

        // Refund preview — kinukuha bago pa man baguhin ang status
        // (kailangan ng tiered calculation ang orihinal na created_at at
        // check-in datetime, hindi apektado man ito ng status change,
        // pero mas malinaw itong tawagin habang tumpak pa ang konteksto).
        $refundPercentage = null;
        $refundAmount     = 0;

        if ($newStatus === 'cancelled') {
            $refundPercentage = $booking->calculateRefundPercentage();
            $refundAmount     = $booking->calculateRefundAmount();

            $updates['cancelled_at']        = now();
            $updates['cancellation_reason'] = $request->cancellation_reason;
            $updates['balance_due']         = 0; // cancelled na, walang balance na dapat pa bayaran
            // Free up the property
            $booking->property->update(['status' => 'available']);
        }

        if ($newStatus === 'checked_in') {
            $updates['actual_check_in'] = now();
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
            $updates['actual_check_out'] = now();
            $booking->property->update(['status' => 'available']);
        }

        $booking->update($updates);

        // ── Auto-compute at i-record ang refund (kung ang new status ay
        //    "cancelled") — parehong policy at Payment-record pattern na
        //    ginagamit sa Customer\HomeController::cancelBooking(), para
        //    hindi mag-out-of-sync ang dalawang entry point na ito.
        if ($newStatus === 'cancelled' && $refundAmount > 0) {
            $originalMethod = optional(
                $booking->payments()->where('payment_type', '!=', 'refund')->latest()->first()
            )->payment_method ?? 'cash';

            Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $refundAmount,
                'payment_method' => $originalMethod,
                'payment_type'   => 'refund',
                'status'         => 'success',
                'processed_by'   => auth()->id(),
                'payment_date'   => today(),
                'notes'          => "Auto-computed refund ({$refundPercentage}% policy) — cancelled by admin/staff.",
            ]);

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
                "Booking cancelled by admin/staff ({$refundPercentage}% refund policy)"
            );
        }

        // Notify guest
        $statusMessage = "Your booking {$booking->booking_ref} status has been updated to: " . ucfirst(str_replace('_', ' ', $newStatus));
        if ($newStatus === 'cancelled') {
            $statusMessage .= $refundAmount > 0
                ? ". ₱" . number_format($refundAmount, 2) . " ({$refundPercentage}%) will be refunded per our cancellation policy."
                : ". Based on our cancellation policy, this booking is not eligible for a refund.";
        }

        Notification::create([
            'user_id' => $booking->user_id,
            'type' => 'in_app',
            'title'   => 'Booking Status Updated',
            'message' => $statusMessage,
            'link'    => route('customer.bookings.show', $booking, false),
            'sent_at' => now(),
            'status'  => 'sent',
        ]);

        $logMessage = "Status changed from {$oldStatus} to {$newStatus} for {$booking->booking_ref}";
        if ($newStatus === 'cancelled') {
            $logMessage .= ". Refund eligibility: {$refundPercentage}% (₱" . number_format($refundAmount, 2) . ").";
        }
        if ($newStatus === 'checked_in' && $booking->balance_due > 0) {
            $logMessage .= ". DEFERRED BALANCE: ₱" . number_format($booking->balance_due, 2) . " — authorized by " . (auth()->user()->full_name ?? auth()->user()->name ?? 'admin') . ".";
        }
        StaffLog::record('updated_booking_status', 'bookings', $booking->id, $logMessage);

        event(new BookingUpdated($booking, 'status_changed', oldStatus: $oldStatus, newStatus: $newStatus));

        if ($newStatus === 'cancelled') {
            event(new PropertyAvailabilityChanged(
                $booking->property_id,
                'freed',
                $booking->check_in_date->format('Y-m-d'),
                $booking->check_out_date->format('Y-m-d'),
                bookingId: $booking->id,
            ));
        }

        $successMsg = "Booking status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".";
        if ($newStatus === 'cancelled') {
            $successMsg .= $refundAmount > 0
                ? " ₱" . number_format($refundAmount, 2) . " ({$refundPercentage}%) refund recorded."
                : " Walang refund — labas na ito sa eligible window ng cancellation policy.";
        }
        if ($newStatus === 'checked_in' && $booking->balance_due > 0) {
            $successMsg .= " ⚠️ May natitirang balance na ₱" . number_format($booking->balance_due, 2) . " — deferred hanggang check-out.";
        }

        return back()->with('success', $successMsg);
    }

    // ── Extend Stay (para sa naka-check-in na guest lang) ───────────
    // Hindi ito bagong booking — dine-diretso nitong itinutulak ang
    // check-out ng EXISTING booking. Kaya hindi ito sasailalim sa
    // fixed-slot na policy (extension lang ito ng stay, hindi bagong
    // package) — free-choice pa rin ang bagong check-out time.
    // May bayad para sa extension? Idagdag ito manually sa pamamagitan
    // ng "Extra Charges" panel — hindi awtomatikong kino-compute dito.
    public function extendStay(Request $request, Booking $booking)
    {
        if ($booking->status !== 'checked_in') {
            return back()->withErrors(['extend' => 'Ang "Extend Stay" ay para lang sa mga bookings na naka-check-in na.']);
        }

        $request->validate([
            'new_check_out_date' => 'required|date',
            'new_check_out_time' => 'required|date_format:H:i',
        ]);

        $currentCheckout = $booking->checkOutDateTime();
        $newCheckout = \Carbon\Carbon::parse($request->new_check_out_date . ' ' . $request->new_check_out_time);

        if ($newCheckout->lte($currentCheckout)) {
            return back()->withErrors(['extend' => 'Ang bagong check-out ay dapat pagkatapos ng kasalukuyang check-out na naka-schedule.']);
        }

        // Tignan kung may susunod na booking na ma-co-conflict sa
        // pinaplanong bagong check-out — dahil dito lang dapat pipigilan
        // ang extension.
        if (Booking::hasConflict($booking->property_id, $currentCheckout, $newCheckout, $booking->id)) {
            return back()->withErrors(['extend' => 'Hindi pwedeng i-extend — may susunod na guest na naka-schedule mag-check-in bago pa man mapaglinis ang Villa. Sabihan na lang ang guest na kailangan nilang mag check-out sa oras na naka-schedule.']);
        }

        $oldCheckoutDate = $booking->check_out_date->format('M d, Y');
        $oldCheckoutTime = $booking->check_out_time;

        $booking->update([
            'check_out_date' => $newCheckout->format('Y-m-d'),
            'check_out_time' => $newCheckout->format('H:i:s'),
            'num_nights'     => max(1, $booking->checkInDateTime()->diffInDays($newCheckout)),
        ]);

        // Housekeeping task (checkout_clean) ay dapat maging updated na rin
        // ang due_date nito para tama ang schedule.
        $booking->housekeepingTasks()
            ->where('task_type', 'checkout_clean')
            ->where('status', 'pending')
            ->update(['due_date' => $newCheckout->format('Y-m-d')]);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Stay Extended',
            'message' => "Your check-out for booking {$booking->booking_ref} has been extended to {$newCheckout->format('M d, Y g:i A')}.",
            'link'    => route('customer.bookings.show', $booking, false),
            'sent_at' => now(),
            'status'  => 'sent',
        ]);

        StaffLog::record('extended_stay', 'bookings', $booking->id,
            "Extended booking {$booking->booking_ref} checkout from {$oldCheckoutDate} {$oldCheckoutTime} to {$newCheckout->format('M d, Y g:i A')}");

        return back()->with('success', "Na-extend ang stay hanggang {$newCheckout->format('M d, Y g:i A')}. Huwag kalimutang idagdag ang extension charge sa Extra Charges kung meron.");
    }

    // ── Add Extra Charge (amenity request outside base package) ────
    public function storeExtra(Request $request, Booking $booking)
    {
        $request->validate([
            'item_name'   => 'required|string|max:150',
            'description' => 'nullable|string',
            'quantity'    => 'required|integer|min:1',
            'unit_price'  => 'required|numeric|min:0',
        ]);

        $total = $request->quantity * $request->unit_price;

        BookingExtra::create([
            'booking_id'  => $booking->id,
            'item_name'   => $request->item_name,
            'description' => $request->description,
            'quantity'    => $request->quantity,
            'unit_price'  => $request->unit_price,
            'total'       => $total,
        ]);

        $this->recalculateBookingTotals($booking);

        StaffLog::record('added_booking_extra', 'bookings', $booking->id,
            "Added extra '{$request->item_name}' (₱" . number_format($total, 2) . ") to booking {$booking->booking_ref}");

        return back()->with('success', "Naidagdag ang extra charge: {$request->item_name} (₱" . number_format($total, 2) . ")");
    }

    // ── Remove Extra Charge ─────────────────────────────────────────
    public function destroyExtra(Booking $booking, BookingExtra $extra)
    {
        abort_if($extra->booking_id !== $booking->id, 404);

        $itemName = $extra->item_name;
        $extra->delete();

        $this->recalculateBookingTotals($booking);

        StaffLog::record('removed_booking_extra', 'bookings', $booking->id,
            "Removed extra '{$itemName}' from booking {$booking->booking_ref}");

        return back()->with('success', "Naalis ang extra charge: {$itemName}");
    }

    // ── Recalculate totals after extras change ──────────────────────
    private function recalculateBookingTotals(Booking $booking): void
    {
        $extrasTotal = $booking->extras()->sum('total');
        $totalAmount = $booking->base_amount + $extrasTotal - $booking->discount_amount;
        $balanceDue  = max(0, $totalAmount - $booking->amount_paid);

        $paymentStatus = 'unpaid';
        if ($totalAmount > 0 && $booking->amount_paid >= $totalAmount) {
            $paymentStatus = 'paid';
        } elseif ($booking->amount_paid > 0) {
            $paymentStatus = 'partial';
        }

        $booking->update([
            'extras_amount'  => $extrasTotal,
            'total_amount'   => $totalAmount,
            'balance_due'    => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);
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