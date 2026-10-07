<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Recommendation;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;

/**
 * STAFFING — "two groups are back to back; get the villa cleaned in between."
 *
 *   IF   one booking checks out and the next checks in within
 *        `turnover_gap_hours` (3) of each other, in the next
 *        `turnover_lookahead_days` (7)
 *   AND  no cleaning task has been sent for that checkout yet
 *   THEN send the staff frontdesk a turnover-cleaning task due at the next
 *        check-in.
 *
 * The villa has no staff roster to schedule against, so the housekeeping
 * task is the real staffing decision the system can make.
 *
 * This does not bring back the automatic checkout task that was removed on
 * purpose: nothing is sent until the admin presses the button on the card.
 */
class TurnoverCleanAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_TURNOVER;
    }

    public function generate(OccupancyCalendar $calendar): array
    {
        $maxGapMinutes = (float) config('prescriptive.turnover_gap_hours', 3) * 60;
        $until = Carbon::today()->addDays((int) config('prescriptive.turnover_lookahead_days', 7))->endOfDay();

        $bookings = Booking::where('property_id', $calendar->villa()->id)
            // Only stays that are really happening: an unpaid hold can still vanish.
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereBetween('check_in_date', [Carbon::yesterday()->toDateString(), $until->toDateString()])
            ->get()
            ->sortBy(fn (Booking $booking) => $booking->checkInDateTime()->timestamp)
            ->values();

        $cards = [];

        for ($i = 1; $i < $bookings->count(); $i++) {
            $out = $bookings[$i - 1];
            $in = $bookings[$i];

            $checkOut = $out->checkOutDateTime();
            $checkIn = $in->checkInDateTime();

            if ($checkIn->lte(Carbon::now()) || $checkIn->gt($until)) {
                continue;
            }

            $gapMinutes = $checkOut->diffInMinutes($checkIn, false);

            if ($gapMinutes < 0 || $gapMinutes > $maxGapMinutes) {
                continue;
            }

            if ($this->alreadyHasTask($out)) {
                continue;
            }

            $cards[] = $this->card($out, $in, $checkOut, $checkIn, $gapMinutes);
        }

        return $cards;
    }

    private function alreadyHasTask(Booking $out): bool
    {
        return HousekeepingTask::where('booking_id', $out->id)
            ->where('task_type', 'checkout_clean')
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    private function card(Booking $out, Booking $in, Carbon $checkOut, Carbon $checkIn, float $gapMinutes): array
    {
        $outSlot = $out->slot_name ?? 'current';
        $inSlot = $in->slot_name ?? 'next';
        $hours = round($gapMinutes / 60, 1);
        $gap = $this->number($hours).' hour'.($hours == 1 ? '' : 's');

        return [
            'type' => Recommendation::TYPE_TURNOVER,
            'title' => sprintf(
                'Schedule a turnover clean on %s at %s',
                $checkOut->format('D, M j'),
                $checkOut->format('g:i A')
            ),
            'summary' => sprintf(
                'The %s booking checks out at %s and the %s booking checks in at %s — %s to get the villa ready.',
                $outSlot,
                $checkOut->format('g:i A'),
                $inSlot,
                $checkIn->format('g:i A'),
                $gap
            ),
            'evidence' => [
                sprintf('Checking out: %s (%s) at %s.', $out->booking_ref, $outSlot, $checkOut->format('g:i A')),
                sprintf(
                    'Checking in: %s (%s) at %s, %d guest%s.',
                    $in->booking_ref,
                    $inSlot,
                    $checkIn->format('g:i A'),
                    (int) $in->num_guests,
                    (int) $in->num_guests === 1 ? '' : 's'
                ),
                'No cleaning task has been sent for this checkout yet.',
            ],
            'target_start' => $checkIn->toDateString(),
            'target_end' => $checkIn->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_CREATE_TASK,
            'action_payload' => [
                'task_type' => 'checkout_clean',
                'title' => 'Turnover clean before the '.$checkIn->format('g:i A').' check-in',
                'priority' => 'urgent',
                'due_date' => $checkIn->toDateString(),
                'due_time' => $checkIn->format('H:i'),
                'notes' => sprintf(
                    '%s checks out at %s; %s checks in at %s.',
                    $out->booking_ref,
                    $checkOut->format('g:i A'),
                    $in->booking_ref,
                    $checkIn->format('g:i A')
                ),
                'out_booking_id' => $out->id,
                'in_booking_id' => $in->id,
            ],
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_TURNOVER,
                $out->id,
                $in->id,
            ]),
        ];
    }
}
