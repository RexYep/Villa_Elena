<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ThrottlesAiRefresh;
use App\Http\Controllers\Controller;
use App\Services\AiReportStore;
use App\Services\GeminiService;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class InsightsController extends Controller
{
    use ThrottlesAiRefresh;

    /**
     * Reads the stored insights; calls Groq only when there are none yet.
     *
     * The stats below are plain DB counts and are recomputed on every visit —
     * they're cheap and they should be live. Only the AI's reading of them is
     * stored, and `AiReportStore` explains why.
     */
    public function index(AiReportStore $store, GeminiService $gemini)
    {
        $stats = $this->gatherStats();

        $report = $store->remember(
            AiReportStore::INSIGHTS,
            fn () => $gemini->ask($this->prompt($stats))
        );

        return view('admin.insights.index', $stats + [
            // NULL kapag hindi pa tumugon ang AI kahit kailan. Ang view na
            // ang nagpapasya kung ano ang makikita ng admin — hindi
            // ipinapakita ang tunay na error, nasa log iyon
            // (`storage/logs/laravel.log`, "Groq API call failed").
            'insights' => $report['text'] ?? null,
            'generatedAt' => $report['generated_at'] ?? null,
        ]);
    }

    /**
     * The Refresh button. The ONLY path that spends a Groq request on purpose.
     */
    public function refresh(AiReportStore $store, GeminiService $gemini)
    {
        if ($wait = $this->aiRefreshCooldown(AiReportStore::INSIGHTS)) {
            return back()->with('error', $this->aiRefreshCooldownMessage('insights report', $wait));
        }

        $stats = $this->gatherStats();

        // NULL means "not regenerated" — `refresh()` leaves the previous report
        // in place. Say so plainly rather than flashing a success message over
        // text that didn't change.
        $refreshed = $store->refresh(
            AiReportStore::INSIGHTS,
            fn () => $gemini->ask($this->prompt($stats))
        );

        return $refreshed === null
            ? back()->with('error', 'Insights could not be refreshed right now — the analysis service did not respond. The figures above are unaffected.')
            : back()->with('success', 'Insights refreshed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherStats(): array
    {
        $now = Carbon::now();

        // --- Gather Data ---
        $totalBookings      = Booking::count();
        $pendingBookings    = Booking::where('status', 'pending')->count();
        $confirmedBookings  = Booking::where('status', 'confirmed')->count();
        $checkedIn          = Booking::where('status', 'checked_in')->count();
        $cancelledBookings  = Booking::where('status', 'cancelled')->count();

        // Bug fix: dating `created_at` (kailan na-insert ang row) ang
        // ginagamit dito, hindi `payment_date` (kailan talaga nangyari
        // ang bayad) — parehong pattern ito ng DashboardController at
        // ForecastController. Dahil ito, kahit anong payment na na-
        // record NGAYON (kahit para sa lumang booking, hal. backfill o
        // data seed) ay tila "this month" palagi, at ang totoong nakaraang
        // buwan ay laging PHP 0 kahit may totoong revenue noon. Dinagdag
        // din ang `status = success` at hindi refund, para hindi kasama
        // ang mga pending/failed na payment o ma-double-count ang refund.
        $revenueThisMonth   = Payment::whereMonth('payment_date', $now->month)
                                ->whereYear('payment_date', $now->year)
                                ->where('status', 'success')
                                ->where('payment_type', '!=', 'refund')
                                ->sum('amount');

        $revenueLastMonth   = Payment::whereMonth('payment_date', $now->copy()->subMonth()->month)
                                ->whereYear('payment_date', $now->copy()->subMonth()->year)
                                ->where('status', 'success')
                                ->where('payment_type', '!=', 'refund')
                                ->sum('amount');

        $totalGuests        = User::where('role', 'customer')->count();
        $newGuestsThisMonth = User::where('role', 'customer')
                                ->whereMonth('created_at', $now->month)
                                ->count();

        // Bug fix: "Occupancy Rate" dati ay Property::where('status',
        // 'occupied')->count() / Property::count() — pero 4 lang ang
        // total properties dahil kasama sa bilang na iyon ang 3 Room na
        // hindi naman hiwalay na bookable (info-only records, bahagi ng
        // Villa). Isang Villa lang talaga ang totoong bookable, kaya
        // walang kahulugan ang "occupancy rate" na base sa dami ng
        // property — laging halos 0% o mababa ito kahit puno ang
        // Villa. Pinalitan ng "Currently Checked In" ($checkedIn sa
        // itaas, 0 o 1 lang dahil isang Villa lang) — mas tapat na
        // representasyon ng totoong kalagayan.

        return compact(
            'now',
            'totalBookings',
            'pendingBookings',
            'confirmedBookings',
            'checkedIn',
            'cancelledBookings',
            'revenueThisMonth',
            'revenueLastMonth',
            'totalGuests',
            'newGuestsThisMonth'
        );
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function prompt(array $stats): string
    {
        // `extract()` so the prompt below stays byte-for-byte what it was when
        // this lived in index(). Rewriting ten interpolations as $stats['...']
        // is a chance to change the prompt while meaning to move it, and the
        // wording here is tuned (the single-villa framing especially).
        extract($stats);

        return "
You are a data analyst for Villa Elena Private Rental Resort in the Philippines.
IMPORTANT: Villa Elena is a SINGLE, EXCLUSIVE-USE villa — there is only ONE bookable
property, rented out in its entirety to one guest group at a time (not a hotel with
multiple independently bookable rooms). Never mention 'occupancy rate', 'available
properties', or anything implying multiple bookable units — those concepts don't apply here.
Analyze the following resort data and give exactly 5 short, factual one-sentence insights.

RULES:
- Each insight must be ONE sentence only.
- Start each line with a relevant emoji.
- No markdown, no bold, no asterisks, no headers.
- State facts and interpretations only — no predictions.
- Be specific with numbers and percentages.

--- RESORT DATA ---
Date: {$now->format('F d, Y')}
Total Bookings: {$totalBookings}
Pending Bookings: {$pendingBookings}
Confirmed Bookings: {$confirmedBookings}
Currently Checked In: {$checkedIn}
Cancelled Bookings: {$cancelledBookings}
Revenue This Month: PHP {$revenueThisMonth}
Revenue Last Month: PHP {$revenueLastMonth}
Total Registered Guests: {$totalGuests}
New Guests This Month: {$newGuestsThisMonth}
--- END ---

Respond with exactly 5 lines. One insight per line. No numbering. No extra text.
";
    }
}