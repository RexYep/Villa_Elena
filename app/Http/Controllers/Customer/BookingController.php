<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\StaffLog;
use App\Rules\SlotOfferedOnDate;
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
        // Ang mga petsang ipinasara ng admin — wala sa mapa sa itaas, na
        // booking lang ang laman.
        $blockedSlots     = Booking::blockedSlotMap($booking->property_id);

        return view('customer.reschedule_form', compact('booking', 'slotAvailability', 'pastSlotsToday', 'blockedSlots'));
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
            'checkin' => 'required|date|after_or_equal:today|'.Booking::advanceLimitRule(),
            // Hindi isang nakapirming listahan: ang mga inaalok na slot ay
            // nakadepende sa piniling petsa (tingnan ang SlotOfferedOnDate).
            'slot'    => ['required', new SlotOfferedOnDate('checkin', $booking->property)],
        ], [
            'checkin.before_or_equal' => Booking::advanceLimitMessage(),
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $nights = max(1, $checkin->diffInDays($checkout));

        if ($checkin->isPast()) {
            return back()->withErrors(['dates' => 'The ' . Booking::SLOTS[$request->slot]['label'] . ' check-in slot has already passed for today. Please select a different date or slot.'])->withInput();
        }

        $oldRef   = $booking->booking_ref;
        $oldDates = $booking->check_in_date->format('M d, Y') . ' — ' . $booking->check_out_date->format('M d, Y');

        // Muling pinepresyuhan ang booking para sa BAGONG petsa/slot,
        // kasama ang promong tumatama roon. Ibig sabihin, puwedeng
        // mawala ang promo kapag lumipat palabas ng window nito (tataas
        // ang balanse), o puwede namang bumaba ang presyo kung lumipat
        // papasok — parehong kaso ay hinahawakan na ng price-difference
        // na lohika sa ibaba.
        // Ang may-ari ng booking ang guest dito — hindi Auth::user() sa
        // pangalan ng kaginhawahan, kahit pareho sila (tiniyak na ng
        // abort_if sa itaas). Ang booking ang nagsasabi kung kaninong
        // kasaysayan ang sinusukat, at iyon ang nananatiling tama kahit
        // dumaan pa ito sa ibang path sa hinaharap.
        $quote        = $booking->property->quoteFor($checkin, $request->slot, $booking->user);
        $newTotal     = $quote['total'];
        $newPromo     = $quote['promo'];
        $oldPromoId   = $booking->discount_id;

        // Mas mura ang bagong slot kaysa sa naibayad na. Dati, awtomatiko
        // itong nagiging 'pending' na refund; salungat iyon sa patakaran
        // ng may-ari na non-refundable ang lahat ng bayad (v7.52), at
        // ginagawa nitong paraan ng pagkuha ng pera ang reschedule.
        //
        // Hindi ito MALI — baka iyon talaga ang petsang kaya ng guest —
        // pero may kapalit na hindi niya makikita sa form (walang presyo
        // roon). Kaya hinaharangan at tinatanong, gaya ng
        // Payment::manualEntryProblem(): kahina-hinala = kumpirmahin.
        // Kailangang mauna ito sa reserveSlot(): hindi na maibabalik ang
        // isang reschedule, at nababawasan pa ang natitirang bilang.
        $excess = max(0, round((float) $booking->amount_paid - $newTotal, 2));

        if ($excess > 0 && ! $request->boolean('accept_no_refund')) {
            $problem = 'That date costs ₱' . number_format($newTotal, 2) . ', which is ₱' . number_format($excess, 2)
                . ' less than the ₱' . number_format((float) $booking->amount_paid, 2) . ' you have already paid. '
                . 'Payments are non-refundable, so the difference will not be returned. '
                . 'Tick the box below to move the booking anyway, or choose another date.';

            return back()->withErrors(['accept_no_refund' => $problem])->withInput();
        }

        // Availability check + ang paglipat mismo ng petsa ay iisang
        // atomic na hakbang (tingnan ang Booking::reserveSlot()) —
        // kung hindi, kayang sumingit ng ibang guest sa target na slot
        // sa pagitan ng check at ng UPDATE. Ang $booking->id ang
        // excludeBookingId: hindi dapat hinaharangan ng booking ang
        // sarili nitong kasalukuyang slot.
        $moved = Booking::reserveSlot(
            $booking->property_id,
            $checkin,
            $checkout,
            function () use ($booking, $checkin, $checkout, $nights, $quote, $newPromo, $newTotal) {
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

                return $booking;
            },
            $booking->id
        );

        if ($moved === null) {
            return back()->withErrors(['dates' => Booking::unavailableMessage(
                $booking->property_id, $checkin, 'Sorry, the Villa is not available on the selected date/slot.',
                checkOut: $checkout
            )])->withInput();
        }

        // Panatilihing tapat ang bilang ng paggamit kapag lumipat ang
        // booking sa ibang promo — kung hindi, mauubos ang `usage_limit`
        // ng isang promong hindi na naman aktuwal na ginagamit.
        if ($oldPromoId !== $newPromo?->id) {
            if ($oldPromoId) {
                Discount::where('id', $oldPromoId)->where('used_count', '>', 0)->decrement('used_count');
            }
            $newPromo?->increment('used_count');
        }

        // WALANG refund sa price difference (tingnan sa itaas). Ang sobra
        // ay nananatili sa booking bilang `amount_paid` na lampas sa
        // `total_amount`, at iyon mismo ang tinitingnan ng
        // flagOverpayment() — na ang abiso ay "malamang nasingil nang
        // dalawang beses ang guest". Mali iyon dito at magtutulak sa
        // admin na ibalik ang perang hindi dapat ibalik. Kaya minamarkahan
        // nang naabisuhan ito bago ang recalculation, at ang tamang
        // paliwanag ay nasa "Booking Rescheduled" na abiso sa ibaba.
        // Query update, gaya ng sa flagOverpayment(): hindi fillable ang
        // column at hindi dapat pumutok ang saving() hook.
        if ($excess > 0) {
            Booking::whereKey($booking->id)->update(['overpayment_notified_at' => now()]);
            $booking->overpayment_notified_at = now();
        }

        $booking->recalculateFinancials();
        $booking->refresh();

        StaffLog::record('booking_rescheduled', 'bookings', $booking->id,
            "Guest rescheduled booking {$oldRef} from {$oldDates} to " .
            $checkin->format('M d, Y') . ' — ' . $checkout->format('M d, Y'));

        NotificationHelper::notifyAdmin(
            "Booking Rescheduled — {$booking->booking_ref}",
            ($booking->user->full_name ?? 'Guest') . " rescheduled their booking from {$oldDates} to " .
            $checkin->format('M d, Y') . ' — ' . $checkout->format('M d, Y') . '.'
            . ($excess > 0
                ? ' The new slot costs ₱' . number_format($excess, 2) . ' less than the guest has paid. '
                    . 'Payments are non-refundable, so nothing was refunded and the guest agreed to that. '
                    . 'This is not a double charge.'
                : ''),
            route('admin.bookings.show', $booking, false)
        );

        $message = "Booking {$booking->booking_ref} has been rescheduled to " . $checkin->format('M d, Y') . '.';
        if ($booking->balance_due > 0) {
            $message .= ' An additional ₱' . number_format($booking->balance_due, 2) . ' is now due.';
        } elseif ($excess > 0) {
            $message .= ' As you confirmed, the ₱' . number_format($excess, 2) . ' price difference is not refunded.';
        }

        $remaining = $booking->reschedulesRemaining();
        $message .= $remaining > 0
            ? " You have {$remaining} reschedule" . ($remaining === 1 ? '' : 's') . ' left for this booking.'
            : ' This was your last available reschedule for this booking.';

        return redirect()->route('customer.bookings.show', $booking)->with('success', $message);
    }
}
