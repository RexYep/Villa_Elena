<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\Recommendation;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;

/**
 * MAINTENANCE — "the villa has to close for a few days; close it when it
 * costs the least."
 *
 *   IF   no maintenance is scheduled yet
 *   THEN of every gap of N days with nothing booked, no block and no public
 *        holiday, suggest the one whose weekdays are usually the quietest
 *        (the earliest such gap when several tie).
 *
 * N is the owner's `prescriptive_maintenance_days` (default 2).
 *
 * It speaks once: when a maintenance block already exists inside the
 * horizon, it suggests nothing. This is advice, not a reminder.
 */
class MaintenanceWindowAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_MAINTENANCE;
    }

    public function generate(OccupancyCalendar $calendar): array
    {
        $days = max(1, (int) OccupancyCalendar::setting('prescriptive_maintenance_days', 2));

        $first = Carbon::today()->addDays((int) config('prescriptive.maintenance_lead_days', 7));
        $last = Carbon::today()->addDays((int) config('prescriptive.maintenance_horizon_days', 60));

        for ($day = Carbon::today(); $day->lte($last); $day->addDay()) {
            if ($calendar->blockReason($day) === 'maintenance') {
                return [];
            }
        }

        $gaps = [];

        for ($start = $first->copy(); $start->copy()->addDays($days - 1)->lte($last); $start->addDay()) {
            if ($gap = $this->freeGap($calendar, $start, $days)) {
                $gaps[] = $gap;
            }
        }

        if (empty($gaps)) {
            return [];
        }

        // Quietest weekdays first; the earliest date breaks a tie.
        usort($gaps, fn (array $a, array $b) => [$a['usual_pct'], $a['start']->timestamp]
            <=> [$b['usual_pct'], $b['start']->timestamp]);

        $best = $gaps[0];
        $busiest = end($gaps);

        // No history at all: every gap looks the same and picking one would
        // be a guess dressed up as a recommendation.
        if ($best['usual_offered'] === 0) {
            return [];
        }

        $window = $this->rangeLabel($best['start'], $best['end']);
        $weekdays = $this->weekdayLabel($best['dates']);

        $facts = [
            sprintf(
                'Checked %d free %d-day gap%s between %s and %s.',
                count($gaps),
                $days,
                count($gaps) === 1 ? '' : 's',
                $first->format('M j'),
                $last->format('M j')
            ),
            sprintf(
                'Usual for %s: %s — %d of %d slots were booked in the last %d days.',
                $weekdays,
                $this->percent($best['usual_pct']),
                $best['usual_booked'],
                $best['usual_offered'],
                $calendar->historyDays()
            ),
        ];

        if (round($busiest['usual_pct']) > round($best['usual_pct'])) {
            $facts[] = sprintf(
                'The busiest free gap (%s) usually fills %s.',
                $this->weekdayLabel($busiest['dates']),
                $this->percent($busiest['usual_pct'])
            );
        }

        $facts[] = 'No bookings, blocks or public holidays fall on these dates.';

        return [[
            'type' => Recommendation::TYPE_MAINTENANCE,
            'title' => "Close the villa for maintenance on {$window}",
            'summary' => sprintf(
                'Nothing is booked on %s, and %s usually %s only %s — the quietest of the free gaps.',
                $days === 1 ? 'this date' : "these {$days} days",
                $weekdays,
                $days === 1 ? 'fills' : 'fill',
                $this->percent($best['usual_pct'])
            ),
            'evidence' => $facts,
            'target_start' => $best['start']->toDateString(),
            'target_end' => $best['end']->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_CREATE_BLOCK,
            'action_payload' => [
                'start_date' => $best['start']->toDateString(),
                'end_date' => $best['end']->toDateString(),
                'reason' => 'maintenance',
                'notes' => "Scheduled from a recommendation — quietest free {$days}-day gap ({$weekdays}).",
            ],
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_MAINTENANCE,
                $best['start']->toDateString(),
                $best['end']->toDateString(),
            ]),
        ]];
    }

    /** The facts for a gap starting here, or null when it is not free. */
    private function freeGap(OccupancyCalendar $calendar, Carbon $start, int $days): ?array
    {
        $dates = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);

            if ($calendar->isBlocked($date) || $calendar->holidayName($date) !== null) {
                return null;
            }

            $slots = $calendar->sellableSlots($date);

            if (empty($slots)) {
                return null;
            }

            foreach ($slots as $slot) {
                // A date the owner nominated for a 22-hour stay is being
                // sold on purpose; closing it is not this rule's call.
                if (Booking::slotRequiresWindow($slot) || $calendar->isTaken($date, $slot)) {
                    return null;
                }
            }

            $dates[] = $date;
        }

        return $calendar->summarise($dates);
    }
}
