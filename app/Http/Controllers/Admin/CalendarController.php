<?php

namespace App\Http\Controllers\Admin;

use App\Events\BookingUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\AvailabilityBlock;
use App\Models\SlotWindow;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    // ── Calendar Page ──────────────────────────────────────────────
    /**
     * Ang villa LAMANG, hindi na `Property::all()`.
     *
     * Pitong row ang ibinibigay ng dating query — ang villa at ang anim na
     * informational room — at dalawang dropdown ang pinupuno nito. Walang
     * saysay ang una (isang listing lang naman ang puwedeng i-book, kaya
     * laging blangko ang calendar kapag room ang pinili) at MAPANIRA ang
     * ikalawa: nakakapag-block ng petsa sa isang room, na wala namang epekto
     * sa availability — tingnan ang quickBlock() sa ibaba.
     */
    public function index()
    {
        $villa = Property::where('type', 'villa')->firstOrFail();
        return view('admin.calendar.index', compact('villa'));
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
            $slot = $booking->slotKey();

            // `name` mula sa Booking::SLOTS, hindi ucfirst($slot) — ang huli
            // ay nagbubunga ng "Stay22" para sa 22-oras na slot.
            $slotName = $slot ? (Booking::SLOTS[$slot]['name'] ?? ucfirst($slot)) : '';
            $label = $slot ? $slotName . ' · ' : '';

            // Ang oras ay galing sa NAKAIMBAK na check_in_time/check_out_time,
            // HINDI sa Booking::SLOTS[$slot]['label'].
            //
            // Ang `extendStay()` ang sinasadyang eksepsiyon sa buong sistema:
            // binabago nito ang oras ng checkout ng isang naka-check-in nang
            // bisita, at hindi hinahawakan ang check_in_time. Kaya `night` pa
            // rin ang isinasagot ng slotKey() habang ang totoong labasan ay
            // ibang-iba na — at ang de-latang "7:00 PM – 6:00 AM" ay magiging
            // kasinungalingan. Pangalan lang ang kinukuha sa slot; ang mga
            // numero ay galing sa row mismo.
            $times = \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A')
                   . ' – ' . \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A');

            // Walang slot ang mga legacy na booking mula bago ang v5.1 (hal.
            // 2:00 PM na check-in), kaya NULL ang slotKey() doon. Ang saklaw ng
            // oras lang ang ipinapakita — mas mainam kaysa blangkong hanay.
            $slotDisplay = $slot ? $slotName . ' · ' . $times : $times;

            $events[] = [
                'id'              => 'booking-' . $booking->id,
                'title'           => $label . ($booking->user->full_name ?? 'Guest'),
                'start'           => $booking->check_in_date->format('Y-m-d'),
                'end'             => $booking->check_out_date->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'backgroundColor' => $color['bg'],
                'borderColor'     => $color['border'],
                'textColor'       => '#ffffff',
                // Kada event, hindi pangkalahatan. `editable: true` ang buong
                // calendar, kaya nada-drag ang lahat maliban sa `cancelled`.
                'editable'        => $booking->canBeMoved(),
                'extendedProps'   => [
                    'type'           => 'booking',
                    'movable'        => $booking->canBeMoved(),
                    'booking_id'     => $booking->id,
                    'booking_ref'    => $booking->booking_ref,
                    'guest'          => $booking->user->full_name ?? 'Guest',
                    'property'       => $booking->property->property_name ?? 'N/A',
                    'property_id'    => $booking->property_id,
                    'slot'           => $slot,
                    'slot_display'   => $slotDisplay,
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
                // Nada-drag din ang block bar dati: umuusad ito sa screen at
                // saka biglang babalik, dahil ini-revert ito ng eventDrop
                // (`type !== 'booking'`). Ang hindi pag-aalok ng paggalaw ay
                // mas malinaw kaysa sa pag-urong nito.
                'editable'        => false,
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

        // ── 22-Hour slot windows ───────────────────────────────────
        //
        // Kabaligtaran ng block: ito ay NAGBUBUKAS. Kaparehong hugis ng
        // dalawang-event (naka-label na bar + tint) sa parehong dahilan —
        // hindi nagpapakita ng title ang background event, kaya kung
        // iisa lang, walang makikitang dahilan ang admin.
        //
        // Ang bar ay umaabot sa ISANG petsa lang (ang check-in), hindi sa
        // buong 22 oras: ang petsa ng check-out ay isang ordinaryong
        // petsang may Day/Night, at ang pagpapahaba ng bar doon ay
        // magmumukhang sarado rin ito.
        $windows = SlotWindow::where('is_active', 1)
            ->whereBetween('check_in_date', [$start, $end])
            ->get();

        foreach ($windows as $window) {
            $slotName = Booking::SLOTS[$window->slot]['name'] ?? $window->slot;
            $dateStr = $window->check_in_date->format('Y-m-d');
            $endExclusive = $window->check_in_date->copy()->addDay()->format('Y-m-d');

            $events[] = [
                'id'              => 'slotwindow-' . $window->id,
                'title'           => '🏡 ' . $slotName . ' only — ' . $window->span_label,
                'start'           => $dateStr,
                'end'             => $endExclusive,
                'allDay'          => true,
                'editable'        => false,
                'backgroundColor' => '#0f766e',
                'borderColor'     => '#115e59',
                'textColor'       => '#fff',
                'extendedProps'   => [
                    'type'          => 'slot_window',
                    'window_id'     => $window->id,
                    'slot'          => $window->slot,
                    'slot_name'     => $slotName,
                    'span_label'    => $window->span_label,
                    'check_in_date' => $dateStr,
                    'notes'         => $window->notes,
                ],
            ];

            $events[] = [
                'id'              => 'slotwindow-bg-' . $window->id,
                'start'           => $dateStr,
                'end'             => $endExclusive,
                'display'         => 'background',
                'backgroundColor' => '#ccfbf1',
                'extendedProps'   => ['type' => 'slot_window'],
            ];
        }

        return response()->json($events);
    }

    // ── Drag & Drop — move booking dates ──────────────────────────
    public function moveBooking(Request $request, Booking $booking)
    {
        // `after_or_equal`, HINDI `after`.
        //
        // Ito ang dahilan ng "Error moving booking". Ang Day slot ay 8:00 AM–
        // 5:00 PM sa IISANG petsa, kaya `check_out_date === check_in_date` —
        // at hinihingi ng `after:` na mahigpit na mas huli, kaya BUMAGSAK ITO
        // SA BAWAT DAY BOOKING, palagi. 39 sa 55 booking sa calendar ang Day.
        //
        // WALA nang `check_out_date` dito. Ang drag ay nagpapalit ng PETSA
        // lamang; ang slot ang nagtatakda ng haba, at alam na iyon ng server
        // mula sa `check_in_time`. Ang pagpapasya nito sa kliyente ay
        // dalawang beses nang nagkamali sa iisang linya
        // (`.toISOString()` sa isang lokal na hatinggabi, na umuurong ng isang
        // araw sa UTC+8), at ang pinakahuling anyo niyon ay nakakapagpasok
        // sana ng Night booking na ang checkout ay BAGO pa ang check-in.
        //
        // Ito rin ang iniuutos ng CLAUDE.md: `Booking::slotDateTimes()` ang
        // iisang pinagmumulan ng katotohanan sa slot → oras, at dapat itong
        // daanan ng bawat controller sa halip na magbasa ng hilaw na field.
        $request->validate([
            'check_in_date' => 'required|date',
        ]);

        // Hindi na ito nada-drag sa feed (`editable` kada event), pero
        // POST-able pa rin ang ruta nang diretso. Tingnan ang
        // Booking::MOVABLE_STATUSES para sa dahilan.
        if (! $booking->canBeMoved()) {
            return response()->json([
                'success' => false,
                'message' => 'A ' . str_replace('_', ' ', $booking->status) . ' booking cannot be moved to another date.',
            ], 422);
        }

        $oldIn  = $booking->check_in_date->format('M d, Y');
        $oldOut = $booking->check_out_date->format('M d, Y');

        $newIn = \Carbon\Carbon::parse($request->check_in_date);

        // `exactSlotKey()`, HINDI `slotKey()`.
        //
        // Ang haba ang kinukuwenta dito, kaya EKSAKTONG tugma lang ang
        // maaaring pagbatayan. Ang `slotKey()` ay binabasa ang nakaimbak na
        // `slot` column, at sinasadyang PINAPANATILI iyon ang sagot na
        // `night` kahit inilipat na ng `extendStay()` ang checkout — tama
        // iyon para sa label, pero kung dito gagamitin, ang isang
        // pinahabang stay na idadrag sa ibang petsa ay muling isusulat sa
        // de-latang 7:00 PM–6:00 AM at mabubura ang extension na binayaran
        // ng bisita.
        $slot = $booking->exactSlotKey();

        if ($slot !== null) {
            // Ang slot ang nagsasabi ng haba — Day ay iisang petsa, Night ay
            // tumatawid sa hatinggabi. Ang server ang kumukuha nito, kaya
            // walang depekto sa petsa sa kliyente ang makakasira ng row.
            [$newCheckIn, $newCheckOut] = Booking::slotDateTimes($slot, $newIn->format('Y-m-d'));
        } else {
            // Mga legacy row bago ang v5.1 (hal. 2:00 PM na check-in) at ang
            // mga pinahabang stay: wala silang eksaktong tugmang slot.
            // Pinapanatili ang orihinal nitong haba sa halip na pilitin sa
            // isang slot na hindi naman nito kailanman sinunod.
            $minutes     = $booking->checkInDateTime()->diffInMinutes($booking->checkOutDateTime());
            $newCheckIn  = \Carbon\Carbon::parse($newIn->format('Y-m-d') . ' ' . $booking->check_in_time);
            $newCheckOut = $newCheckIn->copy()->addMinutes($minutes);
        }

        $newOut = $newCheckOut->copy()->startOfDay();

        // max(1, …) — kagaya ng LIMANG ibang path na sumusulat ng num_nights
        // (portal preview at submit, staff walk-in, admin create, extendStay).
        // Ito lang ang hubad na diffInDays, at 0 ang isinasagot niyon sa isang
        // Day booking, na iisang petsa lamang. LAHAT ng row sa database ay
        // num_nights = 1, at `sum('num_nights')` ang ginagamit ng
        // ReportController:115 — tahimik sanang kukulangin ang kabuuan.
        $nights = max(1, $newIn->diffInDays($newOut));

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
    /**
     * Ang property ay HINDI na galing sa request.
     *
     * Dati ay isang `exists:properties,id` lang ang bantay, at pumipili ang
     * admin mula sa dropdown ng lahat ng property. Piliin ang "Room 3" at
     * matagumpay ang lahat: nasusulat ang row, lumalabas ang pulang bar sa
     * calendar, may StaffLog entry — at BUKAS PA RIN ANG VILLA. Iisang
     * property_id lang naman ang tinitingnan ng `Booking::blockOn()` at ng
     * availability grid ng frontdesk: ang sa villa. Tahimik na palya iyon na
     * may tunay na bunga — akala ng may-ari sarado na ang petsa, at
     * ipinagbibili pa rin ito ng portal.
     *
     * Hindi ito tsinetsek bilang validation rule dahil wala namang tanong na
     * dapat itanong: iisa ang property na puwedeng i-block.
     */
    public function quickBlock(Request $request)
    {
        $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'required|in:maintenance,owner_use,private_event,other',
            'notes'       => 'nullable|string|max:255',
        ]);

        $villa = Property::where('type', 'villa')->firstOrFail();

        $block = AvailabilityBlock::create([
            'property_id' => $villa->id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'notes'       => $request->notes,
            'created_by'  => Auth::id(),
        ]);

        StaffLog::record('created_availability_block', 'availability_blocks', $block->id,
            "Blocked {$request->start_date} to {$request->end_date} via calendar ({$request->reason})");

        return response()->json(['success' => true, 'block_id' => $block->id]);
    }

    // ── Delete Block from Calendar ─────────────────────────────────
    /**
     * THE ONE THAT MATTERED MOST, and it recorded nothing at all.
     *
     * Creating a block at least left `availability_blocks.created_by` behind.
     * Deleting one destroys that row — so re-opening the villa for sale on
     * dates the owner had closed for maintenance or private use was the only
     * action in this controller with no trace of any kind, on either side.
     * The details are read before the delete because here they genuinely do
     * vanish with the row.
     */
    public function deleteBlock(AvailabilityBlock $block)
    {
        $summary = sprintf(
            '%s to %s (%s)',
            $block->start_date instanceof \DateTimeInterface ? $block->start_date->format('Y-m-d') : $block->start_date,
            $block->end_date instanceof \DateTimeInterface ? $block->end_date->format('Y-m-d') : $block->end_date,
            $block->reason ?: 'no reason given'
        );
        $blockId = $block->id;
        $propertyId = $block->property_id;

        $block->delete();

        StaffLog::record('deleted_availability_block', 'availability_blocks', $blockId,
            "Unblocked {$summary} on property #{$propertyId} — those dates are bookable again");

        return response()->json(['success' => true]);
    }

    // ── 22-Hour slot windows ───────────────────────────────────────
    /**
     * Binubuksan ang isang slot na hindi pang-araw-araw para sa ISANG petsa.
     *
     * Kabaligtaran ito ng quickBlock() sa itaas: ang block ay NAGSASARA
     * ng petsa, ang window ay NAGBUBUKAS ng slot na kung hindi ay hindi
     * inaalok. Nasa parehong pahina sila dahil pareho silang aksyon sa
     * kalendaryo, pero magkaibang-magkaiba ang ibig sabihin.
     *
     * IISANG PETSA ang tinatanggap, hindi saklaw: isang row = isang
     * inaalok na stay. Ang haba ay hindi iniimbak — hinahango ito sa
     * `Booking::slotDateTimes()`, kaya iisa lang ang nagsasabi niyon.
     */
    public function addSlotWindow(Request $request)
    {
        $request->validate([
            'slot' => 'required|in:'.implode(',', Booking::windowedSlotKeys()),
            'check_in_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:255',
            'confirm_conflict' => 'nullable|boolean',
        ]);

        $villa = Property::where('type', 'villa')->firstOrFail();
        $slot = $request->slot;
        $date = \Carbon\Carbon::parse($request->check_in_date)->format('Y-m-d');

        // Walang saysay ang window kung wala pang presyo ang slot: mananatili
        // itong hindi inaalok (tingnan ang Booking::slotsOfferedOn()), kaya
        // mas mabuting sabihin ito ngayon kaysa hayaang mag-isip ang admin
        // na gumana na ito.
        if (! $villa->isSlotPriced($slot)) {
            return response()->json([
                'success' => false,
                'message' => Booking::SLOTS[$slot]['name'].' has no price yet, so the date would still not be '
                    .'bookable. Set the rate in Properties first.',
            ], 422);
        }

        if (SlotWindow::query()->offeredOn($villa->id, $slot, $date)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'That date already offers the '.Booking::SLOTS[$slot]['name'].' stay.',
            ], 422);
        }

        // ⚠️ Ang isang window sa ibabaw ng umiiral nang booking ay
        // karaniwang pagkakamali, at DALAWA ang pinsala:
        //
        //   1. Hindi kailanman maipagbibili ang 22 oras — hawak na ng
        //      naunang booking ang bahagi ng saklaw nito.
        //   2. Dahil ang petsang may window ay 22-oras LAMANG, itatago
        //      nito ang Day/Night ng petsang iyon — kaya nawawala rin ang
        //      isang slot na maaaring mabibili pa naman.
        //
        // Hindi pagbabawal, kundi pagtatanong: baka nagpaplano ang admin
        // sa paligid ng isang kanselasyon. Kaparehong hati ng
        // Payment::manualEntryProblem() — ang "mali" ay hinaharangan, ang
        // "kaduda-duda" ay kailangang kumpirmahin.
        if (! $request->boolean('confirm_conflict')) {
            if ($clash = $this->slotWindowConflict($villa, $slot, $date)) {
                return response()->json([
                    'success' => false,
                    'needs_confirmation' => true,
                    'message' => $clash,
                ], 409);
            }
        }

        $window = SlotWindow::create([
            'property_id' => $villa->id,
            'slot' => $slot,
            'check_in_date' => $date,
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);

        StaffLog::record('created_slot_window', 'slot_windows', $window->id,
            "Opened the {$window->span_label} ".Booking::SLOTS[$slot]['name'].' stay'
            .' — that date now offers only that slot');

        return response()->json([
            'success' => true,
            'window_id' => $window->id,
            'span_label' => $window->span_label,
        ]);
    }

    /**
     * Ang dahilan kung bakit hindi magagamit ang isang window sa petsang
     * ito, o NULL kung malinaw. Teksto ang ibinabalik para maipakita nang
     * buo sa admin — hindi sapat ang isang boolean para makapagpasya siya.
     */
    private function slotWindowConflict(Property $villa, string $slot, string $date): ?string
    {
        [$checkIn, $checkOut] = Booking::slotDateTimes($slot, $date);

        // (1) May humahawak na ba sa saklaw ng 22-oras na stay?
        $overlapping = Booking::where('property_id', $villa->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_out_date', '>=', $checkIn->copy()->subDay())
            ->where('check_in_date', '<=', $checkOut->copy()->addDay())
            ->with('user')
            ->get()
            ->first(fn ($b) => $checkIn->lt($b->checkOutDateTime()) && $checkOut->gt($b->checkInDateTime()));

        if ($overlapping) {
            $who = $overlapping->user->full_name ?? 'a guest';

            return "{$overlapping->booking_ref} ({$who}) already overlaps that stay, so the "
                .Booking::SLOTS[$slot]['name'].' slot could never be booked on that date. '
                .'Open it anyway?';
        }

        // (2) Mawawala ba ang isang Day/Night na alok na maaaring mabili pa?
        //     Ito ang eksklusibidad na humahataw pabalik.
        $hidden = array_values(array_diff(
            Booking::slotsOfferedOn($date, $villa),
            [$slot]
        ));

        if ($hidden) {
            $names = array_map(fn ($k) => Booking::SLOTS[$k]['name'] ?? $k, $hidden);

            return 'Opening this will hide '.implode(' and ', $names).' on '
                .$checkIn->format('M j').', because a 22-hour date offers that slot only. Continue?';
        }

        return null;
    }

    public function deleteSlotWindow(SlotWindow $slotWindow)
    {
        // Binabasa bago ang delete — kasama ng row ang mga detalye.
        $summary = $slotWindow->span_label;
        $slotName = Booking::SLOTS[$slotWindow->slot]['name'] ?? $slotWindow->slot;
        $id = $slotWindow->id;

        $slotWindow->delete();

        StaffLog::record('deleted_slot_window', 'slot_windows', $id,
            "Closed the {$summary} {$slotName} stay — that date is back to the regular slots");

        return response()->json(['success' => true]);
    }
}