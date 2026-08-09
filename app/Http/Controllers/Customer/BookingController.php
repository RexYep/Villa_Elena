<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // ── Reschedule Form ─────────────────────────────────────────────
    public function edit(Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        if (!$booking->isCancellable()) {
            return back()->with('error', 'This booking can no longer be rescheduled.');
        }

        $booking->load('property');

        return view('customer.reschedule_form', compact('booking'));
    }

    // ── Reschedule Booking ──────────────────────────────────────────
    public function update(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);

        if (!$booking->isCancellable()) {
            return back()->with('error', 'This booking can no longer be rescheduled.');
        }

        $request->validate([
            'checkin' => 'required|date|after_or_equal:today',
            'slot'    => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $nights = max(1, $checkin->diffInDays($checkout));

        if (Booking::hasConflict($booking->property_id, $checkin, $checkout, $booking->id)) {
            return back()->withErrors(['dates' => 'Sorry, the Villa is not available on the selected date/slot.'])->withInput();
        }

        $oldRef   = $booking->booking_ref;
        $oldDates = $booking->check_in_date->format('M d, Y') . ' — ' . $booking->check_out_date->format('M d, Y');

        $newTotal = $booking->property->getPackagePrice($checkin);

        $booking->update([
            'check_in_date'   => $checkin->format('Y-m-d'),
            'check_in_time'   => $checkin->format('H:i:s'),
            'check_out_date'  => $checkout->format('Y-m-d'),
            'check_out_time'  => $checkout->format('H:i:s'),
            'num_nights'      => $nights,
            'base_amount'     => $newTotal,
            'total_amount'    => $newTotal,
        ]);

        // Reconcile balance/refund against what's already been paid —
        // same recompute pattern as HomeController::cancelBooking().
        $refundAmount = 0;

        if ($newTotal < $booking->amount_paid) {
            $refundAmount = round($booking->amount_paid - $newTotal, 2);

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
                'notes'          => "Auto-computed refund — guest rescheduled to a lower-priced slot.",
            ]);

            $totalPaid     = $booking->payments()->where('payment_type', '!=', 'refund')->sum('amount');
            $totalRefunded = $booking->payments()->where('payment_type', 'refund')->sum('amount');
            $amountPaid    = max(0, $totalPaid - $totalRefunded);

            $booking->update([
                'amount_paid'    => $amountPaid,
                'balance_due'    => 0,
                'payment_status' => $amountPaid > 0 ? 'partial' : 'refunded',
            ]);

            NotificationHelper::refundIssued($booking->fresh(), $refundAmount, 'Booking rescheduled to a lower-priced slot');
        } else {
            $balanceDue = round($newTotal - $booking->amount_paid, 2);

            $booking->update([
                'balance_due'    => $balanceDue,
                'payment_status' => $balanceDue <= 0 ? 'paid' : ($booking->amount_paid > 0 ? 'partial' : 'unpaid'),
            ]);
        }

        $booking->refresh();

        StaffLog::record('booking_rescheduled', 'bookings', $booking->id,
            "Guest rescheduled booking {$oldRef} from {$oldDates} to " .
            $checkin->format('M d, Y') . ' — ' . $checkout->format('M d, Y'));

        NotificationHelper::notifyAdmin(
            "Booking Rescheduled — {$booking->booking_ref}",
            ($booking->user->full_name ?? 'Guest') . " rescheduled their booking from {$oldDates} to " .
            $checkin->format('M d, Y') . ' — ' . $checkout->format('M d, Y') . '.',
            route('admin.bookings.show', $booking, false)
        );

        $message = "Booking {$booking->booking_ref} has been rescheduled to " . $checkin->format('M d, Y') . '.';
        if ($booking->balance_due > 0) {
            $message .= ' An additional ₱' . number_format($booking->balance_due, 2) . ' is now due.';
        } elseif ($refundAmount > 0) {
            $message .= ' ₱' . number_format($refundAmount, 2) . ' has been refunded for the price difference.';
        }

        return redirect()->route('customer.bookings.show', $booking)->with('success', $message);
    }
}
