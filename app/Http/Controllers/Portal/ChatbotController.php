<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Discount;
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
                // quoteFor() — HINDI getPackagePrice() — para tugma ang
                // sinasabi ni Elena sa presyong makikita ng guest sa
                // property page at sisingilin sa booking form. Kung
                // magkaiba ang dalawa, ang chatbot ang unang mapapansing
                // nagsisinungaling.
                $quote        = $villa->quoteFor($checkinDt, $slot);
                $packagePrice = $quote['total'];
                $slotLabel    = Booking::SLOTS[$slot]['label'];

                $bookUrl = route('portal.property', $villa)
                    . '?checkin=' . $checkin->format('Y-m-d')
                    . '&slot=' . $slot
                    . '&guests=' . ($guests ?? 2);

                if (!$guestOk) {
                    $contextData = "The requested guest count ({$guests}) exceeds Villa Elena's max capacity of {$villa->max_capacity} guests.";
                } elseif ($isAvailable) {
                    $propertyCards[] = [
                        'id'         => $villa->id,
                        'name'       => $villa->property_name,
                        'type'       => 'Whole Villa (Exclusive)',
                        'capacity'   => $villa->max_capacity,
                        // `price` ang aktwal na babayaran; `base_price` ang
                        // presyo bago ang bawas, para may maitawid na
                        // numero ang card kapag may promo.
                        'price'      => $packagePrice,
                        'base_price' => $quote['base'],
                        'discount'   => $quote['discount'],
                        'promo'      => $quote['promo']?->label,
                        'slot_label' => $slotLabel,
                        'status'     => $villa->status,
                        'amenities'  => is_array($villa->amenities) ? array_slice($villa->amenities, 0, 3) : [],
                        'book_url'   => $bookUrl,
                        'image'      => $villa->primaryImage ? $villa->primaryImage->url : null,
                    ];

                    $contextData = "Villa Elena IS AVAILABLE for {$checkin->format('M d, Y')}, {$slotLabel} slot. ";

                    if ($quote['discount'] > 0) {
                        $contextData .= "Package price: ₱" . number_format($quote['base'], 2)
                            . ", but the \"{$quote['promo']->label}\" promo ({$quote['promo']->value_label}) applies to this date, so the guest pays ₱"
                            . number_format($packagePrice, 2) . " — a saving of ₱" . number_format($quote['discount'], 2)
                            . ". The discount is applied AUTOMATICALLY; there is no code to enter. Mention this saving to the guest.";
                    } else {
                        $contextData .= "Package price: ₱" . number_format($packagePrice, 2)
                            . " (flat rate, not per guest). No promo applies to this particular date/slot.";
                    }
                } else {
                    $contextData = "Villa Elena is NOT available for {$checkin->format('M d, Y')}, {$slotLabel} slot — it's already booked. Suggest the guest try a different date or the other slot (Day or Night).";
                }
            } elseif (($intent['intent'] ?? '') === 'get_price') {
                // Walang petsang binanggit, kaya hindi masasabi kung
                // tumatama ba ang isang promo — nakadepende iyon sa
                // check-in date. Ibinibigay ang list price at ipinaaalam
                // na may promo, sa halip na mangako ng bawas na baka
                // hindi naman pala tumama sa petsang pipiliin niya.
                $contextData = "Villa Elena package pricing BEFORE any promo (flat rate regardless of number of guests, up to {$villa->max_capacity} max): ₱" . number_format($villa->base_price, 2) . " for Monday–Thursday check-in and Sunday check-in after 6:00 PM. ₱" . number_format($villa->weekend_price, 2) . " for Friday, Saturday, or Sunday check-in before 6:00 PM.";

                if (Discount::publicActive()->isNotEmpty()) {
                    $contextData .= " A promo may lower this — see CURRENT PROMOS. Whether it applies depends on the check-in date, and the guest hasn't given one yet, so ask for their preferred date rather than promising a discounted figure.";
                }
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

        // ── Step 3b: Kasalukuyang promos ───────────────────────────
        // Ginagamit dito ang PAREHONG Discount::publicActive() na
        // pinagmumulan ng landing-page banner, kaya hindi puwedeng
        // mag-iba ang sinasabi ni Elena sa nakikita sa homepage.
        //
        // Laging naroon ang bloke na ito — hindi lang kapag "promo" ang
        // tanong — dahil hindi maaasahang mahuhulaan ng intent extractor
        // ang bawat paraan ng pagtatanong ("discount ba meron?", "mura
        // ba sa September?"). Mas mura ang ilang linyang konteksto kaysa
        // sa isang na-miss na promo.
        //
        // MAHALAGA: kapag walang promo, sinasabi natin iyon nang tahasan.
        // Ang isang walang lamang seksyon ay iniimbita ang modelong
        // mag-imbento ng promong wala naman.
        $promos     = Discount::publicActive();
        $promoBlock = '';

        if ($promos->isEmpty()) {
            $promoBlock = "There are NO promos or discounts running right now. If the guest asks about promos, say so plainly and do NOT invent one.\n";
        } else {
            $promoBlock = "These promos are live. They apply AUTOMATICALLY based on the guest's CHECK-IN DATE — there is no promo code to type in, and the guest does not need to do anything to claim one:\n";

            foreach ($promos as $p) {
                $window = $p->expiry_date
                    ? $p->start_date?->format('M d, Y') . ' – ' . $p->expiry_date->format('M d, Y')
                    : 'ongoing, no end date';

                $promoBlock .= "- \"{$p->label}\": {$p->value_label} the villa base rate";
                $promoBlock .= $p->applies_to !== 'all' ? " ({$p->slot_label} bookings only)" : '';
                $promoBlock .= ", for stays {$window}.";
                $promoBlock .= $p->description ? " {$p->description}" : '';

                if ($p->isUpcoming()) {
                    // Ang pagkakaiba ay tunay na mahalaga sa guest: bukas
                    // na ang booking, pero ang STAY ang dapat nasa loob ng
                    // window bago tumama ang bawas.
                    $promoBlock .= " NOTE: this promo has NOT started yet — it only discounts stays inside that date range, but guests CAN book ahead for it right now.";
                }

                $promoBlock .= "\n";
            }

            $promoBlock .= "Promos never stack — if two overlap, the guest automatically gets the bigger discount. The discount comes off the villa rate only, not off any add-ons or extras.\n";
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
- Never invent a promo or discount. Only mention what is listed under CURRENT PROMOS below. If that section says there are none, tell the guest there are no promos right now — do not soften it into a maybe.
- Promos apply automatically from the check-in date. NEVER ask the guest for a promo code and never imply one exists — there are no codes in this system.
- A promo whose window hasn't started yet still lets guests book ahead; it just doesn't discount stays outside its date range. Be precise about that if it comes up.
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

CURRENT PROMOS / DISCOUNTS:
{$promoBlock}

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