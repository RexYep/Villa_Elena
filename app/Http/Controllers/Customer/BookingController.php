<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\StaffLog;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    // ── Reschedule Form ─────────────────────────────────────────────
    public function edit(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        if ($reason = $booking->rescheduleBlockReason()) {
            return back()->with('error', $reason);
        }

        $booking->load('property');

        // Kaparehong mapa ng availability na ginagamit ng calendar sa
        // property page — at kaparehong exclusion ng hasConflict() sa
        // update() sa ibaba, kaya hindi hinaharangan ng booking na ito ang
        // sarili nitong slot. Kung wala ito, pumipili nang bulag ang bisita
        // at saka lang niya nalalaman na sarado ang slot pagka-submit.
        $slotAvailability = Booking::slotAvailabilityMap($booking->property_id, $booking->id);
        $pastSlotsToday   = Booking::pastSlotsToday();

        return view('customer.reschedule_form', compact('booking', 'slotAvailability', 'pastSlotsToday'));
    }

    // ── Reschedule Booking ──────────────────────────────────────────
    public function update(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        // Ulit na check dito (hindi lang sa edit()) — kung nakabukas ang
        // form nang matagal, posibleng lumagpas na sa cutoff mula noong
        // na-render ito, o nakapag-reschedule na sa ibang tab.
        if ($reason = $booking->rescheduleBlockReason()) {
            return back()->with('error', $reason);
        }

        $request->validate([
            'checkin' => 'required|date|after_or_equal:today',
            'slot'    => 'required|in:' . implode(',', array_keys(Booking::SLOTS)),
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $nights = max(1, $checkin->diffInDays($checkout));

        if ($checkin->isPast()) {
            return back()->withErrors(['dates' => 'The ' . Booking::SLOTS[$request->slot]['label'] . ' check-in slot has already passed for today. Please select a different date or slot.'])->withInput();
        }

        if (Booking::hasConflict($booking->property_id, $checkin, $checkout, $booking->id)) {
            return back()->withErrors(['dates' => 'Sorry, the Villa is not available on the selected date/slot.'])->withInput();
        }

        $oldRef   = $booking->booking_ref;
        $oldDates = $booking->check_in_date->format('M d, Y') . ' — ' . $booking->check_out_date->format('M d, Y');

        // Muling pinepresyuhan ang booking para sa BAGONG petsa/slot,
        // kasama ang promong tumatama roon. Ibig sabihin, puwedeng
        // mawala ang promo kapag lumipat palabas ng window nito (tataas
        // ang balanse), o puwede namang bumaba ang presyo kung lumipat
        // papasok — parehong kaso ay hinahawakan na ng price-difference
        // na lohika sa ibaba.
        $quote        = $booking->property->quoteFor($checkin, $request->slot);
        $newTotal     = $quote['total'];
        $newPromo     = $quote['promo'];
        $oldPromoId   = $booking->discount_id;

        $booking->update([
            'check_in_date'    => $checkin->format('Y-m-d'),
            'check_in_time'    => $checkin->format('H:i:s'),
            'check_out_date'   => $checkout->format('Y-m-d'),
            'check_out_time'   => $checkout->format('H:i:s'),
            'num_nights'       => $nights,
            'base_amount'      => $quote['base'],
            'discount_amount'  => $quote['discount'],
            'discount_id'      => $newPromo?->id,
            'total_amount'     => $newTotal,
            'reschedule_count' => $booking->reschedule_count + 1,
        ]);

        // Panatilihing tapat ang bilang ng paggamit kapag lumipat ang
        // booking sa ibang promo — kung hindi, mauubos ang `usage_limit`
        // ng isang promong hindi na naman aktuwal na ginagamit.
        if ($oldPromoId !== $newPromo?->id) {
            if ($oldPromoId) {
                Discount::where('id', $oldPromoId)->where('used_count', '>', 0)->decrement('used_count');
            }
            $newPromo?->increment('used_count');
        }

        // Kung mas mura ang bagong slot kaysa sa nabayaran na, may sobra
        // na dapat ibalik. Ginagawa itong 'pending' na refund — utang na
        // ito ng resort, pero hindi pa nailalabas ang pera (manu-mano
        // itong ipinapadala ng admin).
        $refundAmount  = 0;
        $refundPayment = null;

        if ($newTotal < $booking->amount_paid) {
            $refundAmount = round($booking->amount_paid - $newTotal, 2);

            $originalMethod = optional(
                $booking->payments()->where('payment_type', '!=', 'refund')->latest()->first()
            )->payment_method ?? 'cash';

            $refundPayment = Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $refundAmount,
                'payment_method' => $originalMethod,
                'payment_type'   => 'refund',
                'status'         => 'pending',
                'payment_date'   => today(),
                'notes'          => "Auto-computed refund — guest rescheduled to a lower-priced slot.",
            ]);

            NotificationHelper::refundIssued($booking->fresh(), $refundAmount, 'Booking rescheduled to a lower-priced slot');

            // Ang guest din ang dapat makaalam na may sobra siyang
            // babalik — hindi lang ang admin.
            NotificationHelper::refundApprovedForGuest(
                $booking->fresh(),
                $refundAmount,
                'Rescheduled to a lower-priced slot',
                $refundPayment
            );
        }

        $booking->recalculateFinancials();
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
            // "has been refunded" dati — 'pending' pa lang naman ang refund
            // row, kaya walang perang naipadala sa puntong ito.
            $message .= ' ₱' . number_format($refundAmount, 2) . ' will be refunded for the price difference.';
        }

        $remaining = $booking->reschedulesRemaining();
        $message .= $remaining > 0
            ? " You have {$remaining} reschedule" . ($remaining === 1 ? '' : 's') . ' left for this booking.'
            : ' This was your last available reschedule for this booking.';

        // Pareho ng dahilan sa cancel path: hindi hinaharangan ang
        // reschedule ng isang form tungkol sa bank details — pagkatapos
        // lang natin itinatanong, at may link pa rin sa notification
        // kung sakaling umalis siya.
        if ($refundPayment && $refundPayment->needsRefundDestination()) {
            return redirect()
                ->route('customer.refunds.destination', $refundPayment)
                ->with('success', $message . ' Please tell us where to send the refund.');
        }

        return redirect()->route('customer.bookings.show', $booking)->with('success', $message);
    }
}
