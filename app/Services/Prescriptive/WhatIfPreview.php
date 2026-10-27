<?php

namespace App\Services\Prescriptive;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Recommendation;
use Carbon\Carbon;

/**
 * "What would the system have recommended back then?" — the Recommendations
 * page's Monthly, Quarterly and Yearly views.
 *
 * A month or a quarter is run AS OF its start (`OccupancyCalendar::asOf()`):
 * only the bookings that existed that day, and "usual" from the
 * `history_days` before it. A year is its twelve months, each run as of its
 * own start — the way the system would really have run through the year.
 *
 * READ ONLY. It runs the history-based rules through
 * `PrescriptiveEngine::preview()`, which writes nothing; no card here can be
 * applied.
 *
 * A period whose 180 days of history reach back before the first booking is
 * NOT run. Those empty days would count as "nobody booked", "usual" would be
 * near zero, and every week would get a promo card.
 */
class WhatIfPreview
{
    private Property $villa;

    private ?Carbon $dataStart = null;

    private bool $dataStartRead = false;

    public function __construct(?Property $villa = null)
    {
        $this->villa = $villa ?? Property::where('type', 'villa')->firstOrFail();
    }

    /** The months, by number, as the dropdown shows them. */
    public const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /**
     * The years a what-if can be shown for: up to this year, each with at
     * least one month that has enough history. The latest first.
     *
     * @return array<int, int>
     */
    public function years(): array
    {
        $start = $this->dataStart();

        if ($start === null) {
            return [];
        }

        $years = [];

        for ($year = Carbon::today()->year; $year >= $start->year; $year--) {
            // December has the most history behind it of any month in the year.
            if ($this->hasEnoughHistory($this->asOfFor(Carbon::create($year, 12, 1)))) {
                $years[] = $year;
            }
        }

        return $years;
    }

    public function month(int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();

        return $this->period($from, $from->copy()->endOfMonth()->startOfDay(), self::MONTHS[$month].' '.$year);
    }

    public function quarter(int $year, int $quarter): array
    {
        $from = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return $this->period($from, $from->copy()->lastOfQuarter()->startOfDay(), 'Q'.$quarter.' '.$year);
    }

    /**
     * The year's twelve monthly what-ifs, and one row per month for the
     * charts and the summary table.
     */
    public function year(int $year): array
    {
        $months = [];
        $cards = 0;

        foreach (self::MONTHS as $number => $name) {
            $period = $this->month($year, $number);
            $cards += count($period['cards'] ?? []);

            $months[$number] = $period + [
                'number' => $number,
                'short' => substr($name, 0, 3),
                'counts' => $this->countByType($period['cards'] ?? []),
            ];
        }

        return [
            'label' => (string) $year,
            'months' => $months,
            'card_count' => $cards,
            'run_months' => count(array_filter($months, fn (array $m) => $m['enough'])),
        ];
    }

    /**
     * The live calendar's stretches, for the Default view's chart: the same
     * next-30-days the nightly rules judge, as of right now.
     */
    public function liveStretches(): array
    {
        return $this->stretchRows(new OccupancyCalendar($this->villa), []);
    }

    // ── One period ─────────────────────────────────────────────────

    private function period(Carbon $from, Carbon $to, string $label): array
    {
        $asOf = $this->asOfFor($from);
        $historyDays = (new OccupancyCalendar($this->villa))->historyDays();
        $historyFrom = $asOf->copy()->subDays($historyDays);

        $base = [
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'dates' => $this->dateSpan($from, $to),
            'as_of' => $asOf,
            'is_past' => $asOf->lt(Carbon::today()),
            'history_dates' => $this->dateSpan($historyFrom, $asOf->copy()->subDay()),
            'history_days' => $historyDays,
        ];

        if (! $this->hasEnoughHistory($asOf)) {
            return $base + [
                'enough' => false,
                'needs_from' => $historyFrom,
                'data_start' => $this->dataStart(),
                'cards' => [],
            ];
        }

        $calendar = (new OccupancyCalendar($this->villa))->asOf($asOf)->withFutureRange($from, $to);
        $engine = new PrescriptiveEngine($calendar);
        $run = $engine->preview($engine->historyAdvisors());

        $cards = array_map(fn (array $attrs) => (new Recommendation)->forceFill($attrs), $run['cards']);
        usort($cards, fn ($a, $b) => [$a->target_start, $a->title] <=> [$b->target_start, $b->title]);

        $stretches = $this->stretchRows($calendar, $cards);
        $dates = array_merge(...array_map(fn (array $s) => $s['dates'], $stretches ?: [['dates' => []]]));

        return $base + [
            'enough' => true,
            'cards' => $cards,
            'failed' => $run['failed'],
            'stretches' => $stretches,
            'summary' => $dates ? $calendar->summarise($dates) : null,
            'weekdays' => $this->weekdayRows($calendar),
        ];
    }

    /**
     * One row per stretch for the chart and its table: the three numbers,
     * and the titles of the promo / peak-rate cards that cover it.
     *
     * @param  array<int, Recommendation>  $cards
     */
    private function stretchRows(OccupancyCalendar $calendar, array $cards): array
    {
        $rows = [];

        foreach ($calendar->stretches() as $stretch) {
            $start = $stretch['start'];
            $end = $stretch['end'];

            $covering = array_values(array_map(
                fn (Recommendation $card) => $card->title,
                array_filter($cards, fn (Recommendation $card) => $card->action_type !== Recommendation::ACTION_CREATE_BLOCK
                    && $card->target_start && $card->target_end
                    && Carbon::parse($card->target_start)->lte($end)
                    && Carbon::parse($card->target_end)->gte($start))
            ));

            $rows[] = [
                'label' => $this->rangeLabel($start, $end),
                'kind' => $stretch['kind'] === 'weekend' ? 'Fri–Sun' : 'Mon–Thu',
                'dates' => $stretch['dates'],
                'booked' => $stretch['booked'],
                'offered' => $stretch['offered'],
                'booked_pct' => round($stretch['booked_pct'], 1),
                'usual_pct' => round($stretch['usual_pct'], 1),
                'predicted' => round($stretch['predicted'], 1),
                'cards' => $covering,
            ];
        }

        return $rows;
    }

    /** "Usual by weekday", Monday first as the stretches are. */
    private function weekdayRows(OccupancyCalendar $calendar): array
    {
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $history = $calendar->weekdayHistory();
        $rows = [];

        foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
            $rows[] = ['name' => $names[$dow]] + $history[$dow];
        }

        return $rows;
    }

    /** @param  array<int, Recommendation>  $cards */
    private function countByType(array $cards): array
    {
        $counts = ['promo' => 0, 'peak' => 0, 'maintenance' => 0];

        foreach ($cards as $card) {
            match ($card->action_type) {
                Recommendation::ACTION_CREATE_PROMO => $counts['promo']++,
                Recommendation::ACTION_CREATE_PRICING_RULE => $counts['peak']++,
                Recommendation::ACTION_CREATE_BLOCK => $counts['maintenance']++,
                default => null,
            };
        }

        return $counts;
    }

    // ── Dates ──────────────────────────────────────────────────────

    /**
     * The day a period is run as of: `min_lead_days` before it starts, so
     * the rules (which never judge a date closer than that) cover all of it.
     * Never later than today: a period that has not started is run now.
     */
    private function asOfFor(Carbon $from): Carbon
    {
        return $from->copy()
            ->subDays((int) config('prescriptive.min_lead_days', 2))
            ->min(Carbon::today())
            ->copy();
    }

    private function hasEnoughHistory(Carbon $asOf): bool
    {
        $start = $this->dataStart();
        $days = (new OccupancyCalendar($this->villa))->historyDays();

        return $start !== null && $asOf->copy()->subDays($days)->gte($start);
    }

    /**
     * Where the booking history starts: the first day of the month of the
     * earliest booking. A month, not the exact day, so a history that starts
     * on Jul 3 is not refused for missing Jul 1–2.
     */
    public function dataStart(): ?Carbon
    {
        if (! $this->dataStartRead) {
            $first = Booking::where('property_id', $this->villa->id)->min('check_in_date');
            $this->dataStart = $first ? Carbon::parse($first)->startOfMonth() : null;
            $this->dataStartRead = true;
        }

        return $this->dataStart?->copy();
    }

    /** "Jan 1 – Mar 31, 2026" */
    private function dateSpan(Carbon $from, Carbon $to): string
    {
        return $from->year === $to->year
            ? $from->format('M j').' – '.$to->format('M j, Y')
            : $from->format('M j, Y').' – '.$to->format('M j, Y');
    }

    /** "Jan 5–8" or "Jan 30 – Feb 1" */
    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        return $start->isSameMonth($end)
            ? $start->format('M j').'–'.$end->format('j')
            : $start->format('M j').' – '.$end->format('M j');
    }
}
