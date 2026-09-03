<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\GeminiService;
use Carbon\Carbon;
use League\CommonMark\CommonMarkConverter;

class ForecastController extends Controller
{
    public function index(GeminiService $ai)
    {
        // ── REAL DATA (last 6 months, live DB queries) ──────────────
        // "bookings" — lahat ng booking na ang check-in ay nahulog sa
        //   buwang iyon, hindi kasama ang cancelled/no_show (hindi
        //   dapat isama sa performance trend ang mga hindi natuloy).
        // "revenue" — parehong pattern ng DashboardController::index()
        //   (Payment::whereMonth/whereYear, hindi kasama ang refund).
        // "stayed" — subset lang ng "bookings": ilan sa mga iyon ang
        //   talagang natapos (checked_out), bilang completion metric.
        $months = [];
        $historicalData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $label = $date->format('M Y');

            $bookings = Booking::whereYear('check_in_date', $date->year)
                ->whereMonth('check_in_date', $date->month)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->count();

            $revenue = Payment::whereYear('payment_date', $date->year)
                ->whereMonth('payment_date', $date->month)
                ->where('status', 'success')
                ->where('payment_type', '!=', 'refund')
                ->sum('amount');

            $stayed = Booking::whereYear('check_in_date', $date->year)
                ->whereMonth('check_in_date', $date->month)
                ->where('status', 'checked_out')
                ->count();

            $months[] = $label;
            $historicalData[] = [
                'month' => $label,
                'bookings' => $bookings,
                'revenue' => (float) $revenue,
                'stayed' => $stayed,
            ];
        }

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

        // Walang "occupancy rate" / "available properties" — Villa
        // Elena ay ISANG villa lang, exclusive-use, hindi hotel na may
        // maraming hiwalay na bookable na kwarto. Walang kahulugan ang
        // "occupancy rate ng mga property" dito; ang totoong ineresenteng
        // metric ay bookings/revenue lang.
        $prompt = "
You are a hospitality business forecasting analyst for Villa Elena Private Rental Resort in the Philippines.

IMPORTANT CONTEXT: Villa Elena is a SINGLE, EXCLUSIVE-USE villa rented out in its
entirety to one guest group at a time — not a hotel with multiple independently
bookable rooms. Do NOT mention 'occupancy rate', 'available properties', or any
metric implying multiple bookable units — those don't apply to this business model.

Based on the following 6-month historical data, provide a forecast for the next 3 months: {$next3Months[0]}, {$next3Months[1]}, and {$next3Months[2]}.

HISTORICAL DATA (last 6 months):
{$historySummary}

RESORT INFO:
- Location: Philippines (consider Philippine holidays and tourism seasons)

Please provide:
1. Expected number of bookings for each of the next 3 months
2. Expected revenue (in PHP) for each of the next 3 months
3. 3 key factors that will influence performance

Do NOT recommend specific actions, discounts, price changes, or promo campaigns.
Your job here is the OUTLOOK only — what is likely to happen and why. Stop there.

Write a clear, professional, formal business report — the kind a resort owner would
receive from a real analyst, not a casual chat reply. Use proper Markdown: a heading
per month (###), short paragraphs or bullet points under each, and a closing section
for the key factors. Be specific with numbers.
        ";

        // SADYANG HINDI na humihingi ng rekomendasyon ang prompt sa itaas.
        //
        // Dati, hinihingan dito ang AI ng "2 actionable recommendations".
        // Sinusulat iyon ng modelo mula sa pangkalahatang kaalaman nito sa
        // industriya, hindi kinokompyut mula sa datos ng Villa Elena — kaya
        // walang masasagot sa tanong na "saan galing ang numerong iyan?",
        // at hindi ito maaaring isagawa ng sistema.
        //
        // Ang gawaing iyon ay nasa /admin/prescriptive na ngayon, kung saan
        // ang bawat mungkahi ay may kinompyut na inaasahang halaga, may
        // ipinapakitang ebidensya, at may Apply na tunay na gumagawa ng
        // Discount o AvailabilityBlock. Mahalagang MANATILING iisa lang ang
        // pinagmumulan ng payo: kung muling hihingi ang page na ito ng
        // sariling rekomendasyon, magkakasalungat ang dalawang page at
        // mawawalan ng saysay pareho.
        //
        // Multi-month Markdown report — 1024 tokens cuts it off mid-section.
        $forecastRaw = $ai->ask($prompt, 2048);

        // Ang sagot ng AI ay Markdown (may mga heading, bold, listahan) —
        // dating dinidisplay ito bilang plain text sa loob ng
        // white-space:pre-wrap na div, kaya literal na nakikita ang mga
        // "**"/"#" sa halaman sa halip na tunay na bold/heading. Ni-
        // rerender na natin ito ngayon bilang tunay na HTML, para
        // formal at maayos ang itsura sa halip na "hilaw" mula sa AI.
        // `html_input => 'escape'` + `allow_unsafe_links => false` para
        // secure pa rin kahit external (AI-generated) ang content na
        // ito.
        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $forecastHtml = (string) $converter->convert($forecastRaw);

        return view('admin.forecast.index', compact(
            'months',
            'historicalData',
            'forecastHtml',
            'next3Months'
        ));
    }
}
