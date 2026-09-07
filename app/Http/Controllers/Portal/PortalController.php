<?php

namespace App\Http\Controllers\Portal;

use App\Events\BookingCreated;
use App\Events\PropertyAvailabilityChanged;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmitted;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Review;
use App\Models\Setting;
use App\Models\StaffLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PortalController extends Controller
{
    /**
     * Petsa ng huling pagbabago sa Privacy Policy at Terms of Service.
     * Manu-manong ina-update kasama ng mismong teksto ng dalawang page —
     * hindi ito dapat naka-tali sa file mtime o deploy date, dahil ang
     * ipinapakita nito sa guest ay kung kailan huling nagbago ang
     * *patakaran*, hindi kung kailan huling na-deploy ang app.
     */
    public const LEGAL_LAST_UPDATED = '2026-08-27';

    // ── Homepage / Property Listing ────────────────────────────────
    public function home(Request $request)
    {
        // Isang Villa Elena na lang mismo ang lalabas dito (type=villa).
        // Ang mga individual "Room" record (type=room) ay internal
        // reference/detail na lang (housekeeping, images, atbp.) at
        // hindi na sila lalabas bilang hiwalay na bookable listing.
        $query = Property::with(['images' => fn ($q) => $q->where('is_primary', 1)])
            ->where('status', '!=', 'maintenance')
            ->where('type', 'villa');

        if ($request->filled('guests')) {
            $query->where('max_capacity', '>=', $request->guests);
        }
        $properties = $query->orderBy('base_price')->get();

        // Status + larawan ng bawat kwarto (info lang) para sa "Explore the
        // Rooms" section sa homepage. Parehong pattern ng eager-load na
        // ginagamit sa Villa listing sa itaas (images na is_primary=1 lang).
        $rooms = Property::where('type', 'room')
            ->with(['images' => fn ($q) => $q->where('is_primary', 1)])
            ->orderBy('property_name')
            ->get(['id', 'property_name', 'status', 'floor_area_sqm']);

        if ($request->filled('checkin')) {
            // Ang tunay na conflict check ay gagawin ulit sa
            // bookingForm/submitBooking gamit ang aktwal na slot na
            // pipiliin ng customer — paunang listing filter lang ito.
            $slot = in_array($request->get('slot'), array_keys(Booking::SLOTS)) ? $request->get('slot') : 'day';
            [$checkin, $checkout] = Booking::slotDateTimes($slot, $request->checkin);

            $properties = $properties->filter(
                fn ($property) => ! Booking::hasConflict($property->id, $checkin, $checkout)
            )->values();
        }

        $resortName = Setting::get('resort_name', 'Villa Elena Resort');
        $resortDesc = Setting::get('resort_description', 'A private luxury resort getaway.');
        $resortEmail = Setting::get('resort_email', 'hello@villaelena.ph');
        $resortPhone = Setting::get('resort_phone', '+63 917 123 4567');
        $resortAddress = Setting::get('resort_address', 'Barangay Pansol, Calamba, Laguna');
        $facebookUrl = Setting::get('facebook_url');
        $tiktokUrl = Setting::get('tiktok_url');

        // Latest approved guest reviews for the "Guest Voices" section.
        $reviews = Review::where('status', 'approved')
            ->with(['user', 'property'])
            ->latest()
            ->take(3)
            ->get();

        // Kasalukuyang ina-advertise na seasonal promos. Dito sa landing
        // page ito pangunahing nakikita: karamihan ng bagong booker ay
        // HINDI pa naka-login, kaya wala silang user_id at hindi sila
        // maaabot ng in-app notification — banner lang ang umaabot sa
        // kanila.
        $promos = Discount::publicActive();

        $allowOnlineBooking = Setting::get('allow_online_booking', '1') === '1';

        return view('portal.home', compact(
            'properties', 'rooms', 'resortName', 'resortDesc', 'reviews',
            'resortEmail', 'resortPhone', 'resortAddress', 'facebookUrl', 'tiktokUrl',
            'promos', 'allowOnlineBooking'
        ));
    }

    // ── All Approved Reviews (public) ────────────────────────────────
    public function reviews()
    {
        $reviews = Review::where('status', 'approved')
            ->with(['user', 'property'])
            ->latest()
            ->paginate(9);

        $avgRating = round(Review::where('status', 'approved')->avg('rating'), 1);
        $totalReviews = Review::where('status', 'approved')->count();

        return view('portal.reviews', compact('reviews', 'avgRating', 'totalReviews'));
    }

    // ── Legal Pages (public) ─────────────────────────────────────────
    //
    // Dalawang page lang: Privacy Policy at Terms of Service. Walang
    // hiwalay na "Cookie Policy" — strictly-necessary cookies lang ang
    // itinatakda ng system (session, CSRF token, at remember-me), wala
    // tayong analytics o advertising cookie, kaya isang seksyon na lang
    // iyon sa loob ng Privacy Policy (#cookies) sa halip na buong page
    // na halos walang laman.
    public function privacy()
    {
        return view('portal.legal.privacy', $this->legalContext());
    }

    public function terms()
    {
        return view('portal.legal.terms', $this->legalContext());
    }

    /**
     * Live na datos ang pinagmumulan ng bawat numerong binabanggit ng
     * dalawang legal page (rate ng villa, deposit %, hold window,
     * reschedule limits, slot times) — hindi hardcoded na teksto.
     *
     * Sinasadya ito: ang mismong bug na naitama sa v5.5 ay isang
     * nakasulat na patakaran na hindi tugma sa ipinapatupad ng code
     * (nag-a-advertise ang booking form ng "free cancellation 48 hours
     * before check-in" gayong 7 araw pala ang tunay na 100% tier). Ang
     * legal page ang pinakadelikadong lugar para maulit iyon, kaya
     * kinukuha na lang dito ang halaga sa parehong pinagmumulan ng
     * booking flow.
     */
    private function legalContext(): array
    {
        return [
            'resortName' => Setting::get('resort_name', 'Villa Elena Private Rental Resort'),
            'resortEmail' => Setting::get('resort_email', config('mail.from.address')),
            'resortPhone' => Setting::get('resort_phone', '+63 917 123 4567'),
            'resortAddress' => Setting::get('resort_address', 'Barangay Pansol, Calamba, Laguna'),
            'villa' => Property::where('type', 'villa')->first(),
            'depositPct' => (float) Setting::get('deposit_percentage', 50),
            'holdMinutes' => Booking::pendingHoldMinutes(),
            'slots' => Booking::SLOTS,
            'maxReschedules' => Booking::MAX_RESCHEDULES,
            'rescheduleCutoff' => Booking::RESCHEDULE_CUTOFF_DAYS,
            'lastUpdated' => Carbon::parse(self::LEGAL_LAST_UPDATED),
        ];
    }

    // ── Contact Form Submission ──────────────────────────────────────
    public function submitContact(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email',
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|min:10|max:2000',
        ]);

        $resortEmail = Setting::get('resort_email', config('mail.from.address'));

        try {
            Mail::to($resortEmail)->send(new ContactFormSubmitted(
                $request->name,
                $request->email,
                $request->subject,
                $request->message
            ));
        } catch (\Throwable $e) {
            Log::error('Contact form email failed: '.$e->getMessage());

            return back()->withInput()->with('contact_error', 'Sorry, something went wrong sending your message. Please try again or reach us directly.');
        }

        return back()->with('contact_success', 'Thank you! Your message has been sent — we\'ll get back to you shortly.');
    }

    // ── Single Property Detail ─────────────────────────────────────
    public function propertyDetail(Property $property, Request $request)
    {
        $property->load('images');

        // Mga individual na kwarto sa loob ng Villa (display/info lang,
        // hindi hiwalay na bookable listing)
        $rooms = Property::where('type', 'room')->orderBy('property_name')->get(['id', 'property_name', 'status']);

        // Availability para sa calendar. Bawat araw ay may dalawang slot
        // lang (day/night), kaya ang ipinapadala sa view ay kung aling
        // SLOT ang sarado kada petsa — hindi hilaw na date range. Kung
        // date range lang ang ibibigay, magmumukhang buong-araw na sarado
        // ang isang petsang may gabing booking lang, gayong bakante pa
        // ang umaga nito.
        $slotAvailability = Booking::slotAvailabilityMap($property->id);

        $checkin = $request->get('checkin');
        $guests = $request->get('guests', 1);
        $slot = in_array($request->get('slot'), array_keys(Booking::SLOTS)) ? $request->get('slot') : 'day';

        // Approved guest reviews for this specific property.
        $reviews = Review::where('property_id', $property->id)
            ->where('status', 'approved')
            ->with('user')
            ->latest()
            ->get();

        $avgRating = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;
        $totalReviews = $reviews->count();

        $allowOnlineBooking = Setting::get('allow_online_booking', '1') === '1';

        return view('portal.property', compact(
            'property', 'rooms', 'slotAvailability', 'checkin', 'guests', 'slot',
            'reviews', 'avgRating', 'totalReviews', 'allowOnlineBooking'
        ));
    }

    // ── Live Price Preview (AJAX) ────────────────────────────────
    // Ginagamit ng property.blade.php para sa real-time price preview.
    // Sinasadyang tinatawag dito ang PAREHONG getPackagePrice() at
    // Booking::hasConflict() na ginagamit sa bookingForm()/submitBooking(),
    // para guaranteed na tugma ang ipinapakitang preview sa presyo/
    // availability na makikita sa susunod na hakbang (kasama na ang
    // anumang holiday/special-date override sa loob ng getPackagePrice()).
    public function pricePreview(Property $property, Request $request)
    {
        $validator = validator($request->all(), [
            'checkin' => 'required|date',
            'slot' => 'required|in:'.implode(',', array_keys(Booking::SLOTS)),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Kulang o mali ang petsa/slot na napili.',
            ], 422);
        }

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $hoursStay = $checkin->diffInHours($checkout);

        // Kung "ngayong araw" ang pinili pero lumagpas na ang check-in
        // time mismo ng slot (hal. 10PM na pero "Day" 8AM pa rin ang
        // pinili), hindi na ito dapat payagan — hindi na ito makaka-
        // check-in sa oras na iyon. `after_or_equal:today` sa validation
        // ay date-level lang, hindi nito nakikita itong kaso.
        if ($checkin->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'The '.Booking::SLOTS[$request->slot]['label'].' check-in slot has already passed for today. Please select a different date or slot.',
            ]);
        }

        if (Booking::hasConflict($property->id, $checkin, $checkout)) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'hours' => round($hoursStay, 1),
                'message' => 'The villa is not available for the selected dates or schedule.',
            ]);
        }

        // Flat/package price + anumang tumatamang seasonal promo — iisang
        // quoteFor() ang ginagamit dito at sa bookingForm()/submitBooking(),
        // kaya hindi puwedeng magkaiba ang ipinakitang presyo sa sisingilin.
        $quote = $property->quoteFor($checkin, $request->slot);
        $baseAmount = $quote['base'];
        $isPeak = in_array($checkin->dayOfWeek, [5, 6]) || ($checkin->dayOfWeek === 0 && $checkin->format('H:i') < '18:00');

        return response()->json([
            'valid' => true,
            'available' => true,
            'hours' => round($hoursStay, 1),
            'is_peak' => $isPeak,
            // `price` ay ang AKTWAL na babayaran (may bawas na). Ang
            // `base_price` ang panghati/tinatawid na presyo sa UI.
            'price' => $quote['total'],
            'price_formatted' => '₱'.number_format($quote['total'], 2),
            'base_price' => $baseAmount,
            'base_formatted' => '₱'.number_format($baseAmount, 2),
            'discount' => $quote['discount'],
            'discount_formatted' => '₱'.number_format($quote['discount'], 2),
            'promo_label' => $quote['promo']?->label,
            'promo_value_label' => $quote['promo']?->value_label,
        ]);
    }

    // ── Booking Form ───────────────────────────────────────────────
    public function bookingForm(Property $property, Request $request)
    {
        if (Setting::get('allow_online_booking', '1') !== '1') {
            return redirect()->route('portal.property', $property)
                ->with('error', 'Online booking is temporarily unavailable. Please contact us directly to reserve your stay.');
        }

        if (! Auth::check()) {
            return redirect()->route('login')
                ->with('info', 'Please log in or create an account to complete your booking.');
        }

        $request->validate([
            'checkin' => 'required|date|after_or_equal:today',
            'slot' => 'required|in:'.implode(',', array_keys(Booking::SLOTS)),
            'guests' => 'required|integer|min:1|max:'.$property->max_capacity,
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $nights = max(1, $checkin->diffInDays($checkout));

        // Kung lumagpas na ang check-in time mismo ng slot para sa
        // "ngayong araw" (hal. 10PM na pero "Day" 8AM pa rin ang pinili),
        // hindi na ito dapat payagan.
        if ($checkin->isPast()) {
            return redirect()->route('portal.property', $property)
                ->withErrors(['dates' => 'The '.Booking::SLOTS[$request->slot]['label'].' check-in slot has already passed for today. Please select a different date or slot.'])
                ->withInput();
        }

        // Check availability — ang gap sa pagitan ng dalawang fixed slot
        // ang siya nang cleaning buffer, kaya walang hiwalay na buffer
        // check dito.
        if (Booking::hasConflict($property->id, $checkin, $checkout)) {
            return redirect()->route('portal.property', $property)
                ->withErrors(['dates' => 'The villa is not available for the selected dates or schedule.'])
                ->withInput();
        }

        // Flat/package price — base lang sa segment ng CHECK-IN
        // (hindi na babago kahit anong araw mahulog ang check-out,
        // dahil isang package lang ang binabayaran, hindi per-night).
        // Kasama na rito ang anumang tumatamang seasonal promo.
        $quote = $property->quoteFor($checkin, $request->slot);
        $baseAmount = $quote['base'];
        $discountAmount = $quote['discount'];
        $totalAmount = $quote['total'];
        $promo = $quote['promo'];
        $isPeak = in_array($checkin->dayOfWeek, [5, 6]) || ($checkin->dayOfWeek === 0 && $checkin->format('H:i') < '18:00');
        $nightBreakdown = [[
            'date' => $checkin->format('M d, Y'),
            'day' => $checkin->format('l').' '.$checkin->format('g:i A').' check-in',
            'price' => $baseAmount,
            'weekend' => $isPeak,
        ]];

        // Deposit percentage — minimum 50% ngayon (dating 30%) bago
        // ma-confirm ang isang online booking. Palitan ang value nito sa
        // Settings (Admin panel o Setting::set('deposit_percentage', 50)).
        // Kinukuwenta laban sa DISCOUNTED na total, hindi sa base — kung
        // hindi, mas malaki pa sa kalahati ng aktwal na sinisingil ang
        // hihingin sa guest na may promo.
        $depositPct = (float) Setting::get('deposit_percentage', 50);
        $depositAmount = round($totalAmount * $depositPct / 100, 2);

        $slot = $request->slot;

        return view('portal.booking_form', compact(
            'property', 'checkin', 'checkout', 'slot', 'nights',
            'baseAmount', 'discountAmount', 'totalAmount', 'promo',
            'nightBreakdown', 'depositPct', 'depositAmount',
            'request'
        ));
    }

    // ── Submit Booking ─────────────────────────────────────────────
    public function submitBooking(Property $property, Request $request)
    {
        if (Setting::get('allow_online_booking', '1') !== '1') {
            return redirect()->route('portal.property', $property)
                ->with('error', 'Online booking is temporarily unavailable. Please contact us directly to reserve your stay.');
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // Anti-abuse: hindi papayagan ang bagong booking kung may
        // kasalukuyan nang "pending" (unpaid, di pa 2+ oras) na booking
        // pa ang guest na ito — kailangan muna nilang bayaran o
        // kanselahin ang dati bago makagawa ng panibago. Pinipigilan
        // nito ang isang account na mag-hold ng maraming petsa nang
        // sabay-sabay nang hindi talaga nagbabayad.
        if (Booking::hasActivePendingBooking(Auth::id())) {
            return back()->withErrors([
                'dates' => 'You have an existing booking that is pending payment. Please pay or cancel it before creating a new booking.',
            ])->withInput();
        }

        // Anti-abuse: kung ilang beses nang AUTO-cancel (hindi binayaran
        // sa loob ng hold window) ang guest na ito nang sunod-sunod,
        // pinepetsahan muna sila bago makagawa ng panibagong booking —
        // hindi katulad ng hasExcessiveCancellations() (na nage-force
        // lang ng full payment), pinipigilan mismo dito ang paggawa ng
        // bagong unpaid hold habang tumatakbo pa ang cooldown.
        if ($cooldownEndsAt = Booking::bookingCooldownEndsAt(Auth::id())) {
            return back()->withErrors([
                'dates' => 'Several of your recent bookings were auto-cancelled for non-payment. '
                    .'Please try again after '.$cooldownEndsAt->format('M d, Y g:i A').'.',
            ])->withInput();
        }

        $request->validate([
            'checkin' => 'required|date|after_or_equal:today',
            'slot' => 'required|in:'.implode(',', array_keys(Booking::SLOTS)),
            'guests' => 'required|integer|min:1|max:'.$property->max_capacity,
            'special_requests' => 'nullable|string|max:500',

            // Kailangang naka-tick ang "I have read the booking policies"
            // bago magpatuloy sa payment. Naka-disable ang submit button
            // hangga't hindi naka-check, pero client-side affordance lang
            // iyon — dito ito talaga ipinapatupad, dahil kayang laktawan
            // ang JS pero hindi ang server.
            'policies_accepted' => 'accepted',
        ], [
            'policies_accepted.accepted' => 'Please confirm that you have read the booking policies before continuing.',
        ]);

        [$checkin, $checkout] = Booking::slotDateTimes($request->slot, $request->checkin);
        $nights = max(1, $checkin->diffInDays($checkout));

        // Final na check din dito (hindi lang sa bookingForm()) kung
        // lumagpas na ang check-in time ng slot para sa "ngayong araw" —
        // hal. nag-tagal ang guest sa Guest Details page kaya lumagpas na
        // ang oras bago pa man mag-submit.
        if ($checkin->isPast()) {
            return back()->withErrors(['dates' => 'The '.Booking::SLOTS[$request->slot]['label'].' check-in slot has already passed for today. Please select a different date or slot.'])->withInput();
        }

        // Final conflict check — walang hiwalay na buffer, ang gap sa
        // pagitan ng dalawang fixed slot na mismo ang buffer.
        if (Booking::hasConflict($property->id, $checkin, $checkout)) {
            return back()->withErrors(['dates' => 'This selected date/slot is no longer available. Please choose another.'])->withInput();
        }

        // Flat/package price + seasonal promo. Muling kinukuwenta dito
        // (hindi tinatanggap mula sa form) — kung galing sa request ang
        // discount, kayang baguhin ng guest ang presyo mismo.
        $quote = $property->quoteFor($checkin, $request->slot);
        $baseAmount = $quote['base'];
        $discountAmount = $quote['discount'];
        $totalAmount = $quote['total'];
        $promo = $quote['promo'];

        // Ang booking ay ginagawa muna bilang "pending" para ma-reserve
        // ang slot (kasama sa hasConflict() check ng ibang customer),
        // pero HINDI pa ito "confirmed" — magiging "confirmed" lang ito
        // AWTOMATIKO pagkatapos ng successful PayMongo payment
        // (tingnan: PaymentController::success()). WALANG admin approval
        // step — direktang papunta sa Pay page pagkatapos nito.
        $booking = Booking::create([
            'user_id' => Auth::id(),
            'property_id' => $property->id,
            'check_in_date' => $checkin->format('Y-m-d'),
            'check_in_time' => $checkin->format('H:i:s'),
            'check_out_date' => $checkout->format('Y-m-d'),
            'check_out_time' => $checkout->format('H:i:s'),
            'num_nights' => $nights,
            'num_guests' => $request->guests,
            'base_amount' => $baseAmount,
            'extras_amount' => 0,
            'discount_amount' => $discountAmount,
            'discount_id' => $promo?->id,
            'total_amount' => $totalAmount,
            'amount_paid' => 0,
            'balance_due' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'source' => 'online',
            'special_requests' => $request->special_requests,
        ]);

        // Atomic sa antas ng SQL (`used_count = used_count + 1`), kaya
        // walang mawawalang bilang kahit sabay-sabay ang mga booking.
        // Puwedeng lumampas nang isa sa `usage_limit` kung eksaktong
        // sabay ang dalawang guest — sinasadyang tinatanggap iyon; mas
        // mabigat na presyo ang mag-lock ng buong promo row sa bawat
        // booking kaysa sa isang sobrang na-diskuwentuhang stay.
        $promo?->increment('used_count');

        // Realtime broadcast lang ito (admin dashboard toast) — dapat
        // hindi ito maka-block sa online booking flow ng customer kung
        // mag-fail ang Pusher; naka-commit na ang booking sa puntong ito.
        try {
            event(new BookingCreated($booking->load(['user', 'property'])));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast BookingCreated (online booking): '.$e->getMessage());
        }

        try {
            event(new PropertyAvailabilityChanged(
                $booking->property_id,
                'blocked',
                $checkin->format('Y-m-d'),
                $checkout->format('Y-m-d'),
                checkInTime: $checkin->format('g:i A'),
                checkOutTime: $checkout->format('g:i A'),
                bookingId: $booking->id,
            ));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast PropertyAvailabilityChanged (online booking): '.$e->getMessage());
        }

        NotificationHelper::newBooking($booking->load(['user', 'property']));
        // Notify guest — pinalitan ang wording, dahil hindi na naghihintay
        // ng admin review; ang susunod na hakbang na lang ay ang bayad.
        NotificationHelper::notifyGuest(
            Auth::id(),
            'Booking Received',
            "Your booking {$booking->booking_ref} for {$property->property_name} has been received. Please complete payment to confirm your reservation.",
            route('customer.bookings.show', $booking, false)
        );

        StaffLog::record('online_booking', 'bookings', $booking->id,
            "Online booking {$booking->booking_ref} by ".Auth::user()->full_name);

        // Diretso na sa Pay page — mandatory na ang bayad, walang
        // "pay later" o admin approval na hihintayin pa.
        return redirect()->route('payment.page', $booking);
    }

    // ── Booking Confirmation ───────────────────────────────────────
    public function confirmation(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        $booking->load('property');

        return view('portal.confirmation', compact('booking'));
    }
}
