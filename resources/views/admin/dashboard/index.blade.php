<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard — Villa Elena Resort</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
            --navy-light: #243b55;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dim:   rgba(201,168,76,0.15);
            --teal:       #0e9f9f;
            --teal-dim:   rgba(14,159,159,0.12);
            --white:      #ffffff;
            --off-white:  #f4f6f9;
            --text-main:  #1a2f45;
            --text-muted: #6b7a8d;
            --border:     #e2e8f0;
            --sidebar-w:  260px;
            --topbar-h:   68px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--off-white);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* ── SIDEBAR ──────────────────────────────────────── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
            transition: transform .3s ease;
        }

        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .sidebar-brand h1 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--gold-light);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .sidebar-brand p {
            color: rgba(255,255,255,0.35);
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .sidebar-section {
            padding: 20px 16px 8px;
        }

        .sidebar-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .nav-item-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all .2s;
            margin-bottom: 2px;
        }

        .nav-item-custom:hover {
            background: rgba(255,255,255,0.07);
            color: var(--white);
        }

        .nav-item-custom.active {
            background: var(--gold-dim);
            color: var(--gold-light);
            font-weight: 500;
        }

        .nav-item-custom .nav-icon {
            width: 32px;
            height: 32px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            background: rgba(255,255,255,0.05);
        }

        .nav-item-custom.active .nav-icon {
            background: var(--gold-dim);
            color: var(--gold);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gold-dim);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .user-info .name {
            color: var(--white);
            font-size: 13px;
            font-weight: 500;
        }

        .user-info .role-badge {
            font-size: 10px;
            color: var(--gold);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ── TOPBAR ───────────────────────────────────────── */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 900;
        }

        .topbar-left h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--text-main);
        }

        .topbar-left p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-btn {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s;
            position: relative;
            font-size: 16px;
        }

        .topbar-btn:hover {
            background: var(--off-white);
            color: var(--text-main);
        }

        .badge-dot {
            position: absolute;
            top: 7px; right: 7px;
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #ef4444;
            border: 1.5px solid white;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            border-radius: 9px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        /* ── MAIN CONTENT ─────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 32px;
            min-height: calc(100vh - var(--topbar-h));
        }

        /* ── KPI CARDS ────────────────────────────────────── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--white);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }

        .kpi-card.gold::before   { background: linear-gradient(90deg, var(--gold), var(--gold-light)); }
        .kpi-card.teal::before   { background: linear-gradient(90deg, var(--teal), #5ee7e7); }
        .kpi-card.navy::before   { background: linear-gradient(90deg, var(--navy-mid), #3a6186); }
        .kpi-card.green::before  { background: linear-gradient(90deg, #16a34a, #4ade80); }
        .kpi-card.orange::before { background: linear-gradient(90deg, #ea580c, #fb923c); }
        .kpi-card.purple::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
        .kpi-card.rose::before   { background: linear-gradient(90deg, #e11d48, #fb7185); }
        .kpi-card.sky::before    { background: linear-gradient(90deg, #0284c7, #38bdf8); }

        .kpi-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }

        .kpi-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
            margin-bottom: 8px;
        }

        .kpi-sub {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .kpi-sub .up   { color: #16a34a; }
        .kpi-sub .down { color: #ef4444; }

        .kpi-icon {
            position: absolute;
            bottom: 16px; right: 20px;
            font-size: 38px;
            opacity: 0.06;
            color: var(--text-main);
        }

        /* ── CHARTS ROW ───────────────────────────────────── */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .card-panel {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-panel-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-panel-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-main);
        }

        .card-panel-header p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .card-panel-body { padding: 20px 24px; }

        .badge-pill {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* ── BOTTOM ROW ───────────────────────────────────── */
        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* ── TABLE ────────────────────────────────────────── */
        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .custom-table th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            padding: 0 12px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        .custom-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
            vertical-align: middle;
        }

        .custom-table tr:last-child td { border-bottom: none; }
        .custom-table tr:hover td { background: #fafbfc; }

        /* ── STATUS BADGES ────────────────────────────────── */
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending    { background: #fef9c3; color: #a16207; }
        .status-confirmed  { background: #dcfce7; color: #15803d; }
        .status-checked_in { background: #dbeafe; color: #1d4ed8; }
        .status-cancelled  { background: #fee2e2; color: #dc2626; }
        .status-checked_out{ background: #f1f5f9; color: #475569; }

        .pay-paid     { background: #dcfce7; color: #15803d; }
        .pay-partial  { background: #fef9c3; color: #a16207; }
        .pay-unpaid   { background: #fee2e2; color: #dc2626; }
        .pay-refunded { background: #e0f2fe; color: #0369a1; }

        /* ── QUICK ACTIONS ────────────────────────────────── */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text-main);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all .2s;
        }

        .quick-action-btn:hover {
            background: var(--navy);
            color: var(--white);
            border-color: var(--navy);
        }

        .quick-action-btn .qa-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        /* ── PROPERTY STATUS LIST ─────────────────────────── */
        .property-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .property-item:last-child { border-bottom: none; }

        .prop-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-available   { background: #22c55e; }
        .dot-occupied    { background: #3b82f6; }
        .dot-maintenance { background: #f59e0b; }

        /* ── RESPONSIVE ───────────────────────────────────── */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .topbar, .main-content { left: 0; margin-left: 0; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .charts-row, .bottom-row { grid-template-columns: 1fr; }
        }

        /* ── ANIMATIONS ───────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .kpi-card { animation: fadeUp .4s ease both; }
        .kpi-card:nth-child(1) { animation-delay: .05s; }
        .kpi-card:nth-child(2) { animation-delay: .10s; }
        .kpi-card:nth-child(3) { animation-delay: .15s; }
        .kpi-card:nth-child(4) { animation-delay: .20s; }
        .kpi-card:nth-child(5) { animation-delay: .25s; }
        .kpi-card:nth-child(6) { animation-delay: .30s; }
        .kpi-card:nth-child(7) { animation-delay: .35s; }
        .kpi-card:nth-child(8) { animation-delay: .40s; }

        .charts-row, .bottom-row {
            animation: fadeUp .5s ease .3s both;
        }

        .nav-item-custom.active {
    background: var(--gold-dim);
    color: var(--gold-light);
    font-weight: 500;
    border-left: 3px solid var(--gold);
    padding-left: 9px; /* para mag-adjust dahil sa border */
}
    </style>
</head>
<body>

@include('admin.partials.sidebar')

{{-- ── TOPBAR ──────────────────────────────────────────────── --}}
<header class="topbar">
    <div class="topbar-left">
        <h2>Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 18 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->full_name)[0] }} 👋</h2>
        <p>{{ now()->format('l, F j, Y') }} &nbsp;·&nbsp; Here's what's happening at the resort today</p>
    </div>
    <div class="topbar-right">
<!-- REPLACE your bell topbar-btn with this: -->
<div style="position:relative;" id="notifWrapper">
    <div class="topbar-btn" id="notifBtn" onclick="toggleNotif()" style="cursor:pointer;">
        <i class="bi bi-bell"></i>
        @if(isset($unreadCount) && $unreadCount > 0)
            <span class="badge-dot" id="notifDot"></span>
        @endif
    </div>
    <!-- Dropdown inline here -->
    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
            <div class="notif-header-title">Notifications</div>
            <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
        </div>
        <div class="notif-list">
            @isset($notifications)
                @forelse($notifications as $notif)
                @php
                    $iconMap = [
                        'booking_update'=>['icon'=>'bi-calendar-check','bg'=>'#dcfce7','color'=>'#16a34a'],
                        'payment'       =>['icon'=>'bi-credit-card',   'bg'=>'#dbeafe','color'=>'#1d4ed8'],
                        'cancellation'  =>['icon'=>'bi-x-circle',      'bg'=>'#fee2e2','color'=>'#dc2626'],
                        'in_app'        =>['icon'=>'bi-info-circle',   'bg'=>'#f1f5f9','color'=>'#475569'],
                    ];
                    $ic = $iconMap[$notif->type] ?? $iconMap['in_app'];
                @endphp
                <div class="notif-item {{ !$notif->is_read ? 'unread' : '' }}">
                    <div class="notif-icon-wrap" style="background:{{ $ic['bg'] }};color:{{ $ic['color'] }};">
                        <i class="bi {{ $ic['icon'] }}"></i>
                    </div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">{{ $notif->title }}</div>
                        <div class="notif-item-msg">{{ $notif->message }}</div>
                        <div class="notif-item-time">{{ $notif->created_at->diffForHumans() }}</div>
                    </div>
                    @if(!$notif->is_read)
                        <div class="notif-unread-dot"></div>
                    @endif
                </div>
                @empty
                <div class="notif-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
                @endforelse
            @else
                <div class="notif-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            @endisset
        </div>
        <div class="notif-footer">
            <a href="#">View all notifications →</a>
        </div>
    </div>
</div>
<div class="topbar-btn" onclick="openSearch()" style="cursor:pointer;">
    <i class="bi bi-search"></i>
</div>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </form>
    </div>
</header>

{{-- ── MAIN CONTENT ────────────────────────────────────────── --}}
<main class="main-content">

    {{-- KPI Cards --}}
    <div class="kpi-grid">

        <div class="kpi-card gold" id="rt-revenue-card">
            <div class="kpi-label">Revenue Today</div>
            <div class="kpi-value" id="rt-today-revenue" data-value="{{ $stats['revenue_today'] }}">₱{{ number_format($stats['revenue_today'], 0) }}</div>
            <div class="kpi-sub"><i class="bi bi-arrow-up up"></i> <span class="up">Live</span></div>
            <i class="bi bi-cash-coin kpi-icon"></i>
        </div>

        <div class="kpi-card teal">
            <div class="kpi-label">Revenue This Month</div>
            <div class="kpi-value">₱{{ number_format($stats['revenue_this_month'], 0) }}</div>
            <div class="kpi-sub"><i class="bi bi-calendar3"></i> {{ now()->format('F Y') }}</div>
            <i class="bi bi-graph-up kpi-icon"></i>
        </div>

        <div class="kpi-card navy" id="rt-bookings-card">
            <div class="kpi-label">Total Bookings</div>
        <div class="kpi-value" id="rt-total-bookings">{{ number_format($stats['total_bookings']) }}</div>
            <div class="kpi-sub"><i class="bi bi-calendar-check"></i> All time</div>
            <i class="bi bi-calendar2-week kpi-icon"></i>
        </div>

        <div class="kpi-card orange">
            <div class="kpi-label">Pending Bookings</div>
           <div class="kpi-value" id="rt-pending-count">{{ $stats['pending_bookings'] }}</div>
            <div class="kpi-sub"><i class="bi bi-clock"></i> Awaiting confirmation</div>
            <i class="bi bi-hourglass-split kpi-icon"></i>
        </div>

        <div class="kpi-card green">
            <div class="kpi-label">Today's Check-ins</div>
            <div class="kpi-value">{{ $stats['todays_checkins'] }}</div>
            <div class="kpi-sub"><i class="bi bi-box-arrow-in-right"></i> Expected today</div>
            <i class="bi bi-door-open kpi-icon"></i>
        </div>

        <div class="kpi-card rose">
            <div class="kpi-label">Today's Check-outs</div>
            <div class="kpi-value">{{ $stats['todays_checkouts'] }}</div>
            <div class="kpi-sub"><i class="bi bi-box-arrow-right"></i> Departing today</div>
            <i class="bi bi-door-closed kpi-icon"></i>
        </div>

        <div class="kpi-card sky">
            <div class="kpi-label">Total Guests</div>
            <div class="kpi-value">{{ number_format($stats['total_guests']) }}</div>
            <div class="kpi-sub"><i class="bi bi-people"></i> Registered accounts</div>
            <i class="bi bi-person-hearts kpi-icon"></i>
        </div>

        <div class="kpi-card purple">
            <div class="kpi-label">Available Rooms</div>
            <div class="kpi-value">{{ $stats['available_rooms'] }}</div>
            <div class="kpi-sub"><i class="bi bi-house-check"></i> of {{ $stats['total_properties'] }} properties</div>
            <i class="bi bi-houses kpi-icon"></i>
        </div>

    </div>

    {{-- Charts Row --}}
    <div class="charts-row">

        {{-- Revenue Chart --}}
        <div class="card-panel">
            <div class="card-panel-header">
                <div>
                    <h3>Revenue Overview</h3>
                    <p>Monthly revenue for the past 6 months</p>
                </div>
                <span class="badge-pill" style="background:#dcfce7; color:#15803d;">
                    <i class="bi bi-circle-fill" style="font-size:7px"></i> Live
                </span>
            </div>
            <div class="card-panel-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>

        {{-- Booking Sources Pie --}}
        <div class="card-panel">
            <div class="card-panel-header">
                <div>
                    <h3>Booking Sources</h3>
                    <p>Where bookings come from</p>
                </div>
            </div>
            <div class="card-panel-body">
                <canvas id="sourceChart" height="180"></canvas>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; justify-content:center;">
                    <span class="badge-pill" style="background:#e0f2fe; color:#0369a1;">🌐 Online</span>
                    <span class="badge-pill" style="background:#dcfce7; color:#15803d;">🚶 Walk-in</span>
                    <span class="badge-pill" style="background:#fef9c3; color:#a16207;">📞 Phone</span>
                    <span class="badge-pill" style="background:#f3e8ff; color:#7c3aed;">🤝 Partner</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Bottom Row --}}
    <div class="bottom-row">

        {{-- Recent Bookings --}}
        <div class="card-panel">
            <div class="card-panel-header">
                <div>
                    <h3>Recent Bookings</h3>
                    <p>Latest 5 reservations</p>
                </div>
                <a href="{{ route('admin.bookings.index') }}" style="font-size:12px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-panel-body" style="padding: 0 24px;">
                @php
                    $recentBookings = \App\Models\Booking::with(['user','property'])
                        ->latest()->take(5)->get();
                @endphp

                @if($recentBookings->isEmpty())
                    <div style="text-align:center; padding:40px 0; color:#94a3b8;">
                        <i class="bi bi-calendar-x" style="font-size:36px; display:block; margin-bottom:8px;"></i>
                        No bookings yet
                    </div>
                @else
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Property</th>
                                <th>Check-in</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentBookings as $booking)
                            <tr>
                                <td>
                                    <div style="font-weight:500;">{{ $booking->user->full_name ?? 'N/A' }}</div>
                                    <div style="font-size:11px; color:#94a3b8;">{{ $booking->booking_ref }}</div>
                                </td>
                                <td style="font-size:13px;">{{ $booking->property->property_name ?? 'N/A' }}</td>
                                <td style="font-size:12px; color:#64748b;">{{ $booking->check_in_date->format('M d, Y') }}</td>
                                <td>
                                    <span class="status-badge status-{{ $booking->status }}">
                                        {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Right Column: Property Status + Quick Actions --}}
        <div style="display:flex; flex-direction:column; gap:20px;">

            {{-- Property Status --}}
            <div class="card-panel">
                <div class="card-panel-header">
                    <div>
                        <h3>Property Status</h3>
                        <p>Current room availability</p>
                    </div>
                    <a href="{{ route('admin.properties.index') }}" style="font-size:12px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                        Manage <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-panel-body">
                    @php
                        $properties = \App\Models\Property::take(5)->get();
                    @endphp

                    @if($properties->isEmpty())
                        <div style="text-align:center; padding:20px 0; color:#94a3b8; font-size:13px;">
                            <i class="bi bi-house-x" style="font-size:28px; display:block; margin-bottom:8px;"></i>
                            No properties yet
                        </div>
                    @else
                        @foreach($properties as $property)
                        <div class="property-item">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="prop-dot dot-{{ $property->status }}"></div>
                                <div>
                                    <div style="font-size:13px; font-weight:500;">{{ $property->property_name }}</div>
                                    <div style="font-size:11px; color:#94a3b8;">{{ ucfirst($property->type) }} · {{ $property->max_capacity }} guests</div>
                                </div>
                            </div>
                            <span class="status-badge status-{{ $property->status }}">
                                {{ ucfirst($property->status) }}
                            </span>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card-panel">
                <div class="card-panel-header">
                    <div><h3>Quick Actions</h3></div>
                </div>
                <div class="card-panel-body">
                    <div class="quick-actions">
                        <a href="{{ route('admin.bookings.create') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#dbeafe; color:#1d4ed8;">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            New Booking
                        </a>
                        <a href="{{ route('admin.properties.create') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#dcfce7; color:#15803d;">
                                <i class="bi bi-house-add"></i>
                            </div>
                            Add Property
                        </a>
                        <a href="{{ route('admin.reports.index') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#fef9c3; color:#a16207;">
                                <i class="bi bi-file-earmark-bar-graph"></i>
                            </div>
                            Generate Report
                        </a>
                        <a href="{{ route('admin.reviews.index') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#f3e8ff; color:#7c3aed;">
                                <i class="bi bi-star-half"></i>
                            </div>
                            Moderate Reviews
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Revenue Chart ────────────────────────────────────────────
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
        datasets: [{
            label: 'Revenue (₱)',
            data: [0, 0, 0, 0, 0, 0],
            backgroundColor: 'rgba(201,168,76,0.15)',
            borderColor: '#c9a84c',
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ₱' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: {
                    callback: v => '₱' + v.toLocaleString(),
                    font: { size: 11 }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 12 } }
            }
        }
    }
});

// ── Booking Sources Chart ────────────────────────────────────
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
new Chart(sourceCtx, {
    type: 'doughnut',
    data: {
        labels: ['Online', 'Walk-in', 'Phone', 'Partner'],
        datasets: [{
            data: [60, 25, 10, 5],
            backgroundColor: ['#0284c7','#16a34a','#ca8a04','#7c3aed'],
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        cutout: '70%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + '%'
                }
            }
        }
    }
});
</script>
@include('admin.partials.realtime') 
@include('admin.partials.topbar_features')
</body>
</html>