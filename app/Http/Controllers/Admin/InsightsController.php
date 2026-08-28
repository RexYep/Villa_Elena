<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class InsightsController extends Controller
{
    public function index(GeminiService $gemini)
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

        // --- Build Prompt ---
   $prompt = "
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
        $insights = $gemini->ask($prompt);

        return view('admin.insights.index', compact(
            'insights',
            'totalBookings',
            'pendingBookings',
            'revenueThisMonth',
            'revenueLastMonth',
            'checkedIn',
            'totalGuests',
            'newGuestsThisMonth'
        ));
    }
}