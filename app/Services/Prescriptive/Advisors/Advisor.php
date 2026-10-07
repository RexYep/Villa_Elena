<?php

namespace App\Services\Prescriptive\Advisors;

use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;

/**
 * One decision rule: IF a fact about the calendar, THEN a suggested action.
 *
 * A rule returns ARRAYS of attributes ready to become `Recommendation` rows
 * — it never writes to the database. `PrescriptiveEngine` owns the writing,
 * the dedupe and the expiry, so there is one place to understand when a new
 * rule is added.
 *
 * An EMPTY array is a correct answer. A page that always has something to
 * say is noise; if the calendar is fine, nothing should be suggested.
 */
abstract class Advisor
{
    /** @return array<int, array<string, mixed>> */
    abstract public function generate(OccupancyCalendar $calendar): array;

    /**
     * The `recommendations.type` this rule owns. The engine needs it to know
     * WHICH cards the rule may expire: when a rule fails, its cards stay —
     * silence from a crashed rule is not evidence they stopped being true.
     */
    abstract public function type(): string;

    /** The identity of a suggestion, so a daily run updates it instead of duplicating it. */
    protected function fingerprint(array $parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    /**
     * Stretches of the same week that both qualify become one card
     * ("Oct 12–18") instead of two ("Oct 12–15" and "Oct 16–18").
     *
     * @param  array<int, array>  $stretches  qualifying `OccupancyCalendar::stretches()` rows
     * @return array<int, array>
     */
    protected function joinSameWeek(array $stretches, OccupancyCalendar $calendar): array
    {
        $byWeek = [];

        foreach ($stretches as $stretch) {
            $byWeek[$stretch['week']][] = $stretch;
        }

        $joined = [];

        foreach ($byWeek as $week => $parts) {
            if (count($parts) === 1) {
                $joined[] = $parts[0];

                continue;
            }

            $dates = array_merge(...array_column($parts, 'dates'));
            usort($dates, fn (Carbon $a, Carbon $b) => $a <=> $b);

            $joined[] = $calendar->summarise($dates) + ['week' => $week, 'kind' => 'week'];
        }

        return $joined;
    }

    /** "Oct 12", "Oct 12–18" or "Oct 30 – Nov 1". */
    protected function rangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        return $start->isSameMonth($end)
            ? $start->format('M j').'–'.$end->format('j')
            : $start->format('M j').' – '.$end->format('M j');
    }

    /** "Mon–Thu", "Fri–Sun", "Sun and Mon" — the weekdays a set of dates covers. */
    protected function weekdayLabel(array $dates): string
    {
        // In the order the dates fall, so a Sunday–Monday gap reads "Sun and
        // Mon" rather than being re-sorted into "Mon, Sun".
        $names = [];
        $unbroken = true;
        $previous = null;

        foreach ($dates as $date) {
            if ($previous !== null && ! $previous->copy()->addDay()->isSameDay($date)) {
                $unbroken = false;
            }

            $names[$date->dayOfWeek] ??= $date->format('D');
            $previous = $date;
        }

        $names = array_values($names);

        return match (true) {
            count($names) === 1 => $names[0],
            count($names) === 2 => $names[0].' and '.$names[1],
            $unbroken => $names[0].'–'.end($names),
            default => implode(', ', $names),
        };
    }

    protected function percent(float $value): string
    {
        return round($value).'%';
    }

    /** "10" for 10.0, "12.5" for 12.5 — a setting shown the way it was typed. */
    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function peso(float $amount): string
    {
        return '₱'.number_format($amount, 0);
    }

    /**
     * The three facts every occupancy-based card shows, in the same words.
     *
     * @return array<int, string>
     */
    protected function occupancyFacts(array $stretch, OccupancyCalendar $calendar): array
    {
        return [
            sprintf(
                'Booked so far: %d of %d slots (%s).',
                $stretch['booked'],
                $stretch['offered'],
                $this->percent($stretch['booked_pct'])
            ),
            sprintf(
                'Usual for %s: %s — %d of %d slots were booked in the last %d days.',
                $this->weekdayLabel($stretch['dates']),
                $this->percent($stretch['usual_pct']),
                $stretch['usual_booked'],
                $stretch['usual_offered'],
                $calendar->historyDays()
            ),
            sprintf(
                'Predicted occupancy: %s (the larger of the two).',
                $this->percent($stretch['predicted'])
            ),
        ];
    }

    /** Is a promo already discounting any slot still open in the stretch? */
    protected function hasPromoOnOpenSlot(array $stretch, OccupancyCalendar $calendar): bool
    {
        foreach ($stretch['dates'] as $date) {
            foreach ($calendar->sellableSlots($date) as $slot) {
                if (! $calendar->isTaken($date, $slot) && $calendar->hasPromo($date, $slot)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * "₱4,000 becomes ₱3,600" or "₱4,000–₱6,000 becomes ₱3,600–₱5,400", from
     * the rates of the slots still open in the stretch.
     */
    protected function priceChange(array $stretch, OccupancyCalendar $calendar, float $factor): ?string
    {
        $prices = [];

        foreach ($stretch['dates'] as $date) {
            foreach ($calendar->sellableSlots($date) as $slot) {
                if (! $calendar->isTaken($date, $slot)) {
                    $prices[] = $calendar->price($date, $slot);
                }
            }
        }

        if (empty($prices)) {
            return null;
        }

        $range = fn (float $low, float $high) => $low === $high
            ? $this->peso($low)
            : $this->peso($low).'–'.$this->peso($high);

        return sprintf(
            '%s becomes %s.',
            $range(min($prices), max($prices)),
            $range(min($prices) * $factor, max($prices) * $factor)
        );
    }
}
