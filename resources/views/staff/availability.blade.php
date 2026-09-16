@extends('layouts.staff')

@section('title', 'Availability — Villa Elena Staff')
@section('page-title', 'Villa Availability')
@section('page-subtitle', $start->format('M j') . ' — ' . $end->format('M j, Y'))

@push('styles')
<style>
.main{max-width:960px;}

/* ── Range nav ── */
.range-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;
    background:#fff;border:1px solid var(--border);border-radius:12px;
    padding:12px 18px;margin-bottom:18px;}
.range-label{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;color:var(--navy);}
.range-btns{display:flex;gap:8px;}
.range-btn{background:#fff;border:1.5px solid var(--border);border-radius:8px;padding:8px 14px;
    font-size: 14px;font-weight:600;color:var(--muted);text-decoration:none;
    font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.range-btn:hover{border-color:var(--navy);color:var(--navy);}
.range-btn.disabled{opacity:.4;pointer-events:none;}

/* ── Legend ── */
.legend{display:flex;gap:18px;flex-wrap:wrap;font-size: 14px;color:var(--muted);
    margin-bottom:16px;padding:0 4px;}
.legend-item{display:flex;align-items:center;gap:6px;}
.legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0;}
.dot-free{background:#16a34a;}
.dot-booked{background:#dc2626;}
.dot-blocked{background:#d97706;}
.dot-past{background:#cbd5e1;}

/* ── Grid ── */
.grid-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;}
.grid-head{display:grid;grid-template-columns:150px 1fr 1fr;
    border-bottom:1px solid var(--border);background:#f8fafc;}
.grid-head div{padding:12px 16px;font-size: 13px;font-weight:700;text-transform:uppercase;
    letter-spacing:.5px;color:var(--muted);}
.grid-row{display:grid;grid-template-columns:150px 1fr 1fr;border-bottom:1px solid #f1f5f9;}
.grid-row:last-child{border-bottom:none;}
.grid-row.today{background:#fffdf5;}

.date-cell{padding:12px 16px;display:flex;flex-direction:column;justify-content:center;
    border-right:1px solid #f1f5f9;}
.date-day{font-weight:700;font-size:14px;color:var(--navy);}
.date-dow{font-size: 13px;color:var(--muted);margin-top:1px;}
.today-tag{display:inline-block;background:var(--gold);color:#fff;font-size: 11px;font-weight:700;
    padding:1px 7px;border-radius:10px;margin-top:4px;width:fit-content;
    text-transform:uppercase;letter-spacing:.4px;}

.slot-cell{padding:10px 14px;border-right:1px solid #f1f5f9;display:flex;align-items:center;}
.slot-cell:last-child{border-right:none;}

.slot-box{display:flex;align-items:center;gap:9px;width:100%;border-radius:9px;
    padding:9px 12px;text-decoration:none;transition:all .15s;border:1.5px solid transparent;}
.slot-icon{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;
    justify-content:center;font-size: 13px;flex-shrink:0;}
.slot-text{min-width:0;flex:1;}
.slot-state{font-size: 14px;font-weight:600;line-height:1.3;}
.slot-sub{font-size: 13px;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

.slot-box.free{background:#f0fdf4;border-color:#bbf7d0;}
.slot-box.free:hover{border-color:#16a34a;transform:translateY(-1px);}
.slot-box.free .slot-icon{background:#dcfce7;color:#15803d;}
.slot-box.free .slot-state{color:#15803d;}
.slot-box.free .slot-sub{color:#4d7c0f;}

.slot-box.booked{background:#fef2f2;border-color:#fecaca;}
.slot-box.booked .slot-icon{background:#fee2e2;color:#dc2626;}
.slot-box.booked .slot-state{color:#dc2626;}
.slot-box.booked .slot-sub{color:#b91c1c;}

.slot-box.blocked{background:#fffbeb;border-color:#fde68a;}
.slot-box.blocked .slot-icon{background:#fef3c7;color:#a16207;}
.slot-box.blocked .slot-state{color:#a16207;}
.slot-box.blocked .slot-sub{color:#a16207;}

.slot-box.past{background:#f8fafc;border-color:#e2e8f0;}
.slot-box.past .slot-icon{background:#e2e8f0;color:#94a3b8;}
.slot-box.past .slot-state{color:#94a3b8;}

.hint{font-size: 14px;color:var(--muted);margin-top:14px;text-align:center;}

@media (max-width:640px){
    .grid-head{display:none;}
    .grid-row{grid-template-columns:1fr;}
    .date-cell{border-right:none;border-bottom:1px solid #f1f5f9;flex-direction:row;
        align-items:center;gap:8px;background:#f8fafc;}
    .date-dow{margin-top:0;}
    .today-tag{margin-top:0;}
    .slot-cell{border-right:none;}
    .slot-cell::before{content:attr(data-slot);font-size: 12px;font-weight:700;color:var(--muted);
        text-transform:uppercase;letter-spacing:.5px;width:52px;flex-shrink:0;}
}
</style>
@endpush

@section('content')

    <div class="range-bar">
        <div class="range-label">{{ $start->format('M j') }} — {{ $end->format('M j, Y') }}</div>
        <div class="range-btns">
            <a href="{{ route('staff.availability', ['start' => $prevDate]) }}"
               class="range-btn {{ $prevDate ? '' : 'disabled' }}">
                <i class="bi bi-chevron-left"></i> Previous
            </a>
            <a href="{{ route('staff.availability') }}" class="range-btn">Today</a>
            <a href="{{ route('staff.availability', ['start' => $nextDate]) }}" class="range-btn">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="legend">
        <div class="legend-item"><span class="legend-dot dot-free"></span> Available — click to book</div>
        <div class="legend-item"><span class="legend-dot dot-booked"></span> Booked</div>
        <div class="legend-item"><span class="legend-dot dot-blocked"></span> Blocked by admin</div>
        <div class="legend-item"><span class="legend-dot dot-past"></span> Already passed</div>
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
