<?php

namespace App\Services\Forecast;

use App\Models\Booking;
use App\Models\Discount;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;

/**
 * The numbers behind /admin/forecast, worked out by rule — the AI only
 * explains them (v7.82).
 *
 * Until v7.82 the AI was handed six monthly totals and asked to invent the
 * next three. It divided revenue by payment date by bookings by stay date,
 * got "₱3,030 per booking" (below the cheapest rate) and multiplied every
 * month by it. Nothing it wrote could be traced back to a method.
 *
 * Every month is counted in SLOTS, the same unit as Reports' occupancy and
 * the Recommendations page: one Day, Night or 22-hour stay the villa can
 * sell. Per month:
 *
 *   offered   slots for sale (blocked dates and unpriced slots excluded)
 *   booked    slots already taken — a datetime-overlap test, so a 22-hour
 *             stay takes its own date and the next date's Day
 *   expected  for dates still ahead, the Recommendations rule: the larger of
 *             "booked so far" and "usual" (the same weekdays over the last
 *             `history_days`). Dates already past keep what they got.
 *
 * Money is the VALUE OF STAYS by the month of the stay — the price of the
 * bookings checking in that month, discounts taken off. It is not money
 * received: Dashboard and Reports count that by payment date. Dividing one
 * basis by the other is what produced the ₱3,030.
 *
 *   value booked    sum of `total_amount` of the month's live bookings
 *   value expected  value booked + (expected − booked) × the average price
 *                   the month's open slots are listed at today (quoteFor())
 */
class MonthlyOutlook
{
    /** Months of history shown, the current one included. */
    public const HISTORY_MONTHS = 6;

    /** Months forecast after the current one. */
    public const AHEAD_MONTHS = 3;

    /** @var \Illuminate\Support\Collection<int, Discount>|null active promos, read once */
    private ?\Illuminate\Support\Collection $promos = null;

    /** @var array<string, float> see price() */
    private array $prices = [];

    /**
     * @return array{
     *     today: Carbon,
     *     usual_pct: float,
     *     history_days: int,
     *     months: array<int, array<string, mixed>>
     * }
     */
    public function build(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $thisMonth = $today->copy()->startOfMonth();

        $first = $thisMonth->copy()->subMonths(self::HISTORY_MONTHS - 1);
        $last = $thisMonth->copy()->addMonths(self::AHEAD_MONTHS)->endOfMonth()->startOfDay();

        // Loaded from far enough back to answer "usual" too: weekdayHistory()
        // reads the last `history_days` through the same loaded bookings.
        $historyStart = $today->copy()->subDays(max(30, (int) config('prescriptive.history_days', 180)) + 1);
        $calendar = OccupancyCalendar::covering($first->min($historyStart), $last);

        $values = $this->bookedValues($calendar, $first, $last);

        $months = [];

        for ($i = -(self::HISTORY_MONTHS - 1); $i <= self::AHEAD_MONTHS; $i++) {
            $start = $thisMonth->copy()->addMonths($i);
            $months[] = $this->month($calendar, $start, $today, $values[$start->format('Y-m')] ?? 0.0);
        }

        // The usual rate across all seven weekdays, for the footnote.
        $usualBooked = 0;
        $usualOffered = 0;

        foreach ($calendar->weekdayHistory() as $row) {
            $usualBooked += $row['booked'];
            $usualOffered += $row['offered'];
        }

        return [
            'today' => $today,
            'usual_pct' => $usualOffered > 0 ? $usualBooked / $usualOffered * 100 : 0.0,
            'history_days' => $calendar->historyDays(),
            'months' => $months,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function month(OccupancyCalendar $calendar, Carbon $start, Carbon $today, float $valueBooked): array
    {
        $end = $start->copy()->endOfMonth()->startOfDay();

        $state = match (true) {
            $end->lt($today) => 'past',
            $start->gt($today) => 'ahead',
            default => 'current',
        };

        $offered = 0;
        $booked = 0;
        $ahead = [];
        $openPrices = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $isAhead = $date->gte($today);

            if ($isAhead) {
                $ahead[] = $date->copy();
            }

            foreach ($calendar->sellableSlots($date) as $slot) {
                $offered++;

                if ($calendar->isTaken($date, $slot)) {
                    $booked++;
                } elseif ($isAhead) {
                    $openPrices[] = $this->price($calendar, $date, $slot);
                }
            }
        }

        $row = [
            'key' => $start->format('Y-m'),
            'label' => $start->format('M Y'),
            'name' => $start->format('F Y'),
            'state' => $state,
            'days_left' => count($ahead),
            'offered' => $offered,
            'booked' => $booked,
            'value_booked' => round($valueBooked, 2),
            // Filled below for a month with dates still ahead.
            'ahead_offered' => 0,
            'ahead_booked' => 0,
            'booked_pct' => $offered > 0 ? $booked / $offered * 100 : 0.0,
            'usual_pct' => null,
            'expected' => $booked,
            'extra' => 0,
            'open_price' => null,
            'value_expected' => round($valueBooked, 2),
            'holidays' => [],
        ];

        if ($ahead === []) {
            return $row;
        }

        $summary = $calendar->summarise($ahead);

        // Expected slots for the dates still ahead, never fewer than are
        // already booked there. Dates already past keep what they got.
        $aheadExpected = max(
            $summary['booked'],
            (int) round($summary['predicted'] / 100 * $summary['offered'])
        );
        $extra = $aheadExpected - $summary['booked'];
        $openPrice = $openPrices === [] ? null : array_sum($openPrices) / count($openPrices);

        // Rounding can ask for one slot more than is open; never promise a
        // slot that cannot be sold.
        $extra = min($extra, count($openPrices));

        $holidays = [];

        foreach ($ahead as $date) {
            if ($name = $calendar->holidayName($date)) {
                $holidays[] = ['date' => $date->copy(), 'name' => $name];
            }
        }

        return array_merge($row, [
            'ahead_offered' => $summary['offered'],
            'ahead_booked' => $summary['booked'],
            'usual_pct' => $summary['usual_pct'],
            'expected' => $booked + $extra,
            'extra' => $extra,
            'open_price' => $openPrice,
            'value_expected' => round($valueBooked + $extra * ($openPrice ?? 0), 2),
            'holidays' => $holidays,
        ]);
    }

    /**
     * What an open slot is listed at today, through `quoteFor()` (via the
     * calendar) — never a price worked out here.
     *
     * Pricing ~240 open slots one by one cost ~700 queries (each quote reads
     * pricing rules, promos and slot windows). So a quote is reused for
     * another date only when nothing that quoteFor() reads can differ: the
     * same slot, the same weekday (which fixes the regular/peak tier, since a
     * slot's check-in time is fixed), no pricing rule on either date, and the
     * same promos valid on both. A date with a pricing rule is always quoted
     * on its own. If quoteFor() ever reads something new about a date (a
     * holiday rate, say), add it to this key.
     */
    private function price(OccupancyCalendar $calendar, Carbon $date, string $slot): float
    {
        if ($calendar->hasPricingRule($date)) {
            return $calendar->price($date, $slot);
        }

        $this->promos ??= Discount::where('is_active', 1)->get();

        [$checkIn] = Booking::slotDateTimes($slot, $date->format('Y-m-d'));
        $promoIds = $this->promos
            ->filter(fn (Discount $promo) => $promo->isValidOn($checkIn, $slot))
            ->pluck('id')
            ->implode(',');

        $key = $slot.'|'.$date->dayOfWeek.'|'.$promoIds;

        return $this->prices[$key] ??= $calendar->price($date, $slot);
    }

    /**
     * Value of the live bookings checking in each month, one query.
     * The same statuses OccupancyCalendar counts as taking a slot, so a
     * month's slots and its value describe the same bookings.
     *
     * @return array<string, float> 'Y-m' => pesos
     */
    private function bookedValues(OccupancyCalendar $calendar, Carbon $from, Carbon $to): array
    {
        $values = [];

        Booking::where('property_id', $calendar->villa()->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('check_in_date', [$from->toDateString(), $to->toDateString()])
            ->get(['check_in_date', 'total_amount'])
            ->each(function (Booking $booking) use (&$values) {
                $key = $booking->check_in_date->format('Y-m');
                $values[$key] = ($values[$key] ?? 0.0) + (float) $booking->total_amount;
            });

        return $values;
    }
}
