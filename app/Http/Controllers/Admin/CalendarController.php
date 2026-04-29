<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\AvailabilityBlock;
use Illuminate\Http\Request;

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
            $events[] = [
                'id'              => 'booking-' . $booking->id,
                'title'           => ($booking->property->property_name ?? 'N/A') . ' — ' . ($booking->user->full_name ?? 'Guest'),
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
            $events[] = [
                'id'              => 'block-' . $block->id,
                'title'           => '🔒 ' . ($block->property->property_name ?? 'N/A') . ' — ' . ucfirst(str_replace('_', ' ', $block->reason)),
                'start'           => $block->start_date,
                'end'             => \Carbon\Carbon::parse($block->end_date)->addDay()->format('Y-m-d'),
                'backgroundColor' => '#fee2e2',
                'borderColor'     => '#fca5a5',
                'textColor'       => '#dc2626',
                'display'         => 'background', // renders as background stripe
                'extendedProps'   => [
                    'type'       => 'block',
                    'block_id'   => $block->id,
                    'property'   => $block->property->property_name ?? 'N/A',
                    'reason'     => ucfirst(str_replace('_', ' ', $block->reason)),
                    'notes'      => $block->notes,
                    'start_date' => $block->start_date,
                    'end_date'   => $block->end_date,
                ],
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

        $booking->update([
            'check_in_date'  => $newIn,
            'check_out_date' => $newOut,
            'num_nights'     => $nights,
        ]);

        \App\Models\StaffLog::record(
            'moved_booking', 'bookings', $booking->id,
            "Moved {$booking->booking_ref} from {$oldIn}–{$oldOut} to {$newIn->format('M d, Y')}–{$newOut->format('M d, Y')} via calendar"
        );

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
            'created_by'  => auth()->id(),
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