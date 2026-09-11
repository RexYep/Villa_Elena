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
use App\Helpers\BookingMailHelper;
use App\Events\BookingCreated;
use App\Events\BookingUpdated;
use App\Events\PropertyAvailabilityChanged;
use Illuminate\Support\Facades\Auth;

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

        // Kung "ngayong araw" ang pinili pero lumagpas na ang check-in
        // time mismo ng slot (hal. 10PM na pero "Day" 8AM pa rin ang
        // pinili), hindi na ito dapat payagan — hindi na ito makaka-
        // check-in sa oras na iyon.
        if ($checkIn->isPast()) {
            return back()->withErrors(['check_in_date' => 'The ' . Booking::SLOTS[$request->slot]['label'] . ' check-in slot has already passed for today. Please select a different date or slot.'])->withInput();
        }

        $nights = max(1, $checkIn->diffInDays($checkOut));

        // Flat/package price — base lang sa segment ng CHECK-IN (hindi
        // per-night) — kasama na ang anumang tumatamang seasonal promo.
        // Awtomatiko ito: kahit admin ang gumawa ng booking, pareho pa
        // rin ang presyong nakukuha ng guest sa online portal.
        $quote          = $property->quoteFor($checkIn, $request->slot);
        $baseAmount     = $quote['base'];
        $discountAmount = $quote['discount'];
        $totalAmount    = $quote['total'];
        $promo          = $quote['promo'];

        // Availability check + INSERT bilang iisang atomic na hakbang —
        // tingnan ang Booking::reserveSlot(). Dating magkahiwalay ang
        // dalawa, kaya kayang sumingit ng isang online booking sa
        // pagitan nila at makuha ang parehong slot.
        $booking = Booking::reserveSlot($request->property_id, $checkIn, $checkOut, fn () => Booking::create([
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
            'discount_amount'  => $discountAmount,
            'discount_id'      => $promo?->id,
            'total_amount'     => $totalAmount,
            'amount_paid'      => 0,
            'balance_due'      => $totalAmount,
            'status'           => 'confirmed',
            'payment_status'   => 'unpaid',
            'source'           => $request->source,
            'special_requests' => $request->special_requests,
        ]));

        if ($booking === null) {
            return back()->withErrors(['check_in_date' => 'This villa is already booked for the selected date/slot.'])->withInput();
        }

        $promo?->increment('used_count');

        // Realtime broadcast lang ito (admin dashboard toast) — hindi ito
        // dapat maka-block sa buong request kung mag-fail ang Pusher (hal.
        // mali ang credentials, timeout). Naka-commit na ang booking sa
        // puntong ito.
        try {
            event(new BookingCreated($booking));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast BookingCreated: ' . $e->getMessage());
        }

        try {
            event(new PropertyAvailabilityChanged(
                $booking->property_id,
                'blocked',
                $checkIn->format('Y-m-d'),
                $checkOut->format('Y-m-d'),
                checkInTime: $checkIn->format('g:i A'),
                checkOutTime: $checkOut->format('g:i A'),
                bookingId: $booking->id,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast PropertyAvailabilityChanged (admin create): ' . $e->getMessage());
        }

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
        $booking->load(['user', 'property.images', 'payments.refundTransfers', 'extras', 'review', 'housekeepingTasks']);
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

        // Ang pagbabalik ng isang cancelled/no_show na booking sa isang
        // buhay na status ay muling KUMUKUHA ng slot nito — at malamang
        // may ibang guest nang humahawak doon, dahil pinalaya na iyon
        // nang ma-cancel ito. Walang tseke nito dati: tahimik na
        // nagiging double-booking. (Sasagasaan din ito ngayon ng
        // slot_hold unique index, pero bilang 500 — mas mabuti nang
        // malinaw na mensahe kaysa error page.)
        if (in_array($oldStatus, ['cancelled', 'no_show'], true)
            && ! in_array($newStatus, ['cancelled', 'no_show'], true)
            && Booking::hasConflict($booking->property_id, $booking->checkInDateTime(), $booking->checkOutDateTime(), $booking->id)
        ) {
            return back()->with('error',
                "Hindi na maibabalik sa '{$newStatus}' ang booking na ito — may ibang booking nang humahawak sa "
                . $booking->checkInDateTime()->format('M d, Y g:i A') . ' na slot.');
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
            $updates['cancelled_by']        = 'admin';
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
                // 'pending' — inaprubahan na ang refund, pero manu-mano
                // pang ipapadala ang pera (tingnan ang Payments page).
                'status'         => 'pending',
                'processed_by'   => Auth::id(),
                'payment_date'   => today(),
                'notes'          => "Auto-computed refund ({$refundPercentage}% policy) — cancelled by admin/staff.",
            ]);

            $booking->recalculateFinancials();

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
            $logMessage .= ". DEFERRED BALANCE: ₱" . number_format($booking->balance_due, 2) . " — authorized by " . (Auth::user()->full_name ?? Auth::user()->name ?? 'admin') . ".";
        }
        StaffLog::record('updated_booking_status', 'bookings', $booking->id, $logMessage);

        try {
            event(new BookingUpdated($booking, 'status_changed', oldStatus: $oldStatus, newStatus: $newStatus));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast BookingUpdated: ' . $e->getMessage());
        }

        if ($newStatus === 'cancelled') {
            try {
                event(new PropertyAvailabilityChanged(
                    $booking->property_id,
                    'freed',
                    $booking->check_in_date->format('Y-m-d'),
                    $booking->check_out_date->format('Y-m-d'),
                    bookingId: $booking->id,
                ));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to broadcast PropertyAvailabilityChanged (status update): ' . $e->getMessage());
            }
        }

        $successMsg = "Booking status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".";
        if ($newStatus === 'cancelled') {
            $successMsg .= $refundAmount > 0
                ? " ₱" . number_format($refundAmount, 2) . " ({$refundPercentage}%) refund recorded."
                : " No refund — this booking is outside the eligible window of the cancellation policy.";
        }
        if ($newStatus === 'checked_in' && $booking->balance_due > 0) {
            $successMsg .= " ⚠️ Outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — deferred until check-out.";
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

        $oldCheckoutDate = $booking->check_out_date->format('M d, Y');
        $oldCheckoutTime = $booking->check_out_time;

        // Tignan kung may susunod na booking na ma-co-conflict sa
        // pinaplanong bagong check-out — dahil dito lang dapat pipigilan
        // ang extension. Kasama na ito sa lock ng reserveSlot(), kaya
        // hindi na kayang sumingit ng isang bagong booking sa susunod na
        // slot sa pagitan ng check at ng UPDATE.
        $extended = Booking::reserveSlot(
            $booking->property_id,
            $currentCheckout,
            $newCheckout,
            function () use ($booking, $newCheckout) {
                $booking->update([
                    'check_out_date' => $newCheckout->format('Y-m-d'),
                    'check_out_time' => $newCheckout->format('H:i:s'),
                    'num_nights'     => max(1, $booking->checkInDateTime()->diffInDays($newCheckout)),
                ]);

                return $booking;
            },
            $booking->id
        );

        if ($extended === null) {
            return back()->withErrors(['extend' => 'Hindi pwedeng i-extend — may susunod na guest na naka-schedule mag-check-in bago pa man mapaglinis ang Villa. Sabihan na lang ang guest na kailangan nilang mag check-out sa oras na naka-schedule.']);
        }

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
        // Wala nang `refund` dito. Dati, ang "Refund" sa form na ito ay
        // gumagawa ng BAGONG refund row na agad `success` — hindi nito
        // isinasara ang naka-pending na refund (nananatiling "NOT SENT"
        // iyon), hindi ito dumadaan sa refundable-balance check ng
        // PaymentController::refund(), at nabibilang nang dalawang beses
        // ang iisang refund sa booking. Nasa Payments page ang refund:
        // "Refund" para aprubahan, "Mark Paid Out" para isara.
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:qrph,cash',
            'payment_type'   => 'required|in:full_payment,partial',
            'notes'          => 'nullable|string',
            'confirm_duplicate' => 'nullable|boolean',
        ], [
            'payment_type.in' => 'Refunds are not recorded here. Issue one with "Refund" on the Payments page, '
                . 'and close one you already sent with "Mark Paid Out".',
        ]);

        $problem = Payment::manualEntryProblem(
            $booking->fresh(),
            (float) $request->amount,
            $request->payment_method,
            (bool) $request->boolean('confirm_duplicate'),
        );

        if ($problem) {
            return back()->withErrors($problem)->withInput();
        }

        Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type'   => $request->payment_type,
            'status'         => 'success',
            'processed_by'   => Auth::id(),
            'notes'          => $request->notes,
            'payment_date'   => now(),
        ]);

        

        $booking->recalculateFinancials();
        // Tingnan ang Booking::confirmOnFirstPayment() — kung hindi ito
        // tatawagin, kakanselahin ng stale pending sweeper ang booking
        // na may hawak nang pera ng guest.
        $wasPending = $booking->confirmOnFirstPayment();

        // Dati, walang email ang manwal na path na ito. Papasok na pera na
        // lang ang tinatanggap ng form (tingnan ang validation sa itaas),
        // kaya laging may resibo.
        BookingMailHelper::paymentRecorded($booking, (float) $request->amount, $wasPending);

        StaffLog::record('recorded_payment', 'payments', $booking->id,
            "Recorded ₱{$request->amount} payment for {$booking->booking_ref}");

        return back()->with('success', "Payment of ₱" . number_format($request->amount, 2) . " recorded successfully.");
    }
}