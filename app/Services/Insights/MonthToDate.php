<?php

namespace App\Services\Insights;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures behind /admin/insights, worked out by rule — the AI only puts
 * them into sentences (v7.83).
 *
 * Until v7.83 the AI was handed ten bare counts, most of them all-time, and
 * wrote "94 booking requests this month" (94 was every booking ever made)
 * and "all 8 guests are repeat visitors" (nothing it was given said so).
 *
 * The page answers "what happened this month so far", against THE SAME DAYS
 * of last month, never the whole of it: on the 22nd, Oct 1–22 is compared
 * with Sep 1–22. Comparing 22 days with 30 makes every month look worse
 * until its last week.
 *
 * Reports' three date bases, so the two pages agree for the same dates:
 *
 *   bookings   by the date they were MADE    (`bookings.created_at`)
 *   money      by the date it was PAID       (`payments.payment_date`, net)
 *   slots      by the date of the STAY       (OccupancyCalendar, as Reports)
 *
 * Nothing here is divided by a figure on another basis.
 */
class MonthToDate
{
    /** The bookings that no longer hold a slot — the same list as Reports' NOT_STANDING. */
    private const NOT_STANDING = ['cancelled', 'no_show'];

    /** Who ended a cancelled booking, in the order the page lists them. */
    public const CANCELLED_BY = [
        'system' => 'Unpaid holds that ran out',
        'guest' => 'Cancelled by the guest',
        'admin' => 'Cancelled by the resort',
        'unknown' => 'Not recorded',
    ];

    /** Same wording as Reports and the dashboard's donut. */
    public const SOURCES = [
        'online' => 'Online',
        'walk_in' => 'Walk-in',
        'phone' => 'Phone',
        'partner' => 'Others',
    ];

    /**
     * @return array{
     *     today: Carbon,
     *     key: string,
     *     current: array<string, mixed>,
     *     previous: array<string, mixed>,
     *     now: array{checked_in: bool, awaiting_payment: int}
     * }
     */
    public function build(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $from = $today->copy()->startOfMonth();

        // The same number of days into last month, capped at its last day:
        // on Mar 30 the comparison is Feb 1–28, not "Feb 30" = Mar 2.
        $previousFrom = $from->copy()->subMonthNoOverflow();
        $previousTo = $previousFrom->copy()->addDays(
            min($today->day, $previousFrom->daysInMonth) - 1
        );

        $villa = Property::where('type', 'villa')->first();

        return [
            'today' => $today,
            'key' => $from->format('Y-m'),
            'current' => $this->period($villa, $from, $today),
            'previous' => $this->period($villa, $previousFrom, $previousTo),
            'now' => [
                'checked_in' => Booking::where('status', 'checked_in')->exists(),
                // A pending booking is a hold the guest has not paid yet.
                'awaiting_payment' => Booking::where('status', 'pending')->count(),
            ],
        ];
    }

    /**
     * Every figure for one run of dates, both ends included.
     *
     * @return array<string, mixed>
     */
    private function period(?Property $villa, Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // ── Bookings, by the date they were made ───────────────────
        $made = Booking::whereBetween('created_at', [$start, $end])
            ->get(['id', 'status', 'source', 'slot', 'check_in_date', 'check_in_time', 'check_out_date', 'check_out_time', 'cancelled_by']);

        $standing = $made->whereNotIn('status', self::NOT_STANDING);
        $cancelled = $made->where('status', 'cancelled');

        // ── Slots, by the date of the stay ─────────────────────────
        $slots = $villa
            ? OccupancyCalendar::covering($from, $to, $villa)->slotCounts($from, $to)
            : ['booked' => 0, 'offered' => 0];

        // ── Money, by the date it was paid ─────────────────────────
        // collected() / refunds() are the Payment model's one definition,
        // shared with Reports and the dashboard.
        $paid = fn ($query) => $query->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);

        $collected = (float) $paid(Payment::collected())->sum('amount');
        $refunds = (float) $paid(Payment::refunds())->sum('amount');

        return [
            'from' => $from->copy(),
            'to' => $to->copy(),
            'label' => $this->rangeLabel($from, $to),
            'days' => (int) $from->diffInDays($to) + 1,

            'made' => $made->count(),
            'standing' => $standing->count(),
            'cancelled' => $cancelled->count(),
            'cancelled_by' => $this->countCancelledBy($cancelled),
            'by_source' => $this->countBy($made, fn (Booking $b) => $b->source, self::SOURCES),
            'by_slot' => $this->countBy(
                $standing,
                fn (Booking $b) => $b->slotKey() ?? 'other',
                array_map(fn ($s) => $s['name'], Booking::SLOTS) + ['other' => 'Other times']
            ),

            'slots_booked' => $slots['booked'],
            'slots_offered' => $slots['offered'],
            'slots_pct' => $slots['offered'] > 0 ? $slots['booked'] / $slots['offered'] * 100 : null,

            'collected' => $collected,
            'refunds' => $refunds,
            'net' => $collected - $refunds,

            'stays' => $this->stays($villa, $from, $to),
            'new_accounts' => User::where('role', 'customer')->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * Stays that checked in (or are due to) on the period's dates, split by
     * whether the guest had a finished stay BEFORE that one.
     *
     * "Finished" is `User::COMPLETED_STAY_STATUS`, the one definition the
     * returning-guest promos use, so this page and those promos never
     * disagree about who is a regular. The stay itself does not count: it
     * must be an earlier check-in date.
     *
     * @return array{total: int, first: int, returning: int}
     */
    private function stays(?Property $villa, Carbon $from, Carbon $to): array
    {
        if (! $villa) {
            return ['total' => 0, 'first' => 0, 'returning' => 0];
        }

        $stays = Booking::where('property_id', $villa->id)
            ->whereNotIn('status', self::NOT_STANDING)
            ->whereBetween('check_in_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'check_in_date']);

        // Each guest's earliest finished stay, one query.
        $firstFinished = Booking::whereIn('user_id', $stays->pluck('user_id')->unique())
            ->where('status', User::COMPLETED_STAY_STATUS)
            ->groupBy('user_id')
            ->selectRaw('user_id, MIN(check_in_date) AS first_date')
            ->pluck('first_date', 'user_id');

        $returning = $stays->filter(function (Booking $stay) use ($firstFinished) {
            $first = $firstFinished[$stay->user_id] ?? null;

            return $first !== null && $first < $stay->check_in_date->toDateString();
        })->count();

        return [
            'total' => $stays->count(),
            'first' => $stays->count() - $returning,
            'returning' => $returning,
        ];
    }

    /**
     * @return array<string, int> every key of CANCELLED_BY, zero included
     */
    private function countCancelledBy(Collection $cancelled): array
    {
        $counts = array_fill_keys(array_keys(self::CANCELLED_BY), 0);

        foreach ($cancelled as $booking) {
            $key = isset(self::CANCELLED_BY[$booking->cancelled_by]) ? $booking->cancelled_by : 'unknown';
            $counts[$key]++;
        }

        return $counts;
    }

    /**
     * @param  array<string, string>  $labels  in the order the rows appear
     * @return array<int, array{key: string, label: string, count: int}> only the non-zero ones
     */
    private function countBy(Collection $bookings, callable $keyOf, array $labels): array
    {
        $counts = $bookings->countBy($keyOf);
        $rows = [];

        foreach ($labels as $key => $label) {
            if ($counts->has($key)) {
                $rows[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key]];
            }
        }

        return $rows;
    }

    private function rangeLabel(Carbon $from, Carbon $to): string
    {
        return $from->isSameDay($to)
            ? $from->format('M j')
            : $from->format('M j').'–'.$to->format('j');
    }
}
