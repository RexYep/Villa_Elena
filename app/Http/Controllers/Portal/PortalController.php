<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\StaffLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\NotificationHelper;
use App\Events\BookingCreated; 

class PortalController extends Controller
{
    // ── Homepage / Property Listing ────────────────────────────────
    public function home(Request $request)
    {
        $query = Property::with(['images' => fn($q) => $q->where('is_primary', 1)])
            ->where('status', '!=', 'maintenance');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('guests')) {
            $query->where('max_capacity', '>=', $request->guests);
        }
        if ($request->filled('checkin') && $request->filled('checkout')) {
            $checkin  = Carbon::parse($request->checkin);
            $checkout = Carbon::parse($request->checkout);
            // Exclude properties with conflicting bookings
            $bookedIds = Booking::whereNotIn('status', ['cancelled', 'no_show'])
                ->where('check_in_date', '<', $checkout)
                ->where('check_out_date', '>', $checkin)
                ->pluck('property_id');
            $query->whereNotIn('id', $bookedIds);
        }

        $properties  = $query->orderBy('base_price')->get();
        $types       = Property::distinct()->pluck('type');
        $resortName  = Setting::get('resort_name', 'Villa Elena Resort');
        $resortDesc  = Setting::get('resort_description', 'A private luxury resort getaway.');

        return view('portal.home', compact(
            'properties', 'types', 'resortName', 'resortDesc'
        ));
    }

    // ── Single Property Detail ─────────────────────────────────────
    public function propertyDetail(Property $property, Request $request)
    {
        $property->load('images');

        // Get booked date ranges for the calendar
        $bookedRanges = Booking::where('property_id', $property->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_out_date', '>=', today())
            ->get(['check_in_date', 'check_out_date'])
            ->map(fn($b) => [
                'from' => $b->check_in_date->format('Y-m-d'),
                'to'   => $b->check_out_date->format('Y-m-d'),
            ]);

        $checkin  = $request->get('checkin');
        $checkout = $request->get('checkout');
        $guests   = $request->get('guests', 1);

        return view('portal.property', compact(
            'property', 'bookedRanges', 'checkin', 'checkout', 'guests'
        ));
    }

    // ── Booking Form ───────────────────────────────────────────────
    public function bookingForm(Property $property, Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login')
                ->with('info', 'Please log in or create an account to complete your booking.');
        }

        $request->validate([
            'checkin'  => 'required|date|after_or_equal:today',
            'checkout' => 'required|date|after:checkin',
            'guests'   => 'required|integer|min:1|max:' . $property->max_capacity,
        ]);

        $checkin  = Carbon::parse($request->checkin);
        $checkout = Carbon::parse($request->checkout);
        $nights   = $checkin->diffInDays($checkout);

        // Check availability
        $conflict = Booking::where('property_id', $property->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<', $checkout)
            ->where('check_out_date', '>', $checkin)
            ->exists();

        if ($conflict) {
            return redirect()->route('portal.property', $property)
                ->withErrors(['dates' => 'Sorry, this property is not available for the selected dates.'])
                ->withInput();
        }

        // Calculate price
        $baseAmount = 0;
        $nightBreakdown = [];
        for ($i = 0; $i < $nights; $i++) {
            $date  = $checkin->copy()->addDays($i);
            $price = $property->getPriceForDate($date);
            $baseAmount += $price;
            $nightBreakdown[] = [
                'date'     => $date->format('M d, Y'),
                'day'      => $date->format('l'),
                'price'    => $price,
                'weekend'  => in_array($date->dayOfWeek, [0, 6]),
            ];
        }

        $depositPct    = (float) Setting::get('deposit_percentage', 30);
        $depositAmount = round($baseAmount * $depositPct / 100, 2);

        return view('portal.booking_form', compact(
            'property', 'checkin', 'checkout', 'nights',
            'baseAmount', 'nightBreakdown', 'depositPct', 'depositAmount',
            'request'
        ));
    }

    // ── Submit Booking ─────────────────────────────────────────────
    public function submitBooking(Property $property, Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'checkin'          => 'required|date|after_or_equal:today',
            'checkout'         => 'required|date|after:checkin',
            'guests'           => 'required|integer|min:1|max:' . $property->max_capacity,
            'special_requests' => 'nullable|string|max:500',
        ]);

        $checkin  = Carbon::parse($request->checkin);
        $checkout = Carbon::parse($request->checkout);
        $nights   = $checkin->diffInDays($checkout);

        // Final conflict check
        $conflict = Booking::where('property_id', $property->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<', $checkout)
            ->where('check_out_date', '>', $checkin)
            ->exists();

        if ($conflict) {
            return back()->withErrors(['dates' => 'These dates are no longer available. Please choose different dates.']);
        }

        // Calculate total
        $baseAmount = 0;
        for ($i = 0; $i < $nights; $i++) {
            $baseAmount += $property->getPriceForDate($checkin->copy()->addDays($i));
        }

        $booking = Booking::create([
            'user_id'          => auth()->id(),
            'property_id'      => $property->id,
            'check_in_date'    => $checkin,
            'check_out_date'   => $checkout,
            'num_nights'       => $nights,
            'num_guests'       => $request->guests,
            'base_amount'      => $baseAmount,
            'extras_amount'    => 0,
            'discount_amount'  => 0,
            'total_amount'     => $baseAmount,
            'amount_paid'      => 0,
            'balance_due'      => $baseAmount,
            'status'           => 'pending',
            'payment_status'   => 'unpaid',
            'source'           => 'online',
            'special_requests' => $request->special_requests,
        ]);

        event(new BookingCreated($booking->load(['user', 'property'])));

        NotificationHelper::newBooking($booking->load(['user','property']));
        // Notify guest
        NotificationHelper::notifyGuest(
        auth()->id(),
        'Booking Received!',
        "Your booking {$booking->booking_ref} for {$property->property_name} has been received and is pending confirmation."
);
 
        StaffLog::record('online_booking', 'bookings', $booking->id,
            "Online booking {$booking->booking_ref} by " . auth()->user()->full_name);

        return redirect()->route('portal.confirmation', $booking);
    }

    // ── Booking Confirmation ───────────────────────────────────────
    public function confirmation(Booking $booking)
    {
        abort_if($booking->user_id !== auth()->id(), 403);
        $booking->load('property');
        return view('portal.confirmation', compact('booking'));
    }
}