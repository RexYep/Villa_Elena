<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $period    = $request->get('period', 'this_month');
        $dateRange = $this->getDateRange($period, $request);
        $from      = $dateRange['from'];
        $to        = $dateRange['to'];

        // ── Revenue ──────────────────────────────────────────────
        $totalRevenue = Payment::whereBetween('payment_date', [$from, $to])
            ->where('status', 'success')->where('payment_type', '!=', 'refund')->sum('amount');
        $totalRefunds = Payment::whereBetween('payment_date', [$from, $to])
            ->where('status', 'success')->where('payment_type', 'refund')->sum('amount');
        $netRevenue = $totalRevenue - $totalRefunds;

        // ── Bookings ─────────────────────────────────────────────
        $totalBookings     = Booking::whereBetween('created_at', [$from, $to])->count();
        $confirmedBookings = Booking::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'no_show'])->count();
        $cancelledBookings = Booking::whereBetween('created_at', [$from, $to])
            ->where('status', 'cancelled')->count();

        // ── Guests ────────────────────────────────────────────────
        $newGuests = User::where('role', 'customer')->whereBetween('created_at', [$from, $to])->count();

        // ── Revenue by Month (last 12 months) ─────────────────────
        $revenueByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $amount = Payment::whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->where('status', 'success')->where('payment_type', '!=', 'refund')->sum('amount');
            $revenueByMonth[] = ['label' => $month->format('M Y'), 'amount' => (float) $amount];
        }

        // ── Bookings by Month (last 12 months) ────────────────────
        $bookingsByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $count = Booking::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->whereNotIn('status', ['cancelled', 'no_show'])->count();
            $bookingsByMonth[] = ['label' => $month->format('M Y'), 'count' => $count];
        }

        // ── Bookings by Source ─────────────────────────────────────
        $bookingsBySource = Booking::whereBetween('created_at', [$from, $to])
            ->selectRaw('source, COUNT(*) as count')->groupBy('source')
            ->pluck('count', 'source')->toArray();

        // ── Bookings by Status ─────────────────────────────────────
        $bookingsByStatus = Booking::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')->groupBy('status')
            ->pluck('count', 'status')->toArray();

        // ── Top Properties ─────────────────────────────────────────
        $topProperties = Booking::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->selectRaw('property_id, COUNT(*) as bookings, SUM(total_amount) as revenue')
            ->with('property:id,property_name')->groupBy('property_id')
            ->orderByDesc('revenue')->take(5)->get();

        // ── Occupancy Rate ─────────────────────────────────────────
        $propertiesCount  = Property::count();
        $totalDays        = max(1, $from->diffInDays($to) + 1);
        $totalPropDays    = $propertiesCount * $totalDays;
        $occupiedDays     = Booking::whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<=', $to)->where('check_out_date', '>=', $from)->sum('num_nights');
        $occupancyRate    = $totalPropDays > 0 ? round(($occupiedDays / $totalPropDays) * 100, 1) : 0;

        // ── Average Booking Value ──────────────────────────────────
        $avgBookingValue = $confirmedBookings > 0
            ? Booking::whereBetween('created_at', [$from, $to])
                ->whereNotIn('status', ['cancelled','no_show'])->avg('total_amount')
            : 0;

        // ── Daily Revenue for period chart ─────────────────────────
        $dailyRevenue = [];
        $cursor = $from->copy()->startOfDay();
        $limit  = min(60, (int) $from->diffInDays($to) + 1);
        for ($d = 0; $d < $limit; $d++) {
            $amount = Payment::whereDate('payment_date', $cursor->toDateString())
                ->where('status', 'success')->where('payment_type', '!=', 'refund')->sum('amount');
            $dailyRevenue[] = ['label' => $cursor->format('M d'), 'amount' => (float) $amount];
            $cursor->addDay();
        }

        return view('admin.reports.index', compact(
            'period', 'from', 'to',
            'totalRevenue', 'totalRefunds', 'netRevenue',
            'totalBookings', 'confirmedBookings', 'cancelledBookings',
            'newGuests', 'occupancyRate', 'avgBookingValue',
            'revenueByMonth', 'bookingsByMonth',
            'bookingsBySource', 'bookingsByStatus',
            'topProperties', 'dailyRevenue'
        ));
    }

    private function getDateRange(string $period, Request $request): array
    {
        return match($period) {
            'today'      => ['from' => Carbon::today(),                    'to' => Carbon::today()->endOfDay()],
            'this_week'  => ['from' => Carbon::now()->startOfWeek(),       'to' => Carbon::now()->endOfWeek()],
            'last_month' => ['from' => Carbon::now()->subMonth()->startOfMonth(), 'to' => Carbon::now()->subMonth()->endOfMonth()],
            'this_year'  => ['from' => Carbon::now()->startOfYear(),       'to' => Carbon::now()->endOfYear()],
            'custom'     => [
                'from' => Carbon::parse($request->get('from', now()->startOfMonth())),
                'to'   => Carbon::parse($request->get('to',   now()->endOfMonth()))->endOfDay(),
            ],
            default      => ['from' => Carbon::now()->startOfMonth(), 'to' => Carbon::now()->endOfMonth()],
        };
    }
}