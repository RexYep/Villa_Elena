<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Recommendation;
use App\Services\Prescriptive\OccupancyCalendar;

/**
 * PROMOTION — "these dates are quiet, offer a promo."
 *
 *   IF   predicted occupancy for a stretch is below the quiet threshold
 *   AND  no public holiday falls in it
 *   AND  no promo is already discounting it
 *   THEN offer the owner's promo percentage on those dates.
 *
 * Both numbers are the owner's policy (Admin → Settings → Booking Rules →
 * Recommendation Rules): `prescriptive_quiet_threshold` (default 30%) and
 * `prescriptive_promo_percent` (default 10%). The rule decides WHEN a promo
 * is worth offering; the owner decides HOW MUCH.
 *
 * Holidays are skipped because they are the dates that fill on their own —
 * a discount there gives money away. `PeakRateAdvisor` handles them.
 */
class QuietDatesPromoAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_PROMO;
    }

    public function generate(OccupancyCalendar $calendar): array
    {
        $threshold = OccupancyCalendar::setting('prescriptive_quiet_threshold', 30);
        $percent = OccupancyCalendar::setting('prescriptive_promo_percent', 10);

        if ($percent <= 0) {
            return [];
        }

        $quiet = array_filter(
            $calendar->stretches(),
            fn (array $stretch) => $stretch['open'] > 0
                && $stretch['predicted'] < $threshold
                && empty($stretch['holidays'])
                && ! $this->hasPromoOnOpenSlot($stretch, $calendar)
        );

        $cards = [];

        foreach ($this->joinSameWeek($quiet, $calendar) as $stretch) {
            $cards[] = $this->card($stretch, $calendar, $threshold, $percent);
        }

        return $cards;
    }

    private function card(array $stretch, OccupancyCalendar $calendar, float $threshold, float $percent): array
    {
        $window = $this->rangeLabel($stretch['start'], $stretch['end']);
        $off = $this->number($percent);

        $facts = $this->occupancyFacts($stretch, $calendar);

        if ($change = $this->priceChange($stretch, $calendar, 1 - $percent / 100)) {
            $facts[] = "With {$off}% off, ".$change;
        }

        return [
            'type' => Recommendation::TYPE_PROMO,
            'title' => "Offer a {$off}% promo for {$window}",
            'summary' => sprintf(
                '%s for these dates, and predicted occupancy is %s — below your %s quiet mark.',
                $stretch['booked'] === 0
                    ? "None of the {$stretch['offered']} slots are booked"
                    : "Only {$stretch['booked']} of {$stretch['offered']} slots are booked",
                $this->percent($stretch['predicted']),
                $this->percent($threshold)
            ),
            'evidence' => $facts,
            'target_start' => $stretch['start']->toDateString(),
            'target_end' => $stretch['end']->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_CREATE_PROMO,
            'action_payload' => [
                'label' => "{$off}% Promo ({$window})",
                'description' => "{$off}% off the villa rate for check-ins {$window}.",
                'type' => 'percentage',
                'value' => $percent,
                'start_date' => $stretch['start']->toDateString(),
                'expiry_date' => $stretch['end']->toDateString(),
                'applies_to' => 'all',
                'is_public' => 1,
                'is_active' => 1,
            ],
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_PROMO,
                $stretch['week'],
                $stretch['kind'],
                $percent,
            ]),
        ];
    }
}
