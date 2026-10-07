<?php

namespace App\Services\Prescriptive;

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * The facts every recommendation rule reads. Nothing here is a formula the
 * owner has to trust — each number is a count that can be checked against
 * the calendar by hand.
 *
 * For any set of upcoming dates it answers three questions:
 *
 *   BOOKED SO FAR — slots already booked ÷ slots offered.            (a fact)
 *   USUAL         — how full the same weekdays were in the last
 *                   `history_days`.                                   (history)
 *   PREDICTED     — whichever of the two is larger. A stretch will end at
 *                   least as full as it already is, and otherwise about
 *                   as full as those weekdays usually get.           (forecast)
 *
 * A "slot" is one thing a guest can book: Day and Night on an ordinary
 * date, or the single 22-hour stay on a date the owner nominated for it.
 *
 * TWO THINGS THAT MUST STAY AS THEY ARE:
 *
 * 1. "Taken" is a DATETIME-OVERLAP test, the same one `Booking::hasConflict()`
 *    uses. A 22-hour booking takes that date's Night and the next date's
 *    Day; matching on check-in time alone misses both.
 * 2. "Usual" counts Day and Night only. The 22-hour stay is sold on a few
 *    nominated dates, so it has no weekday pattern to measure — and counting
 *    every overlapping Day or Night booking as 22-hour demand is how it once
 *    showed the highest fill rate in the table with three real bookings.
 */
class OccupancyCalendar
{
    private Property $villa;

    private HolidayCalendar $holidays;

    /** @var array<string, bool>|null 'Y-m-d|slot' => true */
    private ?array $taken = null;

    /** @var array<string, string>|null 'Y-m-d' => reason */
    private ?array $blocked = null;

    /** @var array<string, bool>|null 'Y-m-d' => true */
    private ?array $ruled = null;

    /** @var array<int, array{booked: int, offered: int}>|null keyed by day of week */
    private ?array $history = null;

    /** @var array<string, array<int, string>> */
    private array $offeredCache = [];

    /** @var array<string, array> */
    private array $quoteCache = [];

    public function __construct(?Property $villa = null, ?HolidayCalendar $holidays = null)
    {
        $this->villa = $villa ?? Property::where('type', 'villa')->firstOrFail();
        $this->holidays = $holidays ?? new HolidayCalendar;
    }

    public function villa(): Property
    {
        return $this->villa;
    }

    /** A numeric rule value from Admin → Settings, or its default. */
    public static function setting(string $key, float $default): float
    {
        $value = Setting::get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function historyDays(): int
    {
        return max(30, (int) config('prescriptive.history_days', 180));
    }

    // ── One date ───────────────────────────────────────────────────

    /**
     * What a guest could book on this date: offered there, and not closed
     * by a block. `Booking::blockSpan()` decides the block question, so a
     * block on the next day closes this date's 22-hour stay but not its
     * Night — the same answer every other screen gives.
     *
     * @return array<int, string>
     */
    public function sellableSlots(Carbon $date): array
    {
        $key = $date->format('Y-m-d');

        return $this->offeredCache[$key] ??= array_values(array_filter(
            Booking::slotsOfferedOn($date, $this->villa),
            fn (string $slot) => ! $this->isClosedByBlock($date, $slot)
        ));
    }

    public function isTaken(Carbon $date, string $slot): bool
    {
        $this->loadBookings();

        return isset($this->taken[$date->format('Y-m-d').'|'.$slot]);
    }

    public function isBlocked(Carbon $date): bool
    {
        return $this->blockReason($date) !== null;
    }

    public function blockReason(Carbon $date): ?string
    {
        $this->loadBlocks();

        return $this->blocked[$date->format('Y-m-d')] ?? null;
    }

    public function holidayName(Carbon $date): ?string
    {
        return $this->holidays->name($date);
    }

    /** Is the owner's own pricing rule already set for this date? */
    public function hasPricingRule(Carbon $date): bool
    {
        $this->loadPricingRules();

        return isset($this->ruled[$date->format('Y-m-d')]);
    }

    /** Is a promo that any guest can get already discounting this slot? */
    public function hasPromo(Carbon $date, string $slot): bool
    {
        return $this->quote($date, $slot)['promo'] !== null;
    }

    /** What the slot is charged at today, promos included. */
    public function price(Carbon $date, string $slot): float
    {
        return (float) $this->quote($date, $slot)['total'];
    }

    /**
     * Priced through `Property::quoteFor()`, the one source of prices, and
     * with no guest on purpose: this is about a date, not a person, so a
     * returning-guests-only promo does not count as "already discounted".
     */
    private function quote(Carbon $date, string $slot): array
    {
        $key = $date->format('Y-m-d').'|'.$slot;

        if (isset($this->quoteCache[$key])) {
            return $this->quoteCache[$key];
        }

        [$checkIn] = Booking::slotDateTimes($slot, $date->format('Y-m-d'));

        return $this->quoteCache[$key] = $this->villa->quoteFor($checkIn, $slot);
    }

    // ── A set of dates ─────────────────────────────────────────────

    /**
     * Booked so far, usual and predicted occupancy for these dates.
     *
     * @param  array<int, Carbon>  $dates
     * @return array{
     *     dates: array<int, Carbon>, start: Carbon, end: Carbon,
     *     booked: int, offered: int, open: int, booked_pct: float,
     *     usual_booked: int, usual_offered: int, usual_pct: float,
     *     predicted: float, holidays: array<string, string>
     * }
     */
    public function summarise(array $dates): array
    {
        $booked = 0;
        $offered = 0;
        $holidays = [];
        $weekdays = [];

        foreach ($dates as $date) {
            foreach ($this->sellableSlots($date) as $slot) {
                $offered++;

                if ($this->isTaken($date, $slot)) {
                    $booked++;
                }
            }

            if ($name = $this->holidayName($date)) {
                $holidays[$date->format('M j')] = $name;
            }

            $weekdays[$date->dayOfWeek] = true;
        }

        // Each weekday is counted once however many times it appears: three
        // Tuesdays share one Tuesday history, they are not three samples.
        $usualBooked = 0;
        $usualOffered = 0;

        foreach (array_keys($weekdays) as $dow) {
            $usualBooked += $this->weekdayHistory()[$dow]['booked'];
            $usualOffered += $this->weekdayHistory()[$dow]['offered'];
        }

        $bookedPct = $offered > 0 ? $booked / $offered * 100 : 0.0;
        $usualPct = $usualOffered > 0 ? $usualBooked / $usualOffered * 100 : 0.0;

        return [
            'dates' => $dates,
            'start' => $dates[0]->copy(),
            'end' => end($dates)->copy(),
            'booked' => $booked,
            'offered' => $offered,
            'open' => $offered - $booked,
            'booked_pct' => $bookedPct,
            'usual_booked' => $usualBooked,
            'usual_offered' => $usualOffered,
            'usual_pct' => $usualPct,
            'predicted' => max($bookedPct, $usualPct),
            'holidays' => $holidays,
        ];
    }

    /**
     * The upcoming calendar cut into the stretches the pricing rules judge:
     * each week's weekdays (Mon–Thu) and its weekend (Fri–Sun). Those are
     * the two groups the villa already prices differently.
     *
     * A stretch is identified by its week and kind, never by its first
     * date, so the same stretch keeps the same identity from one day to the
     * next — which is what lets a dismissed card stay dismissed.
     *
     * @return array<int, array> each a `summarise()` result plus `week` and `kind`
     */
    public function stretches(): array
    {
        $first = Carbon::today()->addDays((int) config('prescriptive.min_lead_days', 2));
        $last = Carbon::today()->addDays((int) config('prescriptive.lookahead_days', 30));

        // Finish the stretch the range ends in, rather than judging half of it.
        $last = $last->copy()->addDays(match (true) {
            $last->dayOfWeekIso <= 4 => 4 - $last->dayOfWeekIso,
            default => 7 - $last->dayOfWeekIso,
        });

        $groups = [];

        for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
            // A blocked date has nothing to sell, so it is not part of the
            // stretch — a card should not name a date the villa is closed.
            if (empty($this->sellableSlots($date))) {
                continue;
            }

            $week = $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $kind = $date->dayOfWeekIso <= 4 ? 'weekdays' : 'weekend';

            $groups[$week.'|'.$kind][] = $date->copy();
        }

        $stretches = [];

        foreach ($groups as $key => $dates) {
            [$week, $kind] = explode('|', $key);

            $stretches[] = $this->summarise($dates) + ['week' => $week, 'kind' => $kind];
        }

        return $stretches;
    }

    /**
     * How many Day and Night slots were offered, and how many were taken,
     * on each weekday over the last `history_days`.
     *
     * Dates that were blocked are left out of both counts. They were never
     * for sale, so counting them as "not booked" would make a weekday look
     * weaker than it is.
     *
     * @return array<int, array{booked: int, offered: int}>
     */
    public function weekdayHistory(): array
    {
        if ($this->history !== null) {
            return $this->history;
        }

        $this->history = array_fill(0, 7, ['booked' => 0, 'offered' => 0]);

        $start = Carbon::today()->subDays($this->historyDays());
        $end = Carbon::today()->subDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($this->isBlocked($date)) {
                continue;
            }

            foreach (['day', 'night'] as $slot) {
                $this->history[$date->dayOfWeek]['offered']++;

                if ($this->isTaken($date, $slot)) {
                    $this->history[$date->dayOfWeek]['booked']++;
                }
            }
        }

        return $this->history;
    }

    // ── Preloading (one query each, not one per date) ──────────────

    private function isClosedByBlock(Carbon $date, string $slot): bool
    {
        [$checkIn, $checkOut] = Booking::slotDateTimes($slot, $date->format('Y-m-d'));
        [$from, $to] = Booking::blockSpan($checkIn, $checkOut);

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($this->isBlocked($day)) {
                return true;
            }
        }

        return false;
    }

    private function rangeStart(): Carbon
    {
        return Carbon::today()->subDays($this->historyDays() + 1);
    }

    private function rangeEnd(): Carbon
    {
        $days = max(
            (int) config('prescriptive.lookahead_days', 30) + 7,
            (int) config('prescriptive.maintenance_horizon_days', 60)
        );

        return Carbon::today()->addDays($days + 1);
    }

    private function loadBookings(): void
    {
        if ($this->taken !== null) {
            return;
        }

        $this->taken = [];

        Booking::where('property_id', $this->villa->id)
            // The same statuses hasConflict() treats as holding a slot. A
            // pending booking counts: the guest is at checkout right now.
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('check_in_date', [$this->rangeStart()->toDateString(), $this->rangeEnd()->toDateString()])
            ->get(['check_in_date', 'check_in_time', 'check_out_date', 'check_out_time'])
            ->each(function (Booking $booking) {
                $start = $booking->checkInDateTime();
                $end = $booking->checkOutDateTime();

                // From the day before: a 22-hour stay that began yesterday
                // still reaches into this date's Day slot.
                $cursor = $booking->check_in_date->copy()->subDay();
                $last = $booking->check_out_date->copy()->addDay();

                while ($cursor->lte($last)) {
                    $date = $cursor->format('Y-m-d');

                    foreach (array_keys(Booking::SLOTS) as $slot) {
                        [$slotStart, $slotEnd] = Booking::slotDateTimes($slot, $date);

                        if ($slotStart->lt($end) && $slotEnd->gt($start)) {
                            $this->taken[$date.'|'.$slot] = true;
                        }
                    }

                    $cursor->addDay();
                }
            });
    }

    private function loadBlocks(): void
    {
        if ($this->blocked !== null) {
            return;
        }

        $this->blocked = [];

        AvailabilityBlock::where('property_id', $this->villa->id)
            ->whereDate('end_date', '>=', $this->rangeStart()->toDateString())
            ->whereDate('start_date', '<=', $this->rangeEnd()->toDateString())
            ->get(['start_date', 'end_date', 'reason'])
            ->each(function ($block) {
                for ($day = $block->start_date->copy(); $day->lte($block->end_date); $day->addDay()) {
                    $this->blocked[$day->format('Y-m-d')] = $block->reason;
                }
            });
    }

    private function loadPricingRules(): void
    {
        if ($this->ruled !== null) {
            return;
        }

        $this->ruled = [];

        $this->villa->pricingRules()
            ->where('is_active', 1)
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->whereDate('start_date', '<=', $this->rangeEnd()->toDateString())
            ->get(['start_date', 'end_date'])
            ->each(function ($rule) {
                for ($day = $rule->start_date->copy(); $day->lte($rule->end_date); $day->addDay()) {
                    $this->ruled[$day->format('Y-m-d')] = true;
                }
            });
    }
}
