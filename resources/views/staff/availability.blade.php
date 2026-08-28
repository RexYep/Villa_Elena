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
    font-size:12px;font-weight:600;color:var(--muted);text-decoration:none;
    font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.range-btn:hover{border-color:var(--navy);color:var(--navy);}
.range-btn.disabled{opacity:.4;pointer-events:none;}

/* ── Legend ── */
.legend{display:flex;gap:18px;flex-wrap:wrap;font-size:12px;color:var(--muted);
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
.grid-head div{padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;
    letter-spacing:.5px;color:var(--muted);}
.grid-row{display:grid;grid-template-columns:150px 1fr 1fr;border-bottom:1px solid #f1f5f9;}
.grid-row:last-child{border-bottom:none;}
.grid-row.today{background:#fffdf5;}

.date-cell{padding:12px 16px;display:flex;flex-direction:column;justify-content:center;
    border-right:1px solid #f1f5f9;}
.date-day{font-weight:700;font-size:14px;color:var(--navy);}
.date-dow{font-size:11px;color:var(--muted);margin-top:1px;}
.today-tag{display:inline-block;background:var(--gold);color:#fff;font-size:9px;font-weight:700;
    padding:1px 7px;border-radius:10px;margin-top:4px;width:fit-content;
    text-transform:uppercase;letter-spacing:.4px;}

.slot-cell{padding:10px 14px;border-right:1px solid #f1f5f9;display:flex;align-items:center;}
.slot-cell:last-child{border-right:none;}

.slot-box{display:flex;align-items:center;gap:9px;width:100%;border-radius:9px;
    padding:9px 12px;text-decoration:none;transition:all .15s;border:1.5px solid transparent;}
.slot-icon{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;
    justify-content:center;font-size:11px;flex-shrink:0;}
.slot-text{min-width:0;flex:1;}
.slot-state{font-size:12px;font-weight:600;line-height:1.3;}
.slot-sub{font-size:11px;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

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

.hint{font-size:12px;color:var(--muted);margin-top:14px;text-align:center;}

@media (max-width:640px){
    .grid-head{display:none;}
    .grid-row{grid-template-columns:1fr;}
    .date-cell{border-right:none;border-bottom:1px solid #f1f5f9;flex-direction:row;
        align-items:center;gap:8px;background:#f8fafc;}
    .date-dow{margin-top:0;}
    .today-tag{margin-top:0;}
    .slot-cell{border-right:none;}
    .slot-cell::before{content:attr(data-slot);font-size:10px;font-weight:700;color:var(--muted);
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

    <div class="grid-card">
        <div class="grid-head">
            <div>Date</div>
            <div>Day — 8:00 AM to 5:00 PM</div>
            <div>Night — 7:00 PM to 6:00 AM</div>
        </div>

        @foreach($grid as $row)
        <div class="grid-row {{ $row['is_today'] ? 'today' : '' }}">
            <div class="date-cell">
                <div class="date-day">{{ $row['date']->format('M j') }}</div>
                <div class="date-dow">{{ $row['date']->format('l') }}</div>
                @if($row['is_today'])
                    <span class="today-tag">Today</span>
                @endif
            </div>

            @foreach($row['slots'] as $slotKey => $slot)
                <div class="slot-cell" data-slot="{{ ucfirst($slotKey) }}">
                    @if($slot['state'] === 'free')
                        <a class="slot-box free"
                           href="{{ route('staff.walkin', ['date' => $row['date']->format('Y-m-d'), 'slot' => $slotKey]) }}"
                           title="Book this slot for {{ $row['date']->format('M j, Y') }}">
                            <span class="slot-icon"><i class="bi bi-plus-lg"></i></span>
                            <span class="slot-text">
                                <span class="slot-state">Available</span>
                                <span class="slot-sub d-block">
                                    @if ($slot['promo'])
                                        {{-- Ang tinatawid ay ang list price; ang presyong sinisingil
                                             ay ang nasa gilid nito. Ito ang sinasabi ni staff sa guest. --}}
                                        <s style="opacity:.55;">₱{{ number_format($slot['base'], 2) }}</s>
                                        <strong>₱{{ number_format($slot['price'], 2) }}</strong>
                                        · {{ $slot['promo'] }}
                                    @else
                                        ₱{{ number_format($slot['price'], 2) }} · book now
                                    @endif
                                </span>
                            </span>
                        </a>
                    @else
                        <div class="slot-box {{ $slot['state'] }}">
                            <span class="slot-icon">
                                @if($slot['state'] === 'booked')
                                    <i class="bi bi-person-fill"></i>
                                @elseif($slot['state'] === 'blocked')
                                    <i class="bi bi-lock-fill"></i>
                                @else
                                    <i class="bi bi-dash-lg"></i>
                                @endif
                            </span>
                            <span class="slot-text">
                                <span class="slot-state">
                                    @if($slot['state'] === 'booked') Booked
                                    @elseif($slot['state'] === 'blocked') Blocked
                                    @else Passed @endif
                                </span>
                                @if($slot['guest'])
                                    <span class="slot-sub d-block">{{ $slot['guest'] }} · {{ $slot['label'] }}</span>
                                @elseif($slot['state'] === 'blocked')
                                    <span class="slot-sub d-block">{{ $slot['label'] }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endforeach
    </div>

    <div class="hint">
        <i class="bi bi-info-circle me-1"></i>
        Showing {{ $days }} days. Availability follows the same rules as the booking form —
        expired unpaid holds free up automatically.
    </div>

@endsection
