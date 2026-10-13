@extends('layouts.staff')

@section('title', 'Availability — Villa Elena Staff')
@section('page-title', 'Villa Availability')
@section('page-subtitle', $start->format('M j') . ' — ' . $end->format('M j, Y'))

@push('styles')
{{-- The grid has one column per priced slot, so two numbers follow from how
     many there are: how wide the page may grow, and the width below which
     the table no longer has room and each date becomes a stacked block. --}}
@php
    $manySlots = count($slotDefs) > 2;
    $pageMax = $manySlots ? 1280 : 960;
    $stackBelow = $manySlots ? 860 : 600;
@endphp
<style>
.main{max-width:{{ $pageMax }}px;}

/* ── Range nav ── */
/* Wraps: on a phone the three buttons need the whole row, and without the
   wrap the date was squeezed into a 36px column, five lines tall. */
.range-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
    background:#fff;border:1px solid var(--border);border-radius:12px;
    padding:12px 18px;margin-bottom:18px;}
.range-label{font-family: var(--font-display);font-size:16px;font-weight:600;color:var(--navy);
    white-space:nowrap;}
.range-btns{display:flex;gap:8px;}
.range-btn{background:#fff;border:1.5px solid var(--border);border-radius:8px;padding:8px 14px;
    font-size: 14px;font-weight:600;color:var(--muted);text-decoration:none;
    font-family: var(--font-body);display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.range-btn:hover{border-color:var(--navy);color:var(--navy);}
.range-btn.disabled{opacity:.4;pointer-events:none;}

/* ── Legend ── */
.legend{display:flex;gap:18px;flex-wrap:wrap;font-size: 14px;color:var(--muted);
    margin-bottom:16px;padding:0 4px;}
.legend-item{display:flex;align-items:center;gap:6px;}
.legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0;}
.dot-free{background:var(--state-green);}
.dot-booked{background:var(--state-red);}
.dot-blocked{background:var(--state-amber);}
.dot-past{background:var(--border);}
.legend-wide{width:26px;background:var(--border-strong);}

/* ── Grid ── */
/* The wrapper is a size container so the grid stacks by the room it has,
   not by the window: the sidebar takes 260px of a desktop window and none
   of a tablet's. */
#availabilityGrid{container:avail / inline-size;}
.grid-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;}
/* minmax(0,1fr), not 1fr: a bare 1fr track cannot go below its content's
   width, so each row sized its own columns, none lined up with the header,
   and the last column ran off the card. */
.grid-head{display:grid;grid-template-columns:150px repeat(var(--slot-cols,2),minmax(0,1fr));
    border-bottom:1px solid var(--border);background:var(--cream);}
.grid-head div{padding:12px 16px;font-size: 13px;font-weight:700;text-transform:uppercase;
    letter-spacing:.5px;color:var(--muted);}
.grid-row{display:grid;grid-template-columns:150px repeat(var(--slot-cols,2),minmax(0,1fr));border-bottom:1px solid var(--sand);}
.grid-row:last-child{border-bottom:none;}
.grid-row.today{background:var(--cream);}

.date-cell{padding:12px 16px;display:flex;flex-direction:column;justify-content:center;
    border-right:1px solid var(--sand);}
.date-day{font-weight:700;font-size:14px;color:var(--navy);}
.date-dow{font-size: 13px;color:var(--muted);margin-top:1px;}
/* Dark text on the gold, 6.7:1. White on it was 2.3:1. */
.today-tag{display:inline-block;background:var(--gold);color:var(--stone);font-size: 12px;font-weight:700;
    padding:1px 7px;border-radius:10px;margin-top:4px;width:fit-content;
    text-transform:uppercase;letter-spacing:.4px;}

.slot-cell{padding:10px 14px;border-right:1px solid var(--sand);display:flex;align-items:center;
    grid-column:span var(--span,1);}
.slot-cell:last-child{border-right:none;}

/* A 22-hour date: one cell across every column. Its name and times sit
   beside the box, because no column heading says them. */
.slot-cell.is-wide{gap:14px;}
.slot-kind{display:flex;flex-direction:column;flex:0 0 150px;font-size:13px;color:var(--muted);line-height:1.35;}
.slot-kind strong{font-size:14px;color:var(--navy);}

.slot-box{display:flex;align-items:center;gap:9px;width:100%;border-radius:9px;
    padding:9px 12px;text-decoration:none;transition:all .15s;border:1.5px solid transparent;}
.slot-icon{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;
    justify-content:center;font-size: 13px;flex-shrink:0;}
.slot-text{min-width:0;flex:1;}
.slot-state{font-size: 14px;font-weight:600;line-height:1.3;}
/* Wraps rather than cuts: this line is the price or the guest, which is
   what staff read out. */
.slot-sub{font-size: 13px;margin-top:1px;overflow-wrap:anywhere;}
.slot-who{display:flex;flex-wrap:wrap;column-gap:8px;}

.slot-box.free{background:var(--tag-emerald-bg);border-color:var(--state-green-line);}
.slot-box.free:hover{border-color:var(--state-green);transform:translateY(-1px);}
.slot-box.free .slot-icon{background:var(--tag-green-bg);color:var(--tag-green-fg);}
.slot-box.free .slot-state{color:var(--tag-green-fg);}
.slot-box.free .slot-sub{color:var(--tag-green-fg);}
/* The list price a promo replaces, struck through beside the charged one. */
.slot-sub s{opacity:.55;}

.slot-box.booked{background:var(--state-red-soft);border-color:var(--state-red-line);}
a.slot-box.booked:hover{border-color:var(--tag-red-fg);transform:translateY(-1px);}
.slot-box.booked .slot-icon{background:var(--tag-red-bg);color:var(--tag-red-fg);}
/* --tag-red-fg on the pale red: --state-red there was 4.4:1. */
.slot-box.booked .slot-state{color:var(--tag-red-fg);}
.slot-box.booked .slot-sub{color:var(--tag-red-fg);}

.slot-box.blocked{background:var(--state-amber-soft);border-color:var(--state-amber-line);}
.slot-box.blocked .slot-icon{background:var(--state-amber-tint);color:var(--tag-amber-fg);}
.slot-box.blocked .slot-state{color:var(--tag-amber-fg);}
.slot-box.blocked .slot-sub{color:var(--tag-amber-fg);}

.slot-box.past{background:var(--cream);border-color:var(--border);}
.slot-box.past .slot-icon{background:var(--border);color:var(--muted);}
.slot-box.past .slot-state{color:var(--muted);}

/* Hindi inaalok sa petsang ito — mas mapurol pa kaysa "past", dahil ito
   ang cell na dapat hindi tumatawag ng pansin. Dashed ang border para
   maiba agad sa isang tunay na estado (bakante / booked / sarado). */
.slot-box.unoffered{background:var(--cream);border-style:dashed;border-color:var(--border);}
/* The words stay at full --muted: at .8 opacity they were 3.4:1. The dashed
   edge, the faded icon and the italic are what set this cell apart. */
.slot-box.unoffered .slot-icon{background:var(--sand);color:var(--muted);opacity:.6;}
.slot-box.unoffered .slot-state{color:var(--muted);}
.slot-box.unoffered .slot-sub{color:var(--muted);font-style:italic;}

.hint{font-size: 14px;color:var(--muted);margin-top:14px;text-align:center;}

@container avail (max-width:{{ $stackBelow }}px){
    .grid-head{display:none;}
    .grid-row{grid-template-columns:minmax(0,1fr);}
    .date-cell{border-right:none;border-bottom:1px solid var(--sand);flex-direction:row;
        align-items:center;gap:8px;background:var(--cream);}
    .date-dow{margin-top:0;}
    .today-tag{margin-top:0;}
    .slot-cell{border-right:none;grid-column:auto;}
    /* The label below already names the slot. */
    .slot-kind{display:none;}
    .slot-cell::before{content:attr(data-slot);font-size: 12px;font-weight:700;color:var(--muted);
        text-transform:uppercase;letter-spacing:.5px;width:72px;flex-shrink:0;}
}

@media (max-width:480px){
    .range-btns{width:100%;}
    .range-btn{flex:1;justify-content:center;}
}
</style>
@endpush

@section('content')

    <div class="range-bar">
        <div class="range-label">{{ $start->format('M j') }} — {{ $end->format('M j, Y') }}</div>
        <div class="range-btns">
            {{-- Not a link when there is nothing earlier to show: a link styled
                 as disabled could still be reached with Tab and followed. --}}
            @if ($prevDate)
                <a href="{{ route('staff.availability', ['start' => $prevDate]) }}" class="range-btn">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i> Previous
                </a>
            @else
                <span class="range-btn disabled" aria-disabled="true">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i> Previous
                </span>
            @endif
            <a href="{{ route('staff.availability') }}" class="range-btn">Today</a>
            <a href="{{ route('staff.availability', ['start' => $nextDate]) }}" class="range-btn">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="legend">
        <div class="legend-item"><span class="legend-dot dot-free"></span> Available — click to book</div>
        <div class="legend-item"><span class="legend-dot dot-booked"></span> Booked — click to open</div>
        <div class="legend-item"><span class="legend-dot dot-blocked"></span> Blocked by admin</div>
        <div class="legend-item"><span class="legend-dot dot-past"></span> Already passed</div>
        {{-- Only when a 22-hour slot is priced: otherwise no row is ever wide. --}}
        @if ($hasWideDates)
            <div class="legend-item"><span class="legend-dot legend-wide"></span> One wide row — a 22-hour date, the only slot sold that day</div>
        @endif
    </div>

    <div id="availabilityGrid">
        @include('staff._availability_grid')
    </div>

    <div class="hint">
        <i class="bi bi-info-circle me-1"></i>
        Showing {{ $days }} days. Availability follows the same rules as the booking form —
        expired unpaid holds free up automatically.
    </div>

@endsection

@push('scripts')
<script>
// ── Live availability grid ─────────────────────────────────────────
//
// Dating iginuguhit minsan: ang slot na na-book, nakumpirma, kinansela o
// hinarangan matapos na-load ang page ay nanatiling "Available" hanggang
// i-reload — kayang alukin ni staff ang slot na nakuha na.
//
// Walang panuntunan ng availability rito. Kapag may nagbago, kinukuha muli
// ang grid mula sa server (iisang Blade partial at buildSlotGrid() sa page)
// at ipinapalit. Kaya ang signal na nadoble, naantala o nawala ay walang
// masamang epekto, at ang update ay hindi puwedeng sumalungat sa reload.
(function () {
    const wrap = document.getElementById('availabilityGrid');
    if (!wrap) return;

    const GRID_URL = @json(route('staff.availability.grid', ['start' => $start->format('Y-m-d')]));
    let timer = null;
    let inFlight = false;
    let again = false;

    function refresh() {
        clearTimeout(timer);
        // Debounce: iisang booking ay kadalasang nagsusulat nang maraming beses.
        timer = setTimeout(function () {
            if (inFlight) { again = true; return; }
            inFlight = true;

            fetch(GRID_URL, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) throw new Error('status ' + res.status);
                    return res.text();
                })
                .then(function (html) {
                    // Ipinapalit LANG kapag tunay na nagbago: ang 60s na salo
                    // ay nagtatanong kahit walang pagbabago, at ang pagpalit
                    // ng DOM sa ilalim ng daliri ni staff ay walang saysay.
                    if (wrap.innerHTML.trim() !== html.trim()) {
                        wrap.innerHTML = html;
                    }
                })
                .catch(function () { /* susunod na signal o 60s na tick */ })
                .finally(function () {
                    inFlight = false;
                    // Dumating ang signal habang nagtatanong: tanungin muli,
                    // kung hindi ay nawawala ang pagbabagong iyon.
                    if (again) { again = false; refresh(); }
                });
        }, 400);
    }

    function listen(channel) {
        if (!channel || channel.__availabilityBound) return;
        channel.__availabilityBound = true;
        channel.bind('availability.changed', refresh);
    }

    // Ang staff realtime script ay nilo-load PAGKATAPOS ng script na ito.
    if (window.rtStaffChannel) listen(window.rtStaffChannel);
    document.addEventListener('staff:realtime-ready', function (e) { listen(e.detail.channel); });

    // Salo kapag walang Pusher, at para sa mga pagbabagong walang event:
    // expired na unpaid hold, at slot na lumipas.
    setInterval(function () { if (!document.hidden) refresh(); }, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
</script>
@endpush
