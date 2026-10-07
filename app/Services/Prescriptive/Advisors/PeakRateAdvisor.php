<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Recommendation;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;

/**
 * PEAK PRICING — "these dates are in demand, charge more for what is left."
 *
 * Two triggers, one action:
 *
 *   IF   predicted occupancy for a stretch is at or above the busy threshold
 *   THEN raise the rate on that stretch.
 *
 *   IF   a date is a public holiday
 *   THEN raise the rate on the holiday itself (back-to-back holidays
 *        together) — not on the rest of its week.
 *
 * Either way only when the owner has not already set a rate for those dates
 * and no promo is discounting them.
 *
 * Both numbers are the owner's policy (Admin → Settings → Booking Rules →
 * Recommendation Rules): `prescriptive_busy_threshold` (default 70%) and
 * `prescriptive_increase_percent` (default 10%).
 *
 * THE HOLIDAY TRIGGER IS A POLICY, NOT A MEASUREMENT, and the card says so.
 * Six months of history has each holiday in it at most once, which is not
 * enough to claim it measured holiday demand. A first version raised the
 * whole Mon–Thu stretch because its Monday was All Souls' Day, on a week
 * with nothing booked; that is the over-reach the per-date rule removes.
 *
 * The action is a PERCENTAGE `pricing_rules` row. That is only correct
 * because `Property::getPackagePrice()` applies a percentage on top of the
 * NORMAL price for that date and slot — so "+10%" means ₱4,400 on a weekday,
 * ₱6,600 on a weekend, and 10% more for a 22-hour stay. A pricing rule has no
 * slot of its own, so one row covers every slot on its dates, which is what
 * the card says it does.
 */
class PeakRateAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_PEAK_RATE;
    }

    public function generate(OccupancyCalendar $calendar): array
    {
        $threshold = OccupancyCalendar::setting('prescriptive_busy_threshold', 70);
        $percent = OccupancyCalendar::setting('prescriptive_increase_percent', 10);

        if ($percent <= 0) {
            return [];
        }

        $stretches = $calendar->stretches();

        $busy = array_filter(
            $stretches,
            fn (array $stretch) => $stretch['predicted'] >= $threshold && $this->canRaise($stretch, $calendar)
        );

        $cards = [];
        $covered = [];

        foreach ($this->joinSameWeek($busy, $calendar) as $stretch) {
            $cards[] = $this->card(
                $stretch,
                $calendar,
                $percent,
                sprintf(
                    'Predicted occupancy is %s — at or above your %s busy mark — so the %s can be priced higher.',
                    $this->percent($stretch['predicted']),
                    $this->percent($threshold),
                    $this->openSlots($stretch)
                ),
                $this->occupancyFacts($stretch, $calendar),
                [$stretch['week'], $stretch['kind']]
            );

            foreach ($stretch['dates'] as $date) {
                $covered[$date->format('Y-m-d')] = true;
            }
        }

        foreach ($this->holidayRuns($stretches, $covered) as $dates) {
            $run = $calendar->summarise($dates);

            if (! $this->canRaise($run, $calendar)) {
                continue;
            }

            $names = [];
            foreach ($run['holidays'] as $day => $name) {
                $names[] = "{$name} ({$day})";
            }

            $cards[] = $this->card(
                $run,
                $calendar,
                $percent,
                sprintf(
                    '%s %s. Your rule prices holiday dates higher, and %s still open.',
                    implode(' and ', $names),
                    count($names) === 1 ? 'is a public holiday' : 'are public holidays',
                    $run['open'] === 1 ? '1 slot is' : $run['open'].' slots are'
                ),
                [sprintf(
                    'Booked so far: %d of %d slots (%s).',
                    $run['booked'],
                    $run['offered'],
                    $this->percent($run['booked_pct'])
                )],
                ['holiday', $run['start']->toDateString(), $run['end']->toDateString()]
            );
        }

        return $cards;
    }

    /** Something is still open, the owner has not set a rate, and no promo is running. */
    private function canRaise(array $stretch, OccupancyCalendar $calendar): bool
    {
        if ($stretch['open'] <= 0) {
            return false;
        }

        // A rate the owner set for these dates is a decision, not something
        // to advise over.
        foreach ($stretch['dates'] as $date) {
            if ($calendar->hasPricingRule($date)) {
                return false;
            }
        }

        return ! $this->hasPromoOnOpenSlot($stretch, $calendar);
    }

    /**
     * Holiday dates not already inside a busy-stretch card, with holidays on
     * consecutive days kept together (Nov 1 and Nov 2 are one card).
     *
     * @param  array<string, bool>  $covered  'Y-m-d' => true
     * @return array<int, array<int, Carbon>>
     */
    private function holidayRuns(array $stretches, array $covered): array
    {
        $runs = [];
        $current = [];

        foreach ($stretches as $stretch) {
            if (empty($stretch['holidays'])) {
                continue;
            }

            foreach ($stretch['dates'] as $date) {
                $isHoliday = isset($stretch['holidays'][$date->format('M j')]);

                if (! $isHoliday || isset($covered[$date->format('Y-m-d')])) {
                    continue;
                }

                if (! empty($current) && ! end($current)->copy()->addDay()->isSameDay($date)) {
                    $runs[] = $current;
                    $current = [];
                }

                $current[] = $date;
            }
        }

        if (! empty($current)) {
            $runs[] = $current;
        }

        return $runs;
    }

    private function openSlots(array $stretch): string
    {
        return $stretch['open'].' open slot'.($stretch['open'] === 1 ? '' : 's');
    }

    /**
     * @param  array<int, string>  $facts
     * @param  array<int, string>  $identity  what makes this card the same card tomorrow
     */
    private function card(
        array $stretch,
        OccupancyCalendar $calendar,
        float $percent,
        string $summary,
        array $facts,
        array $identity
    ): array {
        $window = $this->rangeLabel($stretch['start'], $stretch['end']);
        $up = $this->number($percent);

        if ($change = $this->priceChange($stretch, $calendar, 1 + $percent / 100)) {
            $facts[] = "At +{$up}%, ".$change;
        }

        $facts[] = 'Bookings already made keep the price they were booked at.';

        return [
            'type' => Recommendation::TYPE_PEAK_RATE,
            'title' => "Raise the rate {$up}% for {$window}",
            'summary' => $summary,
            'evidence' => $facts,
            'target_start' => $stretch['start']->toDateString(),
            'target_end' => $stretch['end']->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_CREATE_PRICING_RULE,
            'action_payload' => [
                'label' => "Peak rate +{$up}% ({$window})",
                'type' => 'percentage',
                'price' => $percent,
                'start_date' => $stretch['start']->toDateString(),
                'end_date' => $stretch['end']->toDateString(),
                'is_active' => 1,
            ],
            'fingerprint' => $this->fingerprint(array_merge(
                [Recommendation::TYPE_PEAK_RATE],
                $identity,
                [$percent]
            )),
        ];
    }
}
