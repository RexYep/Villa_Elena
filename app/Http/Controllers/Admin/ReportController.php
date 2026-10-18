<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Prescriptive\OccupancyCalendar;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reports for one villa that is sold by slot (v7.69).
 *
 * Three date bases are in play, and the page says so under the period:
 *
 *   bookings   by the date they were MADE      (`bookings.created_at`)
 *   money      by the date it was PAID         (`payments.payment_date`)
 *   occupancy  by the date of the STAY         (slots booked ÷ slots offered)
 *
 * The page, the PDF and the Excel file all read buildReportData(), so a
 * figure added here must be added to all three or they will disagree.
 */
class ReportController extends Controller
{
    private const PERIODS = ['today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];

    /** Occupancy walks the range a date at a time, so a custom range is bounded. */
    private const MAX_CUSTOM_DAYS = 1096;

    /** The bookings a count of "made and still standing" leaves out. */
    private const NOT_STANDING = ['cancelled', 'no_show'];

    /** Same wording as the dashboard's donut and the bookings list. */
    private const SOURCE_LABELS = [
        'online' => 'Online',
        'walk_in' => 'Walk-in',
        'phone' => 'Phone',
        'partner' => 'Others',
    ];

    private const STATUS_LABELS = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked In',
        'checked_out' => 'Checked Out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];

    public function index(Request $request)
    {
        return view('admin.reports.index', $this->buildReportData($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildReportData($request);

        if ($data['rangeProblem']) {
            return $this->backToReport($request);
        }

        // Subsetting: the view uses DejaVu Sans for the peso sign, and the
        // whole font embedded makes a one-page report an 880 KB download.
        $pdf = Pdf::loadView('admin.reports.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->exportName($data, 'pdf'));
    }

    public function exportExcel(Request $request)
    {
        $data = $this->buildReportData($request);

        if ($data['rangeProblem']) {
            return $this->backToReport($request);
        }

        return Excel::download(new ReportExport($data), $this->exportName($data, 'xlsx'));
    }

    /**
     * A file for a period the admin did not ask for is worse than no file:
     * its name and heading would say "this month" for a range they typed.
     * The page shows the problem, so send them there with the same query.
     */
    private function backToReport(Request $request)
    {
        return redirect()->route('admin.reports.index', $request->query());
    }

    private function exportName(array $data, string $extension): string
    {
        return 'villa-elena-report-'.$data['from']->format('Y-m-d').'-to-'.$data['to']->format('Y-m-d').'.'.$extension;
    }

    private function buildReportData(Request $request): array
    {
        [$period, $from, $to, $rangeProblem, $customFrom, $customTo] = $this->resolvePeriod($request);

        // ── Money, by the date it was paid ─────────────────────────
        //
        // One row per day for each side. The card's three figures and the
        // period chart are all read from these two lists, so the bars
        // always add up to Net Revenue.
        //
        // collected() and refunds() are the Payment model's one definition
        // of revenue, shared with the dashboard — including why a refund is
        // counted whatever its status. Don't restate the filters here.
        $byDay = fn ($payments) => $payments->whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as day, SUM(amount) as total')
            ->groupBy('day')->pluck('total', 'day');

        $collectedByDay = $byDay(Payment::collected());
        $refundsByDay = $byDay(Payment::refunds());

        $totalRevenue = (float) $collectedByDay->sum();
        $totalRefunds = (float) $refundsByDay->sum();
        $netRevenue = $totalRevenue - $totalRefunds;

        [$revenueSeries, $revenueUnit] = $this->revenueSeries($from, $to, $collectedByDay, $refundsByDay);

        // ── Bookings, by the date they were made ───────────────────
        //
        // Loaded once and counted here: the status and source charts, the
        // slot table and the average are all cuts of the same rows.
        $bookings = Booking::whereBetween('created_at', [$from, $to])->get();
        $standing = $bookings->whereNotIn('status', self::NOT_STANDING);

        $totalBookings = $bookings->count();
        $activeBookings = $standing->count();
        $cancelledBookings = $bookings->where('status', 'cancelled')->count();
        $noShowBookings = $bookings->where('status', 'no_show')->count();

        $bookedValue = (float) $standing->sum('total_amount');
        $avgBookingValue = $activeBookings > 0 ? $bookedValue / $activeBookings : 0;

        $bookingsBySource = $this->countBy($bookings, 'source', self::SOURCE_LABELS);
        $bookingsByStatus = $this->countBy($bookings, 'status', self::STATUS_LABELS);
        $bySlot = $this->bySlot($standing, $bookedValue);

        // ── Guests ────────────────────────────────────────────────
        $newGuests = User::where('role', 'customer')->whereBetween('created_at', [$from, $to])->count();

        // ── Last 12 months (not affected by the period) ────────────
        //
        // Counted from the FIRST of this month. `now()->subMonths($i)` on
        // the 29th–31st lands in the wrong month whenever the target month
        // is shorter (Oct 31 − 1 month = "Sep 31" = Oct 1), which repeated
        // some months and skipped others.
        $firstMonth = Carbon::now()->startOfMonth()->subMonths(11);

        // Net, like the card above and the dashboard's chart: a month's bar
        // here equals that month's Net Revenue.
        $revenueMonths = Payment::where('payment_date', '>=', $firstMonth)
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(".Payment::NET_AMOUNT_SQL.') as total')
            ->groupBy('ym')->pluck('total', 'ym');

        $bookingMonths = Booking::where('created_at', '>=', $firstMonth)
            ->whereNotIn('status', self::NOT_STANDING)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')->pluck('total', 'ym');

        $revenueByMonth = [];
        $bookingsByMonth = [];

        for ($month = $firstMonth->copy(), $i = 0; $i < 12; $i++, $month->addMonth()) {
            $key = $month->format('Y-m');
            $label = $month->format('M Y');

            $revenueByMonth[] = ['label' => $label, 'amount' => (float) ($revenueMonths[$key] ?? 0)];
            $bookingsByMonth[] = ['label' => $label, 'count' => (int) ($bookingMonths[$key] ?? 0)];
        }

        // ── Occupancy, by the date of the stay ─────────────────────
        $occupancy = $this->occupancy($from, $to);
        $occupancyRate = $occupancy['offered'] > 0
            ? round($occupancy['booked'] / $occupancy['offered'] * 100, 1)
            : 0;

        // "This Month" on the 11th counts the 12th–31st as well. Compared by
        // date: `$to` is 23:59:59, so `isFuture()` is true for Today itself.
        $runsPastToday = $to->copy()->startOfDay()->gt(Carbon::today());

        return compact(
            'period', 'from', 'to', 'rangeProblem', 'customFrom', 'customTo',
            'totalRevenue', 'totalRefunds', 'netRevenue',
            'revenueSeries', 'revenueUnit',
            'totalBookings', 'activeBookings', 'cancelledBookings', 'noShowBookings',
            'bookedValue', 'avgBookingValue',
            'bookingsBySource', 'bookingsByStatus', 'bySlot',
            'newGuests', 'occupancy', 'occupancyRate', 'runsPastToday',
            'revenueByMonth', 'bookingsByMonth'
        );
    }

    /**
     * Slots booked ÷ slots offered across the period — the count the
     * Recommendations page calls "booked so far", from the same class.
     *
     * It replaces `sum(num_nights) ÷ (Property::count() × days)`, which
     * divided by four properties (the three rooms are not bookable),
     * counted a date as one unit when it holds two slots, and summed a
     * column that is 1 on every row. October read 12.5% for 17 of 62 slots.
     *
     * @return array{booked: int, offered: int}
     */
    private function occupancy(Carbon $from, Carbon $to): array
    {
        $villa = Property::where('type', 'villa')->first();

        if (! $villa) {
            return ['booked' => 0, 'offered' => 0];
        }

        return OccupancyCalendar::covering($from, $to, $villa)->slotCounts($from, $to);
    }

    /**
     * The standing bookings of the period, cut by what they were booked
     * as. This is the table that used to be "Revenue by Property" — with
     * one villa it could only ever hold one row.
     *
     * `slotKey()` and not the stored times: a Night that extendStay()
     * lengthened is still a Night booking. A legacy row with no slot is
     * listed as "Other times" rather than dropped, so the rows add up.
     *
     * @return array<int, array{label: string, times: string, bookings: int, value: float, average: float, share: float}>
     */
    private function bySlot(Collection $standing, float $bookedValue): array
    {
        $groups = $standing->groupBy(fn (Booking $booking) => $booking->slotKey() ?? 'other');

        $definitions = Booking::SLOTS + ['other' => ['name' => 'Other times', 'times' => 'Does not match a slot']];
        $rows = [];

        foreach ($definitions as $key => $slot) {
            if (! $groups->has($key)) {
                continue;
            }

            $count = $groups[$key]->count();
            $value = (float) $groups[$key]->sum('total_amount');

            $rows[] = [
                'label' => $slot['name'],
                'times' => $slot['times'],
                'bookings' => $count,
                'value' => $value,
                'average' => $value / $count,
                'share' => $bookedValue > 0 ? round($value / $bookedValue * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $labels  in the order the rows should appear
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function countBy(Collection $bookings, string $column, array $labels): array
    {
        $counts = $bookings->countBy($column);
        $rows = [];

        foreach ($labels as $key => $label) {
            if ($counts->has($key)) {
                $rows[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key]];
            }
        }

        return $rows;
    }

    /**
     * Net revenue across the period, as bars the chart can hold: a day
     * each up to two months, a week each up to six, then a month each.
     * The old chart stopped at 60 days whatever the period, so "This Year"
     * drew January and February only — blank, while the figure beside it
     * said ₱237,162.
     *
     * A bar can be below zero: a refund is dated when it was issued, which
     * may be a day (or a month) that collected less than it gave back.
     *
     * @return array{0: array<int, array{label: string, amount: float}>, 1: string}
     */
    private function revenueSeries(Carbon $from, Carbon $to, Collection $collectedByDay, Collection $refundsByDay): array
    {
        $first = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        $days = (int) round($first->diffInDays($last)) + 1;

        $unit = match (true) {
            $days <= 62 => 'day',
            $days <= 186 => 'week',
            default => 'month',
        };

        $series = [];

        for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
            $bucket = match ($unit) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
                'month' => $date->format('Y-m'),
            };

            // A week is labelled by its first date inside the period.
            $series[$bucket] ??= [
                'label' => $unit === 'month' ? $date->format('M Y') : $date->format('M d'),
                'amount' => 0.0,
            ];

            $day = $date->format('Y-m-d');

            $series[$bucket]['amount'] += (float) ($collectedByDay[$day] ?? 0) - (float) ($refundsByDay[$day] ?? 0);
        }

        // Whole centavos: a sum of floats can leave a bar at 0.0000001.
        $series = array_map(fn ($bar) => ['amount' => round($bar['amount'], 2)] + $bar, array_values($series));

        return [$series, $unit];
    }

    /**
     * The period the request asks for.
     *
     * A custom range that cannot be used falls back to this month and
     * says why, instead of a 500 (`from=abc` threw from Carbon::parse) or
     * a page of silent zeros. From after To is simply read the other way
     * round: the period line shows the dates, so nothing is hidden.
     *
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: ?string, 4: ?string, 5: ?string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', 'this_month');

        if (! is_string($period) || ! in_array($period, self::PERIODS, true)) {
            $period = 'this_month';
        }

        $problem = null;
        $customFrom = null;
        $customTo = null;

        if ($period === 'custom') {
            $start = $this->parseDate($request->query('from'));
            $end = $this->parseDate($request->query('to'));

            $customFrom = $start?->toDateString();
            $customTo = $end?->toDateString();

            if (! $start || ! $end) {
                $problem = 'Choose both a From date and a To date for a custom range. Showing this month instead.';
            } else {
                if ($start->gt($end)) {
                    [$start, $end] = [$end, $start];
                    [$customFrom, $customTo] = [$customTo, $customFrom];
                }

                if ($start->diffInDays($end) + 1 > self::MAX_CUSTOM_DAYS) {
                    $problem = 'A custom range can cover up to three years. Showing this month instead.';
                } else {
                    return ['custom', $start, $end->endOfDay(), null, $customFrom, $customTo];
                }
            }

            $period = 'this_month';
        }

        [$from, $to] = match ($period) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            // startOfMonth() first, for the reason given at "Last 12 months".
            'last_month' => [
                Carbon::now()->startOfMonth()->subMonth(),
                Carbon::now()->startOfMonth()->subMonth()->endOfMonth(),
            ],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };

        return [$period, $from, $to, $problem, $customFrom, $customTo];
    }

    /** A real `Y-m-d` date at midnight, or null — never a guess. */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // "2026-02-31" parses, as March 3.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
