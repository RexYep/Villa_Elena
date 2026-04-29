<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use Carbon\Carbon;

class ForecastController extends Controller
{
    public function index(GeminiService $ai)
    {
        // ── DUMMY DATA (last 6 months) ──────────────────────
        $months = [];
        $historicalData = [];

        $dummyBookings = [8, 12, 10, 15, 18, 14];
        $dummyRevenue  = [24000, 36000, 30000, 45000, 54000, 42000];
        $dummyStayed   = [6, 10, 9, 13, 16, 12];

        for ($i = 5; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $label = $date->format('M Y');
            $idx   = 5 - $i;

            $months[] = $label;
            $historicalData[] = [
                'month'    => $label,
                'bookings' => $dummyBookings[$idx],
                'revenue'  => $dummyRevenue[$idx],
                'stayed'   => $dummyStayed[$idx],
            ];
        }

        $totalProperties = 4; // dummy

        // ── BUILD PROMPT ────────────────────────────────────
        $historySummary = '';
        foreach ($historicalData as $d) {
            $historySummary .= "- {$d['month']}: {$d['bookings']} bookings, ₱{$d['revenue']} revenue, {$d['stayed']} guests stayed\n";
        }

        $next3Months = [
            Carbon::now()->addMonth(1)->format('F Y'),
            Carbon::now()->addMonth(2)->format('F Y'),
            Carbon::now()->addMonth(3)->format('F Y'),
        ];

        $prompt = "
You are a hospitality business forecasting analyst for Villa Elena Private Rental Resort in the Philippines.

Based on the following 6-month historical data, provide a forecast for the next 3 months: {$next3Months[0]}, {$next3Months[1]}, and {$next3Months[2]}.

HISTORICAL DATA (last 6 months):
{$historySummary}

RESORT INFO:
- Total Properties: {$totalProperties}
- Location: Philippines (consider Philippine holidays and tourism seasons)

Please provide:
1. Expected number of bookings for each of the next 3 months
2. Expected revenue (in PHP) for each of the next 3 months
3. Expected occupancy rate for each of the next 3 months
4. 3 key factors that will influence performance
5. 2 actionable recommendations to maximize revenue

Be specific with numbers. Format clearly with month headers.
        ";

        $forecast = $ai->ask($prompt);

        return view('admin.forecast.index', compact(
            'months',
            'historicalData',
            'forecast',
            'next3Months'
        ));
    }
}