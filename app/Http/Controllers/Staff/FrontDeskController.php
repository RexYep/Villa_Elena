<?php

namespace App\Http\Controllers\Staff;

use App\Events\FrontdeskUpdated;
use App\Events\PropertyAvailabilityChanged;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\StaffLog;
use App\Models\User;
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

        // Villa para sa walk-in form — Villa lang, hindi yung mga
        // individual Room A-F (info-only records na sila simula v4.0,
        // hindi na hiwalay na bookable). Bug fix: hindi dapat
        // `status = 'available'` ang gamit dito — ang `status` column
        // ay REAL-TIME occupancy lang (naka-flip kapag may naka-check-in
        // NGAYON), hindi date-specific availability. Kapag may naka-
        // check-in kahit ngayon lang, mawawala ang Villa sa buong form
        // kahit anong PETSA/SLOT ang gustong i-book ng staff — hindi na
        // naaabot pa ang tamang date/slot check (`Booking::hasConflict()`
        // sa `storeWalkin()`). Ang totoong dapat i-exclude dito ay
        // `maintenance` lang (deliberate block ng admin), hindi
        // `occupied` (pansamantalang estado lang, hindi hadlang sa
        // pag-book ng ibang petsa/slot).
        $availableProperties = Property::where('status', '!=', 'maintenance')
            ->where('type', 'villa')
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
        // Villa lang, hindi yung mga individual Room A-F (info-only
        // records na sila simula v4.0, hindi na hiwalay na bookable).
        // Bug fix: `!= maintenance` hindi `= available` — see index()
        // comment sa itaas para sa buong paliwanag.
        $availableProperties = Property::where('status', '!=', 'maintenance')
            ->where('type', 'villa')
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
            // property_id: dapat Villa lang ang matatanggap, kahit
            // ma-bypass ang dropdown restriction sa frontend.
            'property_id'      => 'required|exists:properties,id,type,villa',
            'check_in_date'    => 'required|date|after_or_equal:today',
            'slot'             => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
            'num_guests'       => 'required|integer|min:1',
            'special_requests' => 'nullable|string|max:500',
            'payment_amount'   => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:cash,gcash,bank_transfer,credit_card',
            'payment_type'     => 'nullable|in:deposit,full_payment',
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->check_in_date);

        // Kunin ang property + presyo BAGO pumasok sa transaction, para
        // ma-validate na natin ang payment_amount laban sa totoong total
        // bago pa gumawa ng kahit anong record.
        $property   = Property::findOrFail($request->property_id);
        $baseAmount = $property->getPackagePrice($checkin);
        $amountPaid = (float) ($request->payment_amount ?? 0);

        // Overpayment guard — hindi puwedeng lumagpas sa total ang
        // ire-record na "amount received". Kung may sukli, i-record na
        // lang ang netong natanggap (hal. binayaran ₱5,000, sukli ₱1,000
        // → i-type na lang ₱4,000), hindi ang buong ₱5,000.
        if ($amountPaid > $baseAmount) {
            return back()
                ->withErrors(['payment_amount' => 'Ang natanggap na bayad (₱' . number_format($amountPaid, 2) . ') ay lampas sa kabuuang halaga (₱' . number_format($baseAmount, 2) . '). Kung may sukli, i-type na lang ang netong natanggap.'])
                ->withInput();
        }

        // Availability check — dating wala nito, kaya posibleng
        // ma-double-book ang buong Villa. Wrapped sa transaction +
        // lockForUpdate() para maiwasan din ang race condition kung
        // dalawang staff sabay-sabay mag-book.
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $checkin, $checkout, $property, $baseAmount, $amountPaid) {

            \App\Models\Booking::where('property_id', $request->property_id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->lockForUpdate()
                ->get();

            if (Booking::hasConflict($request->property_id, $checkin, $checkout)) {
                return null; // signal conflict pabalik sa labas ng closure
            }

            // Create new guest if needed
            if ($request->guest_type === 'new') {
                // Random na password sa halip na hardcoded default —
                // hindi na ito magiging shared/predictable sa lahat ng
                // walk-in accounts. I-trigger ang password reset email
                // para makapili ang guest ng sarili nilang password.
                $tempPassword = \Illuminate\Support\Str::random(20);

                $user = User::create([
                    'full_name' => $request->full_name,
                    'email'     => $request->email,
                    'phone'     => $request->phone,
                    'password'  => bcrypt($tempPassword),
                    'role'      => 'customer',
                    'status'    => 1,
                ]);

                \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
            } else {
                $user = User::findOrFail($request->user_id);
            }

            $nights = max(1, $checkin->diffInDays($checkout));

            $balanceDue = $baseAmount - $amountPaid;

            $paymentStatus = 'unpaid';
            if ($amountPaid >= $baseAmount) $paymentStatus = 'paid';
            elseif ($amountPaid > 0)        $paymentStatus = 'partial';

            // Auto-determine payment type — kung binayaran nang buo,
            // "full_payment"; kung hindi, "deposit". Hindi na umaasa sa
            // manual na pinili ni staff, para maiwasan ang human error.
            $paymentType = $amountPaid >= $baseAmount ? 'full_payment' : 'deposit';

            $booking = Booking::create([
                'user_id'          => $user->id,
                'property_id'      => $property->id,
                'check_in_date'    => $checkin->format('Y-m-d'),
                'check_in_time'    => $checkin->format('H:i:s'),
                'check_out_date'   => $checkout->format('Y-m-d'),
                'check_out_time'   => $checkout->format('H:i:s'),
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

            NotificationHelper::walkInBooking($booking->load(['user', 'property']), auth()->user()->full_name);

            // Record payment if any amount was paid
            if ($amountPaid > 0 && $request->payment_method) {
                Payment::create([
                    'booking_id'     => $booking->id,
                    'amount'         => $amountPaid,
                    'payment_method' => $request->payment_method,
                    'payment_type'   => $paymentType,
                    'status'         => 'success',
                    'payment_date'   => today(),
                    'received_by'    => auth()->id(),
                    'notes'          => 'Walk-in payment recorded by ' . auth()->user()->full_name,
                ]);
            }

            Notification::create([
                'user_id' => $user->id,
                'type'    => 'in_app',
                'title'   => 'Booking Confirmed!',
                'message' => "Your walk-in booking {$booking->booking_ref} for {$property->property_name} has been confirmed. Check-in: {$checkin->format('M d, Y g:i A')}.",
                'link'    => route('customer.bookings.show', $booking, false),
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            StaffLog::record('walkin_booking', 'bookings', $booking->id,
                "Walk-in booking {$booking->booking_ref} created by " . auth()->user()->full_name . " for guest {$user->full_name}");

            return $booking;
        });

        if (!$booking) {
            return back()
                ->withErrors(['check_in_date' => 'Naka-book na ang Villa sa napiling petsa/slot. Pumili ng ibang slot.'])
                ->withInput();
        }

        event(new FrontdeskUpdated(
            'walkin',
            "Walk-in booking {$booking->booking_ref} created for {$booking->user->full_name} ({$booking->property->property_name}).",
            bookingId: $booking->id,
            actor: auth()->user()->full_name,
        ));
        event(new PropertyAvailabilityChanged(
            $booking->property_id,
            'blocked',
            $booking->check_in_date->format('Y-m-d'),
            $booking->check_out_date->format('Y-m-d'),
            checkInTime: $booking->check_in_time ? \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') : null,
            checkOutTime: $booking->check_out_time ? \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') : null,
            bookingId: $booking->id,
        ));

        return redirect()->route('staff.frontdesk')
            ->with('success', "✅ Walk-in booking {$booking->booking_ref} created for {$booking->user->full_name}. " .
                ($booking->amount_paid > 0 ? "Payment of ₱" . number_format($booking->amount_paid, 2) . " recorded." : ""));
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
            'status'         => 'success',
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

        event(new FrontdeskUpdated(
            'payment',
            "₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref} by " . auth()->user()->full_name . ".",
            bookingId: $booking->id,
            actor: auth()->user()->full_name,
        ));

        return back()->with('success', "✅ Payment of ₱" . number_format($request->amount, 2) . " recorded for {$booking->booking_ref}.");
    }

    // ── Check In ──────────────────────────────────────────────────
    public function checkIn(Request $request, Booking $booking)
    {
        if ($booking->status !== 'confirmed') {
            return back()->with('error', 'Booking must be confirmed before check-in.');
        }

        // Bug fix: dating pwede i-click ang Check In button kahit hindi
        // pa ang naka-schedule na araw/oras ng guest. Ngayon, hindi na
        // ito papayagan hangga't hindi pa dumarating ang eksaktong
        // check-in datetime (ang automated na "bookings:auto-checkinout"
        // command na ang bahalang mag-check-in nang eksakto sa oras).
        if ($booking->checkInDateTime()->isFuture()) {
            return back()->with('error', 'It is not yet time for check-in for this booking — it is scheduled for ' . $booking->checkInDateTime()->format('M d, Y g:i A') . '. It will be automatically checked in at the correct time.');
        }

        // Kung may natitirang balance (hal. 50% deposit lang ang binayad),
        // hindi na basta-basta papayagan ang check-in nang walang malinaw
        // na desisyon si staff — kailangan munang pumili: bayaran ngayon
        // (via Payment modal, na magpapababa sa balance_due papuntang 0
        // bago pa man i-submit ang check-in form) o i-confirm explicitly
        // ang deferral (babayaran na lang bago mag-check-out).
        if ($booking->balance_due > 0) {
            $request->validate([
                'balance_arrangement' => 'required|in:deferred',
            ], [
                'balance_arrangement.required' => 'There is an outstanding balance that needs to be confirmed before checking in.',
            ]);
        }

        $booking->update(['status' => 'checked_in', 'actual_check_in' => now()]);
        $booking->property->update(['status' => 'occupied']);
        NotificationHelper::guestCheckedIn($booking->load(['user','property']));

        HousekeepingTask::create([
            'property_id'    => $booking->property_id,
            'booking_id'     => $booking->id,
            'task_type'      => 'checkout_clean',
            'due_date'       => $booking->check_out_date,
            'scheduled_date' => $booking->check_out_date,
            'status'         => 'pending',
            'notes'          => "Post-checkout cleaning for booking {$booking->booking_ref}",
        ]);

        Notification::create([
            'user_id' => $booking->user_id,
            'type'    => 'in_app',
            'title'   => 'Welcome to Villa Elena!',
            'message' => "You have successfully checked in to {$booking->property->property_name}. Enjoy your stay! Check-out: {$booking->check_out_date->format('F d, Y')}.",
            'link'    => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // Audit trail — kung deferred ang balance, malinaw na nakalagay
        // dito kung sino sa staff ang nagkumpirma ng arrangement na ito.
        $balanceNote = $booking->balance_due > 0
            ? " There is an outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — confirmed by " . auth()->user()->full_name . " to be paid before check-out."
            : "";

        StaffLog::record('check_in', 'bookings', $booking->id,
            "Checked in {$booking->user->full_name} for {$booking->booking_ref}." . $balanceNote);

        event(new FrontdeskUpdated(
            'checkin',
            "{$booking->user->full_name} checked in to {$booking->property->property_name} by " . auth()->user()->full_name . ".",
            bookingId: $booking->id,
            actor: auth()->user()->full_name,
        ));

        $successMsg = "✅ {$booking->user->full_name} checked in to {$booking->property->property_name}.";
        if ($booking->balance_due > 0) {
            $successMsg .= " ⚠️ There is an outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — must be paid before check-out.";
        }

        return back()->with('success', $successMsg);
    }

    // ── Check Out ─────────────────────────────────────────────────
    public function checkOut(Booking $booking)
    {
        if ($booking->status !== 'checked_in') {
            return back()->with('error', 'Guest must be checked in first.');
        }

        // Bug fix: dating pwede i-click ang Check Out button kahit hindi
        // pa dumarating ang naka-schedule na check-out oras ng guest.
        // Parehong guard na katulad ng ginagamit sa manual check-in —
        // hindi dapat ma-check-out ang isang booking bago pa man dumating
        // ang eksaktong oras nito.
        if ($booking->checkOutDateTime()->isFuture()) {
            return back()->with('error', 'It is not yet time for check-out for this booking — it is scheduled for ' . $booking->checkOutDateTime()->format('M d, Y g:i A') . '.');
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
            'link'    => route('customer.bookings.show', $booking, false),
            'is_read' => 0,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        StaffLog::record('check_out', 'bookings', $booking->id,
            "Checked out {$booking->user->full_name} for {$booking->booking_ref}");

        event(new FrontdeskUpdated(
            'checkout',
            "{$booking->user->full_name} checked out of {$booking->property->property_name} by " . auth()->user()->full_name . ".",
            bookingId: $booking->id,
            actor: auth()->user()->full_name,
        ));

        return back()->with('success', "✅ {$booking->user->full_name} checked out. Housekeeping task activated.");
    }

    // ── Housekeeping Tasks ─────────────────────────────────────────
    public function startTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'in_progress']);
        StaffLog::record('task_started', 'housekeeping_tasks', $task->id,
            "Started task for {$task->property->property_name}");

        event(new FrontdeskUpdated(
            'task_started',
            "Housekeeping task for {$task->property->property_name} started by " . auth()->user()->full_name . ".",
            taskId: $task->id,
            actor: auth()->user()->full_name,
        ));

        return back()->with('success', "Task started.");
    }

    public function completeTask(HousekeepingTask $task)
    {
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        StaffLog::record('task_completed', 'housekeeping_tasks', $task->id,
            "Completed task for {$task->property->property_name}");

        event(new FrontdeskUpdated(
            'task_completed',
            "Housekeeping task for {$task->property->property_name} completed by " . auth()->user()->full_name . ".",
            taskId: $task->id,
            actor: auth()->user()->full_name,
        ));

        return back()->with('success', "✅ Housekeeping task marked as complete.");
    }
}