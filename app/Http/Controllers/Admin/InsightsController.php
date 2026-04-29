<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
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

        $revenueThisMonth   = Payment::whereMonth('created_at', $now->month)
                                ->whereYear('created_at', $now->year)
                                ->sum('amount');

        $revenueLastMonth   = Payment::whereMonth('created_at', $now->copy()->subMonth()->month)
                                ->whereYear('created_at', $now->copy()->subMonth()->year)
                                ->sum('amount');

        $totalProperties    = Property::count();
        $availableProperties = Property::where('status', 'available')->count();
        $occupiedProperties = Property::where('status', 'occupied')->count();

        $totalGuests        = User::where('role', 'customer')->count();
        $newGuestsThisMonth = User::where('role', 'customer')
                                ->whereMonth('created_at', $now->month)
                                ->count();

        $occupancyRate = $totalProperties > 0
            ? round(($occupiedProperties / $totalProperties) * 100, 1)
            : 0;

        // --- Build Prompt ---
   $prompt = "
You are a data analyst for Villa Elena Private Rental Resort in the Philippines.
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
Total Properties: {$totalProperties}
Available Properties: {$availableProperties}
Occupied Properties: {$occupiedProperties}
Occupancy Rate: {$occupancyRate}%
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
            'occupancyRate',
            'totalGuests',
            'newGuestsThisMonth'
        ));
    }
}