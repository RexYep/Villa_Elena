<?php

namespace App\Http\Controllers\Admin;

use App\Events\BookingUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\AvailabilityBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    // ── Calendar Page ──────────────────────────────────────────────
    public function index()
    {
        $properties = Property::orderBy('sort_order')->orderBy('property_name')->get();
        return view('admin.calendar.index', compact('properties'));
    }

    // ── Events API (JSON for FullCalendar) ─────────────────────────
    public function events(Request $request)
    {
        $start = $request->get('start');
        $end   = $request->get('end');

        $events = [];

        // ── Bookings ───────────────────────────────────────────────
        $bookings = Booking::with(['property', 'user'])
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('check_in_date', [$start, $end])
                  ->orWhereBetween('check_out_date', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('check_in_date', '<=', $start)
                         ->where('check_out_date', '>=', $end);
                  });
            })
            ->get();

        $statusColors = [
            'pending'     => ['bg' => '#f59e0b', 'border' => '#d97706'],
            'confirmed'   => ['bg' => '#3b82f6', 'border' => '#2563eb'],
            'checked_in'  => ['bg' => '#10b981', 'border' => '#059669'],
            'checked_out' => ['bg' => '#6b7280', 'border' => '#4b5563'],
            'no_show'     => ['bg' => '#ef4444', 'border' => '#dc2626'],
        ];

        foreach ($bookings as $booking) {
            $color = $statusColors[$booking->status] ?? ['bg' => '#6b7280', 'border' => '#4b5563'];

            // A calendar cell is narrow — 129px on a 1280px screen, 37px on a
            // phone — and FullCalendar truncates from the right. Leading with the
            // property name spent that entire budget on a string that is identical
            // on every booking (there is only ever one bookable villa), so the
            // guest name never survived. The slot goes first instead: a day and a
            // night booking on the same date are two separate rows in the cell,
            // and it is the only thing that tells them apart. Property still has
            // its own row in the detail modal.
            $slot  = $booking->slotKey();
            $label = $slot ? ucfirst($slot) . ' · ' : '';

            $events[] = [
                'id'              => 'booking-' . $booking->id,
                'title'           => $label . ($booking->user->full_name ?? 'Guest'),
                'start'           => $booking->check_in_date->format('Y-m-d'),
                'end'             => $booking->check_out_date->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'backgroundColor' => $color['bg'],
                'borderColor'     => $color['border'],
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'type'           => 'booking',
                    'booking_id'     => $booking->id,
                    'booking_ref'    => $booking->booking_ref,
                    'guest'          => $booking->user->full_name ?? 'Guest',
                    'property'       => $booking->property->property_name ?? 'N/A',
                    'property_id'    => $booking->property_id,
                    'status'         => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'num_guests'     => $booking->num_guests,
                    'total_amount'   => $booking->total_amount,
                    'check_in'       => $booking->check_in_date->format('M d, Y'),
                    'check_out'      => $booking->check_out_date->format('M d, Y'),
                ],
            ];
        }

        // ── Availability Blocks ────────────────────────────────────
        $blocks = AvailabilityBlock::with('property')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_date', '<=', $start)
                         ->where('end_date', '>=', $end);
                  });
            })
            ->get();

        foreach ($blocks as $block) {
            $reason = ucfirst(str_replace('_', ' ', $block->reason));
            $endExclusive = \Carbon\Carbon::parse($block->end_date)->addDay()->format('Y-m-d');

            $props = [
                'type'       => 'block',
                'block_id'   => $block->id,
                'property'   => $block->property->property_name ?? 'N/A',
                'reason'     => $reason,
                'notes'      => $block->notes,
                'start_date' => $block->start_date,
                'end_date'   => $block->end_date,
            ];

            // DALAWANG event kada block, sinasadya.
            //
            // Dati ay iisa, at `display => 'background'` — at ang
            // background event sa FullCalendar ay HINDI kailanman
            // nagpapakita ng title. Kaya ang dahilan ng block ay
            // ginagawa lang at itinatapon, at ang tanging palatandaan
            // ay isang #fee2e2 na tint na halos hindi mapansin sa isang
            // maputing calendar — "walang senyas" ang tingin ng admin.
            // Hindi rin kailanman naaabot ang eventClick na `type ===
            // 'block'` sa view, dahil hindi naman puwedeng i-click ang
            // background event: patay na code ang buong block-detail
            // modal (at ang Delete na kasama nito).
            //
            // 1) Naka-label na all-day bar — ito ang nakikita at
            //    nacli-click. 2) Ang tint pa rin sa buong araw, para
            //    mabasa agad na sarado ang petsa at hindi lang "may
            //    nakaiskedyul dito".
            $events[] = [
                'id'              => 'block-' . $block->id,
                'title'           => '🔒 Blocked — ' . $reason,
                'start'           => $block->start_date,
                'end'             => $endExclusive,
                'allDay'          => true,
                'backgroundColor' => '#dc2626',
                'borderColor'     => '#b91c1c',
                'textColor'       => '#fff',
                'extendedProps'   => $props,
            ];

            // Kailangang may extendedProps.type pa rin ito: dumadaan
            // ang RAW JSON sa property/status filter ng view, na
            // dire-diretsong bumabasa ng `e.extendedProps.type`.
            $events[] = [
                'id'              => 'block-bg-' . $block->id,
                'start'           => $block->start_date,
                'end'             => $endExclusive,
                'display'         => 'background',
                'backgroundColor' => '#fecaca',
                'extendedProps'   => ['type' => 'block'],
            ];
        }

        return response()->json($events);
    }

    // ── Drag & Drop — move booking dates ──────────────────────────
    public function moveBooking(Request $request, Booking $booking)
    {
        $request->validate([
            'check_in_date'  => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $oldIn  = $booking->check_in_date->format('M d, Y');
        $oldOut = $booking->check_out_date->format('M d, Y');

        $newIn  = \Carbon\Carbon::parse($request->check_in_date);
        $newOut = \Carbon\Carbon::parse($request->check_out_date);
        $nights = $newIn->diffInDays($newOut);

        // Petsa lang ang binabago ng drag — nananatili ang oras ng slot,
        // kaya iyon ang isinasama sa window na tinitingnan.
        $newCheckIn  = \Carbon\Carbon::parse($newIn->format('Y-m-d') . ' ' . $booking->check_in_time);
        $newCheckOut = \Carbon\Carbon::parse($newOut->format('Y-m-d') . ' ' . $booking->check_out_time);

        // Dati, WALANG availability check dito — ang pinaka-maluwag na
        // butas sa buong sistema: kayang i-drag ng admin ang isang
        // booking nang diretso sa ibabaw ng iba at walang pipigil.
        // Dumadaan na ito ngayon sa parehong lock at parehong tseke ng
        // lahat ng ibang path (Booking::reserveSlot()).
        $moved = Booking::reserveSlot(
            $booking->property_id,
            $newCheckIn,
            $newCheckOut,
            function () use ($booking, $newIn, $newOut, $nights) {
                $booking->update([
                    'check_in_date'  => $newIn,
                    'check_out_date' => $newOut,
                    'num_nights'     => $nights,
                ]);

                return $booking;
            },
            $booking->id
        );

        if ($moved === null) {
            return response()->json([
                'success' => false,
                'message' => Booking::unavailableMessage(
                    $booking->property_id, $newCheckIn,
                    'Another booking already holds that date and slot. The booking was not moved.',
                    forStaff: true
                ),
            ], 409);
        }

        \App\Models\StaffLog::record(
            'moved_booking', 'bookings', $booking->id,
            "Moved {$booking->booking_ref} from {$oldIn}–{$oldOut} to {$newIn->format('M d, Y')}–{$newOut->format('M d, Y')} via calendar"
        );

        // Realtime broadcast lang ito — hindi dapat maka-block sa AJAX
        // response (na-move na ang booking sa DB sa puntong ito) kung
        // mag-fail ang Pusher.
        try {
            event(new BookingUpdated($booking, 'moved'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast BookingUpdated (calendar move): ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'nights' => $nights]);
    }

    // ── Quick Block from Calendar ──────────────────────────────────
    public function quickBlock(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'required|in:maintenance,owner_use,private_event,other',
            'notes'       => 'nullable|string|max:255',
        ]);

        $block = AvailabilityBlock::create([
            'property_id' => $request->property_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'notes'       => $request->notes,
            'created_by'  => Auth::id(),
        ]);

        return response()->json(['success' => true, 'block_id' => $block->id]);
    }

    // ── Delete Block from Calendar ─────────────────────────────────
    public function deleteBlock(AvailabilityBlock $block)
    {
        $block->delete();
        return response()->json(['success' => true]);
    }
}