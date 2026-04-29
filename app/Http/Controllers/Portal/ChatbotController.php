<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Models\Property;
use App\Models\Setting;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function reply(Request $request, GeminiService $ai)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array',
        ]);

        $userMessage = $request->input('message');
        $history     = $request->input('history', []);

        // ── Fetch REAL property data from DB ──────────────
        $properties = Property::where('status', '!=', 'maintenance')->get();

        $propertyList = '';
        if ($properties->isEmpty()) {
            $propertyList = "No properties currently listed.\n";
        } else {
            foreach ($properties as $p) {
    $amenities = '';
    if (!empty($p->amenities)) {
        $amenityList = is_array($p->amenities) ? $p->amenities : json_decode($p->amenities, true);
        $amenities = implode(', ', array_map('ucfirst', $amenityList));
    }

    $weekendPrice = $p->weekend_price ? '₱' . number_format($p->weekend_price, 2) : 'same as weekday';
    $status = $p->status === 'available' ? 'Available' : 'Currently Occupied';

    $propertyList .= "- {$p->property_name} ({$p->type}): ";
    $propertyList .= "Up to {$p->max_capacity} guests, ";
    $propertyList .= "Weekday rate ₱" . number_format($p->base_price, 2) . ", ";
    $propertyList .= "Weekend rate {$weekendPrice}. ";
    $propertyList .= $amenities ? "Amenities: {$amenities}. " : '';
    $propertyList .= "Status: {$status}.\n";
}
        }

        // ── Fetch resort settings if available ────────────
        $resortName = Setting::where('setting_key', 'resort_name')->value('setting_value') ?? 'Villa Elena Private Rental Resort';
        $checkInTime = Setting::where('setting_key', 'check_in_time')->value('setting_value') ?? '2:00 PM';
        $checkOutTime = Setting::where('setting_key', 'check_out_time')->value('setting_value') ?? '12:00 PM';
        $depositRate = Setting::where('setting_key', 'deposit_percentage')->value('setting_value') ?? '30';

        // ── Build conversation ────────────────────────────
        $conversation = "You are Elena, a friendly and professional AI assistant for {$resortName} located in Indang, Cavite, Philippines.

IMPORTANT RULES:
- Only answer questions related to Villa Elena Resort.
- NEVER invent or mention property names, prices, or amenities that are not listed below.
- NEVER comment on repetitions, conversation history, or how many times a question was asked.
- NEVER say things like \"you asked this before\" or \"as I mentioned\" or \"since you asked twice\".
- Treat every question as fresh — just answer it directly and naturally.
- If asked about something not in your knowledge, say: 'For more details, please contact us directly or visit our booking page.'
- Keep responses concise (2-4 sentences max).
- Be warm, helpful, and professional.

RESORT INFORMATION:
- Name: {$resortName}
- Location: Barangay Pansol, Calamba,Laguna ,Philippines
- Check-in time: {$checkInTime}
- Check-out time: {$checkOutTime}
- Deposit required: {$depositRate}% of total booking amount
- Booking: Guests can book online through our website or contact the resort directly.

AVAILABLE PROPERTIES:
{$propertyList}

CONVERSATION HISTORY:\n";

        foreach ($history as $entry) {
            $role = $entry['role'] === 'user' ? 'Guest' : 'Elena';
            $conversation .= "{$role}: {$entry['content']}\n";
        }

        $conversation .= "Guest: {$userMessage}\nElena:";

        $reply = $ai->ask($conversation);

        return response()->json(['reply' => trim($reply)]);
    }
}