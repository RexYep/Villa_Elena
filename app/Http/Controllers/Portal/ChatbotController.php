<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function reply(Request $request, GeminiService $ai)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array',
        ]);

        $userMessage = trim($request->input('message'));
        $history     = $request->input('history', []);

        // ── Step 1: Extract intent via AI ─────────────────────────
        // Note: single-villa resort — walang "search among many properties",
        // check-availability/price lang ng IISANG Villa.
        $intentPrompt = "You are a booking intent extractor for a SINGLE-VILLA private resort (NOT a hotel — there is only ONE bookable villa, rented out in its entirety to one group at a time).
Analyze this message and extract booking details.
Respond ONLY with a valid JSON object — no explanation, no markdown, no backticks.

Message: \"{$userMessage}\"

Extract:
{
  \"intent\": \"check_availability\" or \"get_price\" or \"general_question\" or \"greeting\",
  \"checkin\": \"YYYY-MM-DD or null\",
  \"slot\": \"day\" or \"night\" or null — \"day\" means a daytime/morning stay (8:00 AM–5:00 PM), \"night\" means an evening/overnight stay (7:00 PM–6:00 AM). Infer from words like 'morning', 'daytime', 'day tour' → day; 'evening', 'overnight', 'night' → night.
  \"guests\": number or null
}

Today is " . now()->format('Y-m-d') . " (" . now()->format('l') . ").
For relative dates like 'this weekend', 'next week', calculate the actual dates.
This weekend = next Saturday " . now()->next('Saturday')->format('Y-m-d') . " to Sunday " . now()->next('Sunday')->format('Y-m-d') . ".
If no slot is mentioned, leave slot as null (defaults to \"day\").";

        $intentJson = $ai->ask($intentPrompt);

        // Clean JSON response
        $intentJson = preg_replace('/```json|```/', '', $intentJson);
        $intentJson = trim($intentJson);
        $intent     = json_decode($intentJson, true);

        // ── Step 2: Get the single master Villa + room status ──────
        $villa = Property::where('type', 'villa')->first();
        $rooms = Property::where('type', 'room')->orderBy('property_name')->get(['property_name', 'status']);

        $propertyCards = [];
        $contextData   = '';

        if ($villa && $intent && in_array($intent['intent'] ?? '', ['check_availability', 'get_price'])) {

            $checkin = $intent['checkin'] ? Carbon::parse($intent['checkin']) : null;
            $slot    = in_array($intent['slot'] ?? null, array_keys(Booking::SLOTS)) ? $intent['slot'] : 'day';
            $guests  = $intent['guests'] ?? null;

            if ($checkin) {
                [$checkinDt, $checkoutDt] = Booking::slotDateTimes($slot, $checkin->format('Y-m-d'));

                $guestOk     = !$guests || $guests <= $villa->max_capacity;
                $isAvailable = !Booking::hasConflict($villa->id, $checkinDt, $checkoutDt);
                $packagePrice = $villa->getPackagePrice($checkinDt);
                $slotLabel    = Booking::SLOTS[$slot]['label'];

                $bookUrl = route('portal.property', $villa)
                    . '?checkin=' . $checkin->format('Y-m-d')
                    . '&slot=' . $slot
                    . '&guests=' . ($guests ?? 2);

                if (!$guestOk) {
                    $contextData = "The requested guest count ({$guests}) exceeds Villa Elena's max capacity of {$villa->max_capacity} guests.";
                } elseif ($isAvailable) {
                    $propertyCards[] = [
                        'id'        => $villa->id,
                        'name'      => $villa->property_name,
                        'type'      => 'Whole Villa (Exclusive)',
                        'capacity'  => $villa->max_capacity,
                        'price'     => $packagePrice,
                        'status'    => $villa->status,
                        'amenities' => is_array($villa->amenities) ? array_slice($villa->amenities, 0, 3) : [],
                        'book_url'  => $bookUrl,
                        'image'     => $villa->primaryImage ? $villa->primaryImage->url : null,
                    ];
                    $contextData = "Villa Elena IS AVAILABLE for {$checkin->format('M d, Y')}, {$slotLabel} slot. Package price: ₱" . number_format($packagePrice, 2) . " (flat rate, not per guest).";
                } else {
                    $contextData = "Villa Elena is NOT available for {$checkin->format('M d, Y')}, {$slotLabel} slot — it's already booked. Suggest the guest try a different date or the other slot (Day or Night).";
                }
            } elseif (($intent['intent'] ?? '') === 'get_price') {
                $contextData = "Villa Elena package pricing (flat rate regardless of number of guests, up to {$villa->max_capacity} max): ₱" . number_format($villa->base_price, 2) . " for Monday–Thursday check-in and Sunday check-in after 6:00 PM. ₱" . number_format($villa->weekend_price, 2) . " for Friday, Saturday, or Sunday check-in before 6:00 PM.";
            }
        }

        // ── Step 3: Build Villa + Room info for general Q&A ─────────
        $villaInfo = '';
        if ($villa) {
            $amenities = is_array($villa->amenities) ? implode(', ', $villa->amenities) : '';
            $villaInfo .= "Villa Elena — the ONE whole property, rented EXCLUSIVELY (not per room, not per head):\n";
            $villaInfo .= "- Max capacity: {$villa->max_capacity} guests\n";
            $villaInfo .= "- Includes all {$rooms->count()} rooms in a single booking\n";
            $villaInfo .= "- Flat package pricing: ₱" . number_format($villa->base_price, 2) . " (Mon–Thu, and Sun after 6PM) or ₱" . number_format($villa->weekend_price, 2) . " (Fri, Sat, and Sun before 6PM) — same price no matter how many guests\n";
            $villaInfo .= $amenities ? "- Amenities: {$amenities}\n" : '';
            $villaInfo .= "- Overall status: " . ucfirst($villa->status) . "\n";
        }

        $roomStatusList = '';
        foreach ($rooms as $r) {
            $roomStatusList .= "- {$r->property_name}: " . ucfirst($r->status) . "\n";
        }

        // ── Step 4: Resort settings ────────────────────────────────
        $resortName  = Setting::get('resort_name', 'Villa Elena Private Rental Resort');
        $depositRate = Setting::get('deposit_percentage', '30');
        $maxCapacity = $villa->max_capacity ?? 'N/A';

        // ── Step 5: Build final AI prompt ─────────────────────────
        $systemPrompt = "You are Elena, a friendly and professional AI booking assistant for {$resortName} in Barangay Pansol, Calamba, Philippines.

IMPORTANT — HOW THIS RESORT ACTUALLY WORKS:
- Villa Elena is a SINGLE PRIVATE VILLA, not a hotel. There is only ONE bookable listing: the whole Villa.
- Guests never book individual rooms. Booking the Villa means EXCLUSIVE use of the entire property (all {$rooms->count()} rooms included) for their group only — no other guests on-site at the same time.
- Pricing is FLAT/PACKAGE-based — NOT per-night, NOT per-head/per-guest. Same price whether 1 person or {$maxCapacity} people come, because it's a private exclusive rental, not a public per-head resort.
- Bookings are one of exactly TWO fixed slots — there is no free-choice time: Day (8:00 AM check-in – 5:00 PM check-out) or Night (7:00 PM check-in – 6:00 AM check-out the next day). For longer or custom stays, tell the guest to contact the resort directly.

RULES:
- Only answer about Villa Elena Resort topics.
- Never invent prices, amenities, or imply there are multiple villas/rooms to choose from — there is only ONE bookable Villa.
- Never comment on conversation history or repetitions.
- If checking availability or price, use the SEARCH RESULT below — don't guess.
- If available, briefly confirm and tell them to check the card shown below your message / click Book Now.
- If not available, suggest trying a different date or the other slot (Day or Night).
- Keep responses concise (2-4 sentences). Be warm and helpful.
- Today is " . now()->format('F d, Y') . ".

VILLA ELENA INFO:
{$villaInfo}
ROOM STATUS (informational only — these are NOT separately bookable, just what's inside the Villa):
{$roomStatusList}
- Deposit required: {$depositRate}% of total

" . ($contextData ? "SEARCH RESULT:\n{$contextData}\n" : '') . "

CONVERSATION HISTORY:
";

        foreach ($history as $entry) {
            $role          = $entry['role'] === 'user' ? 'Guest' : 'Elena';
            $systemPrompt .= "{$role}: {$entry['content']}\n";
        }

        $systemPrompt .= "Guest: {$userMessage}\nElena:";

        $reply = $ai->ask($systemPrompt);

        return response()->json([
            'reply'          => trim($reply),
            'property_cards' => $propertyCards,
            'intent'         => $intent['intent'] ?? 'general_question',
        ]);
    }
}