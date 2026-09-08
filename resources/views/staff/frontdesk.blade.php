@extends('layouts.staff')

@section('title', 'Frontdesk — Villa Elena Staff')
@section('page-title', 'Frontdesk Operations')
@section('page-subtitle', today()->format('l, F j, Y'))

@section('frontdesk-badge')
    @if ($stats['check_ins_today'] + $stats['check_outs_today'] > 0)
        <span class="nav-badge">{{ $stats['check_ins_today'] + $stats['check_outs_today'] }}</span>
    @endif
@endsection

@section('sidebar-extra')

@endsection

@section('topbar-right')
    <div class="text-end">
        <div class="live-clock" id="liveClock"></div>
        <div class="live-date">Philippine Standard Time</div>
    </div>
@endsection

@push('styles')
    <style>
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .live-clock {
            font-size: 20px;
            font-weight: 700;
            color: var(--navy);
            font-family: 'Playfair Display', serif;
        }

        .live-date {
            font-size: 13px;
            color: var(--muted);
            text-align: right;
        }

        /* ── Stats ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 18px;
        }

        /* ── Villa status strip ──
       Pumalit sa dating "Occupied / Available" na counters, na binibilang
       ang lahat ng property row (1 villa + 3 walang-pangalang info-only na
       room). Nagpapakita iyon ng "Available 3" kahit isa lang ang totoong
       bookable at kuha na ito — mapanlinlang sa frontdesk. */
        .villa-strip {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
        }

        .villa-badge {
            width: 52px;
            height: 52px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            flex-shrink: 0;
        }

        .villa-badge.occupied {
            background: #fee2e2;
            color: #dc2626;
        }

        .villa-badge.available {
            background: #dcfce7;
            color: #15803d;
        }

        .villa-badge.maintenance {
            background: #fef3c7;
            color: #a16207;
        }

        .villa-main {
            flex: 1;
            min-width: 0;
        }

        .villa-name {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--navy);
        }

        .villa-state {
            font-size: 13px;
            margin-top: 3px;
        }

        .villa-state.occupied {
            color: #dc2626;
        }

        .villa-state.available {
            color: #15803d;
        }

        .villa-state.maintenance {
            color: #a16207;
        }

        .villa-next {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        .next-slot {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 8px 13px;
            text-align: center;
            text-decoration: none;
            min-width: 104px;
            transition: all .2s;
        }

        .next-slot-lbl {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
        }

        .next-slot-val {
            font-size: 14px;
            font-weight: 700;
            margin-top: 2px;
        }

        .next-slot.free {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .next-slot.free .next-slot-val {
            color: #15803d;
        }

        .next-slot.free:hover {
            border-color: #16a34a;
            transform: translateY(-1px);
        }

        .next-slot.taken {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .next-slot.taken .next-slot-val {
            color: #dc2626;
        }

        .next-slot.past {
            background: #f8fafc;
        }

        .next-slot.past .next-slot-val {
            color: #94a3b8;
        }

        @media (max-width:760px) {
            .villa-strip {
                flex-direction: column;
                align-items: flex-start;
            }

            .villa-next {
                width: 100%;
            }

            .next-slot {
                flex: 1;
            }
        }

        /* ── Cleaning banner ──
       Ang mga housekeeping task ay naiipon dati dahil kailangan pang
       pumunta sa hiwalay na tab para makita at maisara ang mga ito. Dito na
       ito lumalabas, kasama ang totoong deadline (oras ng susunod na
       check-in), na may isang pindot na "Mark cleaned". */
        .clean-banner {
            display: flex;
            align-items: center;
            gap: 14px;
            border-radius: 14px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1.5px solid;
        }

        .clean-banner.urgent {
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .clean-banner.soon {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .clean-banner.calm {
            background: #f0f9ff;
            border-color: #bae6fd;
        }

        .clean-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
        }

        .clean-banner.urgent .clean-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .clean-banner.soon .clean-icon {
            background: #fef3c7;
            color: #a16207;
        }

        .clean-banner.calm .clean-icon {
            background: #e0f2fe;
            color: #0369a1;
        }

        .clean-main {
            flex: 1;
            min-width: 0;
        }

        .clean-title {
            font-weight: 700;
            font-size: 14px;
        }

        .clean-banner.urgent .clean-title {
            color: #dc2626;
        }

        .clean-banner.soon .clean-title {
            color: #a16207;
        }

        .clean-banner.calm .clean-title {
            color: #0369a1;
        }

        .clean-sub {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
        }

        .btn-clean {
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
            transition: all .2s;
        }

        .btn-clean:hover {
            background: #15803d;
        }

        .task-ready {
            font-size: 13px;
            margin-top: 3px;
            font-weight: 600;
        }

        .task-ready.late {
            color: #dc2626;
        }

        .task-ready.soon {
            color: #a16207;
        }

        .task-ready.ok {
            color: #15803d;
        }

        @media (max-width:640px) {
            .clean-banner {
                flex-wrap: wrap;
            }

            .btn-clean {
                width: 100%;
                justify-content: center;
            }
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 16px 18px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .stat-val {
            font-size: 26px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            color: var(--navy);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 5px;
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 7px;
            transition: all .2s;
        }

        .tab-btn.active {
            background: var(--navy);
            color: #fff;
        }

        .tab-btn .cnt {
            background: rgba(255, 255, 255, .2);
            padding: 0 6px;
            border-radius: 10px;
            font-size: 13px;
        }

        .tab-btn:not(.active) .cnt {
            background: #f1f5f9;
            color: var(--muted);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
        }

        .card-body {
            padding: 0;
        }

        /* ── Booking Row ── */
        .booking-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid #f8fafc;
            transition: background .15s;
        }

        .booking-row:last-child {
            border-bottom: none;
        }

        .booking-row:hover {
            background: #fafbfc;
        }

        .guest-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--navy);
            flex-shrink: 0;
        }

        .booking-info {
            flex: 1;
            min-width: 0;
        }

        .booking-name {
            font-weight: 600;
            font-size: 14px;
        }

        .booking-ref {
            font-size: 14px;
            color: var(--muted);
        }

        .booking-prop {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
        }

        .booking-meta {
            display: flex;
            gap: 10px;
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .booking-action {
            flex-shrink: 0;
        }

        /* Buttons */
        .btn-checkin {
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-checkin:hover {
            background: #15803d;
        }

        .btn-checkout {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-checkout:hover {
            background: var(--navy-mid);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-start {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-complete {
            background: #dcfce7;
            color: #15803d;
        }

        .btn-start:hover {
            background: #bfdbfe;
        }

        .btn-complete:hover {
            background: #bbf7d0;
        }

        /* Badges */
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        .b-pending {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .b-confirmed {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .b-checked_in {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .b-checked_out {
            background: var(--tag-slate-bg);
            color: var(--tag-slate-fg);
        }

        /* Property grid */
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .prop-card {
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 14px;
        }

        .prop-card.available {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .prop-card.occupied {
            border-color: #fca5a5;
            background: #fef2f2;
        }

        .prop-card.maintenance {
            border-color: #fcd34d;
            background: #fffbeb;
        }

        .prop-card-name {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .prop-card-type {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .prop-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .dot-available {
            background: #16a34a;
        }

        .dot-occupied {
            background: #dc2626;
        }

        .dot-maintenance {
            background: #d97706;
        }

        .prop-guest {
            font-size: 13px;
            color: #374151;
            margin-top: 6px;
        }

        /* Housekeeping */
        .task-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid #f8fafc;
        }

        .task-row:last-child {
            border-bottom: none;
        }

        .task-type-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .task-info {
            flex: 1;
        }

        .task-prop {
            font-weight: 600;
            font-size: 13px;
        }

        .task-details {
            font-size: 14px;
            color: var(--muted);
            margin-top: 1px;
        }

        .task-date {
            font-size: 13px;
            margin-top: 3px;
        }

        .task-date.overdue {
            color: #dc2626;
            font-weight: 600;
        }

        .task-date.today {
            color: #d97706;
            font-weight: 600;
        }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }

        .empty i {
            font-size: 36px;
            display: block;
            margin-bottom: 10px;
            opacity: .35;
        }

        .empty p {
            font-size: 13px;
        }

        @media(max-width:900px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }

            .tab-bar {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .tab-btn {
                flex-shrink: 0;
            }
        }

        @media(max-width:560px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            .booking-row {
                flex-wrap: wrap;
            }

            .booking-action {
                width: 100%;
                flex-direction: row !important;
                justify-content: flex-end;
            }

            .property-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        @media(max-width:400px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif
    {{-- Wala nito dati: nasa modal ang "Record Payment" form, kaya ang
         anumang validation error nito ay tahimik na nawawala pagkatapos
         ng redirect — mukhang walang nangyari kay staff. --}}
    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <div id="rt-fd-banner">
        <span><i class="bi bi-arrow-repeat me-1"></i> Another staff member made changes here — refresh to see the
            latest.</span>
        <button type="button" onclick="rtFdRefresh()">Refresh</button>
    </div>

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="stat-val">{{ $stats['check_ins_today'] }}</div>
            <div class="stat-lbl">Check-ins Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-blue"><i class="bi bi-box-arrow-right"></i></div>
            <div class="stat-val">{{ $stats['check_outs_today'] }}</div>
            <div class="stat-lbl">Check-outs Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">{{ $stats['pending_bookings'] }}</div>
            <div class="stat-lbl">Pending Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-purple"><i class="bi bi-brush"></i></div>
            <div class="stat-val">{{ $stats['pending_tasks'] }}</div>
            <div class="stat-lbl">Housekeeping</div>
        </div>
    </div>

    {{-- Villa status + next two slots --}}
    @if ($villa)
        <div class="villa-strip">
            <div class="villa-badge {{ $villa->status }}">
                <i class="bi bi-house-door-fill"></i>
            </div>
            <div class="villa-main">
                <div class="villa-name">{{ $villa->property_name }}</div>
                <div class="villa-state {{ $villa->status }}">
                    @if ($villa->status === 'occupied' && $villa->currentBooking)
                        <i class="bi bi-person-fill"></i>
                        {{ $villa->currentBooking->user->full_name ?? 'Guest' }} —
                        checking out {{ $villa->currentBooking->checkOutDateTime()->format('M j, g:i A') }}
                    @elseif($villa->status === 'occupied')
                        <i class="bi bi-person-fill"></i> Currently occupied
                    @elseif($villa->status === 'maintenance')
                        <i class="bi bi-tools"></i> Under maintenance — not bookable
                    @else
                        <i class="bi bi-check-circle-fill"></i> Vacant right now
                    @endif
                </div>
            </div>
            <div class="villa-next">
                @foreach ($nextSlots as $row)
                    @foreach ($row['slots'] as $slotKey => $slot)
                        @if ($slot['state'] !== 'past')
                            @if ($slot['state'] === 'free')
                                <a class="next-slot free"
                                    href="{{ route('staff.walkin', ['date' => $row['date']->format('Y-m-d'), 'slot' => $slotKey]) }}">
                                    <div class="next-slot-lbl">{{ $row['is_today'] ? 'Today' : $row['date']->format('D') }}
                                        · {{ ucfirst($slotKey) }}</div>
                                    <div class="next-slot-val">Available</div>
                                </a>
                            @else
                                <div class="next-slot taken">
                                    <div class="next-slot-lbl">{{ $row['is_today'] ? 'Today' : $row['date']->format('D') }}
                                        · {{ ucfirst($slotKey) }}</div>
                                    <div class="next-slot-val">{{ $slot['state'] === 'blocked' ? 'Blocked' : 'Booked' }}
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endforeach
                <a href="{{ route('staff.availability') }}" class="next-slot">
                    <div class="next-slot-lbl">See all</div>
                    <div class="next-slot-val" style="color:var(--navy);">14 days →</div>
                </a>
            </div>
        </div>
    @endif

    {{-- Villa needs cleaning --}}
    @if ($urgentTask)
        @php
            $readyBy = $urgentTask->ready_by;
            $hoursLeft = now()->diffInMinutes($readyBy, false) / 60;
            $tone = $hoursLeft < 0 ? 'urgent' : ($hoursLeft <= 4 ? 'soon' : 'calm');
        @endphp
        <div class="clean-banner {{ $tone }}">
            <div class="clean-icon"><i class="bi bi-brush-fill"></i></div>
            <div class="clean-main">
                <div class="clean-title">
                    @if ($hoursLeft < 0)
                        Villa still not marked cleaned — next guest was due {{ $readyBy->diffForHumans() }}
                    @else
                        Villa must be ready by {{ $readyBy->format('g:i A') }}
                        @if ($readyBy->isToday())
                            today
                        @else
                            on {{ $readyBy->format('M j') }}
                        @endif
                    @endif
                </div>
                <div class="clean-sub">
                    @if ($urgentTask->next_guest)
                        Next check-in: {{ $urgentTask->next_guest }} ·
                    @endif
                    {{ $hoursLeft >= 0 ? 'about ' . $readyBy->diffForHumans(null, true) . ' left' : 'overdue' }}
                    · {{ ucfirst(str_replace('_', ' ', $urgentTask->status)) }}
                </div>
            </div>
            <form method="POST" action="{{ route('staff.tasks.complete', $urgentTask) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-clean">
                    <i class="bi bi-check-lg"></i> Mark cleaned
                </button>
            </form>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('checkins', this)">
            <i class="bi bi-box-arrow-in-right"></i> Check-ins
            <span class="cnt">{{ $checkIns->count() }}</span>
        </button>
        <button class="tab-btn" onclick="switchTab('checkouts', this)">
            <i class="bi bi-box-arrow-right"></i> Check-outs
            <span class="cnt">{{ $checkOuts->count() }}</span>
        </button>
        <button class="tab-btn" onclick="switchTab('guests', this)">
            <i class="bi bi-people"></i> Current Guests
            <span class="cnt">{{ $currentGuests->count() }}</span>
        </button>
        <button class="tab-btn" onclick="switchTab('pending', this)">
            <i class="bi bi-clock"></i> Pending
            <span class="cnt">{{ $pendingBookings->count() }}</span>
        </button>
        <button class="tab-btn" onclick="switchTab('housekeeping', this)">
            <i class="bi bi-brush"></i> Housekeeping
            <span class="cnt">{{ $pendingTasks->count() + $inProgressTasks->count() }}</span>
        </button>
        <a href="{{ route('staff.availability') }}" class="tab-btn" style="text-decoration:none;">
            <i class="bi bi-calendar3"></i> Availability
        </a>
    </div>

    {{-- Tab: Check-ins --}}
    <div class="tab-content active" id="tab-checkins">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-box-arrow-in-right me-2" style="color:#16a34a;"></i>Expected Check-ins Today</h3>
                <span class="text-muted-theme" style="font-size: 14px;">{{ today()->format('M d, Y') }}</span>
            </div>
            <div class="card-body">
                @forelse($checkIns as $booking)
                    <div class="booking-row">
                        <div class="guest-avatar">{{ strtoupper(substr($booking->user->full_name, 0, 1)) }}</div>
                        <div class="booking-info">
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            <div class="booking-ref">{{ $booking->booking_ref }}</div>
                            <div class="booking-prop"><i
                                    class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                            <div class="booking-meta">
                                <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                                <span><i class="bi bi-moon"></i> {{ $booking->num_nights }} nights</span>
                                <span><i class="bi bi-cash"></i> ₱{{ number_format($booking->balance_due, 0) }}
                                    balance</span>
                            </div>
                        </div>
                        <div class="booking-action"
                            style="display:flex; gap:8px; flex-direction:column; align-items:flex-end;">
                            {{-- Kapag may natitirang balance ang booking na ito,
                             hindi na basta magsu-submit ang form na ito —
                             hahadlangan muna ito ng handleCheckInSubmit()
                             para bumukas ang Check-in Confirmation modal,
                             na siyang mag-a-attach ng "balance_arrangement"
                             hidden input bago ipasa ang totoong submit. --}}
                            <form method="POST" action="{{ route('staff.checkin', $booking) }}"
                                id="checkinForm_{{ $booking->id }}"
                                onsubmit="return handleCheckInSubmit(event, {{ $booking->id }}, '{{ addslashes($booking->user->full_name) }}', {{ $booking->balance_due ?? 0 }})">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-checkin">
                                    <i class="bi bi-box-arrow-in-right"></i> Check In
                                </button>
                            </form>
                            <button type="button" class="btn-sm"
                                style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size: 13px;font-weight:600;cursor:pointer;"
                                onclick="openPaymentModal({{ $booking->id }}, '{{ $booking->booking_ref }}', {{ $booking->balance_due ?? 0 }})">
                                <i class="bi bi-cash"></i> Payment
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-calendar-check"></i>
                        <p>No check-ins scheduled for today.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tab: Check-outs --}}
    <div class="tab-content" id="tab-checkouts">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-box-arrow-right me-2" style="color:#1d4ed8;"></i>Expected Check-outs Today</h3>
            </div>
            <div class="card-body">
                @forelse($checkOuts as $booking)
                    <div class="booking-row">
                        <div class="guest-avatar">{{ strtoupper(substr($booking->user->full_name, 0, 1)) }}</div>
                        <div class="booking-info">
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            <div class="booking-ref">{{ $booking->booking_ref }}</div>
                            <div class="booking-prop"><i
                                    class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                            <div class="booking-meta">
                                <span><i class="bi bi-calendar3"></i> Checked in
                                    {{ $booking->check_in_date->format('M d') }}</span>
                                <span><i class="bi bi-cash"></i>
                                    @if ($booking->balance_due > 0)
                                        <span style="color:#dc2626;">₱{{ number_format($booking->balance_due, 0) }}
                                            unpaid</span>
                                    @else
                                        <span style="color:#16a34a;">Fully paid</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="booking-action"
                            style="display:flex; gap:8px; flex-direction:column; align-items:flex-end;"
                            data-checkout-row="{{ $booking->id }}">
                            <form method="POST" action="{{ route('staff.checkout', $booking) }}"
                                id="checkoutForm_{{ $booking->id }}" class="checkout-form-{{ $booking->id }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-checkout"
                                    onclick="return confirm('Check out {{ $booking->user->full_name }}?')">
                                    <i class="bi bi-box-arrow-right"></i> Check Out
                                </button>
                            </form>
                            <button type="button" class="btn-sm"
                                style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size: 13px;font-weight:600;cursor:pointer;"
                                onclick="openPaymentModal({{ $booking->id }}, '{{ $booking->booking_ref }}', {{ $booking->balance_due ?? 0 }})">
                                <i class="bi bi-cash"></i> Payment
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-calendar-x"></i>
                        <p>No check-outs scheduled for today.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tab: Current Guests --}}
    <div class="tab-content" id="tab-guests">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-people me-2" style="color:#7c3aed;"></i>Currently Checked In</h3>
                <span class="text-muted-theme" style="font-size: 14px;">{{ $currentGuests->count() }}
                    guest{{ $currentGuests->count() != 1 ? 's' : '' }}</span>
            </div>
            <div class="card-body">
                @forelse($currentGuests as $booking)
                    <div class="booking-row">
                        <div class="guest-avatar" style="background:#ede9fe;color:#7c3aed;">
                            {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                        </div>
                        <div class="booking-info">
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            <div class="booking-prop"><i
                                    class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                            <div class="booking-meta">
                                <span><i class="bi bi-calendar3"></i> In:
                                    {{ $booking->check_in_date->format('M d') }}</span>
                                <span><i class="bi bi-calendar3"></i> Out:
                                    {{ $booking->check_out_date->format('M d, Y') }}</span>
                                @php $daysLeft = today()->diffInDays($booking->check_out_date, false); @endphp
                                @if ($daysLeft <= 0)
                                    <span style="color:#dc2626;font-weight:600;"><i
                                            class="bi bi-exclamation-triangle"></i> Overdue</span>
                                @elseif($daysLeft == 1)
                                    <span style="color:#d97706;font-weight:600;"><i class="bi bi-clock"></i> Checking out
                                        tomorrow</span>
                                @else
                                    <span><i class="bi bi-moon"></i> {{ $daysLeft }} nights left</span>
                                @endif
                            </div>
                        </div>
                        <div class="booking-action"
                            style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                            <span class="badge b-checked_in">Checked In</span>
                            <button type="button" class="btn-sm"
                                style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size: 13px;font-weight:600;cursor:pointer;"
                                onclick="openPaymentModal({{ $booking->id }}, '{{ $booking->booking_ref }}', {{ $booking->balance_due ?? 0 }})">
                                <i class="bi bi-cash"></i> Payment
                            </button>
                            @if ($booking->check_out_date->isToday())
                                <form method="POST" action="{{ route('staff.checkout', $booking) }}"
                                    id="checkoutFormGuests_{{ $booking->id }}"
                                    class="checkout-form-{{ $booking->id }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-checkout" style="font-size: 13px;padding:5px 12px;"
                                        onclick="return confirm('Check out {{ $booking->user->full_name }}?')">
                                        Check Out Now
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-moon-stars"></i>
                        <p>No guests currently checked in.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tab: Pending Bookings --}}
    <div class="tab-content" id="tab-pending">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-clock me-2" style="color:#a16207;"></i>Pending Bookings</h3>
                <span class="text-muted-theme" style="font-size: 14px;">Awaiting payment from guest</span>
            </div>
            <div class="card-body">
                @forelse($pendingBookings as $booking)
                    <div class="booking-row">
                        <div class="guest-avatar tag-amber">
                            {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                        </div>
                        <div class="booking-info">
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            <div class="booking-ref">{{ $booking->booking_ref }} · {{ $booking->user->email }}</div>
                            <div class="booking-prop"><i
                                    class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                            <div class="booking-meta">
                                <span><i class="bi bi-calendar3"></i> {{ $booking->check_in_date->format('M d') }} –
                                    {{ $booking->check_out_date->format('M d, Y') }}</span>
                                <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                                <span>₱{{ number_format($booking->total_amount, 0) }}</span>
                            </div>
                        </div>
                        <div class="booking-action">
                            <span class="badge b-pending">Pending</span>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-check-all"></i>
                        <p>No pending bookings.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tab: Housekeeping --}}
    <div class="tab-content" id="tab-housekeeping">
        @if ($inProgressTasks->count())
            <div class="card mb-3">
                <div class="card-head">
                    <h3><i class="bi bi-brush me-2" style="color:#1d4ed8;"></i>In Progress</h3>
                </div>
                <div class="card-body">
                    @foreach ($inProgressTasks as $task)
                        <div class="task-row">
                            <div class="task-type-icon tag-blue">
                                <i class="bi bi-brush"></i>
                            </div>
                            <div class="task-info">
                                <div class="task-prop">{{ $task->property->property_name }}</div>
                                <div class="task-details">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}
                                    @if ($task->notes)
                                        · {{ $task->notes }}
                                    @endif
                                </div>
                                @include('staff.partials.task_deadline', ['task' => $task])
                            </div>
                            <div>
                                <form method="POST" action="{{ route('staff.tasks.complete', $task) }}"
                                    id="taskCompleteForm_{{ $task->id }}" class="task-form-{{ $task->id }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-sm btn-complete">
                                        <i class="bi bi-check-lg"></i> Mark Done
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-list-task me-2" style="color:#a16207;"></i>Pending Tasks</h3>
            </div>
            <div class="card-body">
                @forelse($pendingTasks as $task)
                    <div class="task-row">
                        <div class="task-type-icon tag-amber">
                            <i class="bi bi-{{ $task->task_type === 'checkout_clean' ? 'brush' : 'tools' }}"></i>
                        </div>
                        <div class="task-info">
                            <div class="task-prop">{{ $task->property->property_name }}</div>
                            <div class="task-details">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}
                                @if ($task->notes)
                                    · {{ $task->notes }}
                                @endif
                            </div>
                            @include('staff.partials.task_deadline', ['task' => $task])
                        </div>
                        <div style="display:flex;gap:6px;" class="task-actions-{{ $task->id }}">
                            <form method="POST" action="{{ route('staff.tasks.start', $task) }}"
                                id="taskStartForm_{{ $task->id }}" class="task-form-{{ $task->id }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-sm btn-start">
                                    <i class="bi bi-play-fill"></i> Start
                                </button>
                            </form>
                            <form method="POST" action="{{ route('staff.tasks.complete', $task) }}"
                                id="taskCompleteForm_{{ $task->id }}" class="task-form-{{ $task->id }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-sm btn-complete">
                                    <i class="bi bi-check-lg"></i> Done
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-check-circle"></i>
                        <p>All housekeeping tasks are complete!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Ang dating "Properties" tab ay nagpapakita ng isang card kada
         property row — kasama ang tatlong info-only na `type=room` na
         record na wala nang pangalan, kaya blangkong card ang lumalabas.
         Napalitan na ito ng Villa strip sa itaas (real-time na estado)
         at ng Availability page (petsa/slot na availability). --}}

@endsection

@section('modals')
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay" style="display:none;">
        <div class="modal-box" style="width:420px;">
            <!-- Modal Header -->
            <div class="modal-head">
                <div>
                    <div class="modal-title">Record Payment</div>
                    <div style="color:rgba(255,255,255,.4);font-size: 14px;margin-top:2px;" id="modalBookingRef"></div>
                </div>
                <button onclick="closePaymentModal()" class="modal-close">✕</button>
            </div>
            <!-- Modal Body -->
            <form id="paymentForm" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Balance Info -->
                    <div
                        style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:13px;color:#6B7A8D;">Balance Due</span>
                        <span style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#dc2626;"
                            id="modalBalance">₱0.00</span>
                    </div>
                    <!-- Amount -->
                    <div style="margin-bottom:14px;">
                        <label
                            style="font-size: 14px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Amount</label>
                        <input type="number" name="amount" id="modalAmount" required min="1" step="0.01"
                            style="border:1.5px solid #e4ddd0;border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s;"
                            placeholder="Enter amount" onfocus="this.style.borderColor='#2c2416'"
                            onblur="this.style.borderColor='#e4ddd0'">
                    </div>
                    <!-- Method + Type -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label
                                style="font-size: 14px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Method</label>
                            <select name="payment_method" required
                                style="border:1.5px solid #e4ddd0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;background:#fff;">
                                <option value="cash">Cash</option>
                                <option value="qrph">QR Ph (GCash / Maya / bank app)</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="font-size: 14px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Type</label>
                            <select name="payment_type" required
                                style="border:1.5px solid #e4ddd0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;background:#fff;">
                                <option value="balance">Balance Payment</option>
                                <option value="full_payment">Full Payment</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                    </div>
                    <!-- Notes -->
                    <div style="margin-bottom:18px;">
                        <label style="font-size: 14px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Notes
                            <span style="color:#6B7A8D;font-weight:400;">(optional)</span></label>
                        <input type="text" name="notes"
                            style="border:1.5px solid #e4ddd0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;"
                            placeholder="e.g. Cash received at frontdesk">
                    </div>
                    <!-- Kailangan lang kapag may kamukhang bayad na naitala
                         ngayong araw para sa booking na ito — hinaharangan ang
                         pagtatala hangga't hindi ito nakatik. -->
                    <label
                        style="display:flex;align-items:flex-start;gap:8px;font-size:13px;color:#4b5563;margin-bottom:18px;">
                        <input type="checkbox" name="confirm_duplicate" value="1" style="margin-top:3px;">
                        <span>This is a <strong>separate</strong> payment — tick only if the guest really paid this
                            amount again today.</span>
                    </label>
                    <!-- Submit -->
                    <button type="submit"
                        style="background:#2c2416;color:#fff;border:none;border-radius:9px;padding:13px;font-size:14px;font-weight:600;cursor:pointer;width:100%;font-family:'DM Sans',sans-serif;transition:all .2s;"
                        onmouseover="this.style.background='#b8943f';this.style.color='#2c2416'"
                        onmouseout="this.style.background='#2c2416';this.style.color='#fff'">
                        <i class="bi bi-check-circle me-2"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Check-in Confirmation Modal (bago) — bumubukas ito lang kapag may
         natitirang balance ang booking na gustong i-check-in. Pinipilit
         nitong pumili si staff sa dalawang landas bago matuloy ang check-in. -->
    <div id="checkinConfirmModal" class="modal-overlay" style="display:none;">
        <div class="modal-box" style="width:440px;">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Outstanding Balance</div>
                    <div style="color:rgba(255,255,255,.5);font-size: 14px;margin-top:2px;">Guest: <span
                            id="ccGuestName"></span></div>
                </div>
                <button type="button" onclick="closeCheckinConfirmModal()" class="modal-close">✕</button>
            </div>
            <div class="modal-body">
                <div
                    style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:13px;color:#991b1b;">Balance Due</span>
                    <span style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#dc2626;"
                        id="ccBalance">₱0.00</span>
                </div>

                <p style="font-size:12.5px;color:#6B7A8D;margin-bottom:16px;line-height:1.6;">
                    Choose what to do before proceeding with this guest's check-in:
                </p>

                <!-- Option A: Pay Now -->
                <button type="button" onclick="ccPayNow()"
                    style="width:100%;text-align:left;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    <div class="d-flex-gap-10">
                        <div
                            style="width:32px;height:32px;border-radius:8px;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#15803d;">Pay Now</div>
                            <div style="font-size:11.5px;color:#6B7A8D;margin-top:1px;">Open the Payment form to record the
                                full balance</div>
                        </div>
                    </div>
                </button>

                <!-- Option B: Defer -->
                <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px 16px;">
                    <div class="d-flex-gap-10" style="margin-bottom:10px;">
                        <div
                            style="width:32px;height:32px;border-radius:8px;background:#d97706;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#92400e;">Defer (Deferred)</div>
                            <div style="font-size:11.5px;color:#6B7A8D;margin-top:1px;">To be paid before check-out</div>
                        </div>
                    </div>
                    <label
                        style="display:flex;align-items:flex-start;gap:8px;font-size: 14px;color:#374151;cursor:pointer;margin-bottom:10px;">
                        <input type="checkbox" id="ccDeferCheckbox" style="margin-top:2px;">
                        <span>I confirm that I will allow this guest to check in now and that the outstanding balance will
                            be settled before check-out. This is recorded in the staff log.</span>
                    </label>
                    <button type="button" id="ccConfirmBtn" onclick="ccConfirmDeferred()" disabled
                        style="width:100%;background:#d97706;color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;opacity:.5;">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Confirm and Check In
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Live clock
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent =
                now.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Tab switching
        function switchTab(name, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
        }

        // Payment Modal Functions
        const paymentFormUrlTemplate = '{{ route('staff.payment', ['booking' => '__BOOKING_ID__']) }}';

        function openPaymentModal(bookingId, bookingRef, balanceDue) {
            document.getElementById('modalBookingRef').textContent = bookingRef;
            document.getElementById('modalBalance').textContent = '₱' + parseFloat(balanceDue).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('modalAmount').value = balanceDue > 0 ? balanceDue : '';
            document.getElementById('paymentForm').action = paymentFormUrlTemplate.replace('__BOOKING_ID__', bookingId);
            document.getElementById('paymentModal').style.display = 'flex';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // Close modal when clicking on backdrop
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) closePaymentModal();
        });

        // ── Check-in Confirmation (balance handling) ────────────────────
        let pendingCheckinBookingId = null;

        function handleCheckInSubmit(event, bookingId, guestName, balanceDue) {
            if (balanceDue <= 0) {
                // Walang balance — direktang tuloy, katulad ng dati.
                return confirm('Check in ' + guestName + '?');
            }
            // May natitirang balance — hadlangan muna ang submit at patukuyin
            // si staff kung babayaran ngayon o ipagpapaliban (confirmed).
            event.preventDefault();
            openCheckinConfirmModal(bookingId, guestName, balanceDue);
            return false;
        }

        function openCheckinConfirmModal(bookingId, guestName, balanceDue) {
            pendingCheckinBookingId = bookingId;
            document.getElementById('ccGuestName').textContent = guestName;
            document.getElementById('ccBalance').textContent = '₱' + parseFloat(balanceDue).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('ccDeferCheckbox').checked = false;
            const btn = document.getElementById('ccConfirmBtn');
            btn.disabled = true;
            btn.style.opacity = '.5';
            document.getElementById('checkinConfirmModal').style.display = 'flex';
        }

        function closeCheckinConfirmModal() {
            document.getElementById('checkinConfirmModal').style.display = 'none';
            pendingCheckinBookingId = null;
        }

        document.getElementById('checkinConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeCheckinConfirmModal();
        });

        document.getElementById('ccDeferCheckbox').addEventListener('change', function() {
            const btn = document.getElementById('ccConfirmBtn');
            btn.disabled = !this.checked;
            btn.style.opacity = this.checked ? '1' : '.5';
        });

        function ccPayNow() {
            // Isara ang confirmation modal, buksan ang Payment modal na naka-prefill
            // sa buong balance. Pagkatapos ma-record ang bayad (₱0 na ang balance),
            // pindutin lang ulit ang "Check In" — awtomatiko na itong magpapatuloy
            // nang walang modal, dahil wala nang balance na natitira.
            const bookingId = pendingCheckinBookingId;
            const guestName = document.getElementById('ccGuestName').textContent;
            const balanceTxt = document.getElementById('ccBalance').textContent.replace(/[₱,]/g, '');
            closeCheckinConfirmModal();
            openPaymentModal(bookingId, guestName, parseFloat(balanceTxt) || 0);
        }

        function ccConfirmDeferred() {
            if (!pendingCheckinBookingId) return;
            const form = document.getElementById('checkinForm_' + pendingCheckinBookingId);
            if (!form) return;

            // Idagdag ang hidden input na nagsasabing "deferred" ang balance
            // arrangement — kailangan ito ng backend (FrontDeskController::checkIn)
            // bago payagan ang check-in kapag may natitirang balance.
            let hidden = form.querySelector('input[name="balance_arrangement"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'balance_arrangement';
                form.appendChild(hidden);
            }
            hidden.value = 'deferred';

            closeCheckinConfirmModal();
            form.submit();
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
        }, 5000);
    </script>
@endpush

