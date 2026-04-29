<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontdesk — Villa Elena Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --navy:#0D1B2A; --navy-mid:#1A2F45; --gold:#C9A84C; --gold-light:#E8C97A;
            --bg:#F4F6F9; --white:#fff; --border:#E2E8F0; --muted:#6B7A8D;
            --sidebar:260px; --topbar:68px;
        }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:#1e293b; }

        /* ── Sidebar ── */
        .sidebar {
            position:fixed; top:0; left:0; width:var(--sidebar); height:100vh;
            background:var(--navy); z-index:100; display:flex; flex-direction:column;
            padding:0; overflow:hidden;
        }
        .sidebar-brand {
            padding:22px 24px 18px; border-bottom:1px solid rgba(255,255,255,.07);
            text-decoration:none; display:block;
        }
        .brand-name { font-family:'Playfair Display',serif; color:var(--gold-light); font-size:18px; }
        .brand-role { color:rgba(255,255,255,.3); font-size:11px; letter-spacing:1px; text-transform:uppercase; margin-top:2px; }
        .sidebar-nav { flex:1; padding:16px 12px; overflow-y:auto; }
        .nav-label { font-size:10px; color:rgba(255,255,255,.25); text-transform:uppercase;
            letter-spacing:1.5px; padding:10px 12px 6px; font-weight:600; }
        .nav-item { display:flex; align-items:center; gap:11px; padding:10px 14px; border-radius:9px;
            color:rgba(255,255,255,.55); text-decoration:none; font-size:13.5px; font-weight:500;
            margin-bottom:2px; transition:all .2s; }
        .nav-item:hover { background:rgba(255,255,255,.06); color:#fff; }
        .nav-item.active { background:rgba(201,168,76,.15); color:var(--gold-light); }
        .nav-item i { font-size:16px; width:20px; text-align:center; }
        .nav-badge { margin-left:auto; background:rgba(201,168,76,.25); color:var(--gold-light);
            padding:1px 8px; border-radius:100px; font-size:10px; font-weight:700; }
        .nav-badge.red { background:rgba(239,68,68,.2); color:#fca5a5; }
        .sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,.07); }
        .staff-info { display:flex; align-items:center; gap:10px; }
        .staff-avatar { width:34px; height:34px; border-radius:50%; background:var(--gold);
            color:var(--navy); display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:13px; }
        .staff-name { color:rgba(255,255,255,.7); font-size:13px; }
        .staff-tag  { color:rgba(255,255,255,.3); font-size:11px; }
        .logout-btn { margin-left:auto; color:rgba(255,255,255,.3); font-size:18px;
            text-decoration:none; transition:color .2s; }
        .logout-btn:hover { color:#fff; }

        /* ── Topbar ── */
        .topbar {
            position:fixed; top:0; left:var(--sidebar); right:0; height:var(--topbar);
            background:#fff; border-bottom:1px solid var(--border); z-index:99;
            display:flex; align-items:center; justify-content:space-between; padding:0 28px;
        }
        .topbar-title { font-family:'Playfair Display',serif; font-size:20px; color:var(--navy); font-weight:700; }
        .topbar-date { font-size:13px; color:var(--muted); margin-top:2px; }
        .topbar-right { display:flex; align-items:center; gap:12px; }
        .live-clock { font-size:20px; font-weight:700; color:var(--navy); font-family:'Playfair Display',serif; }
        .live-date  { font-size:11px; color:var(--muted); text-align:right; }

        /* ── Main ── */
        .main { margin-left:var(--sidebar); margin-top:var(--topbar); padding:28px; }

        /* ── Alert ── */
        .alert { border-radius:10px; font-size:13px; padding:12px 16px; border:none; margin-bottom:20px; }
        .alert-success { background:#dcfce7; color:#15803d; }
        .alert-danger  { background:#fee2e2; color:#dc2626; }

        /* ── Stats ── */
        .stats-row { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:24px; }
        .stat-card { background:#fff; border-radius:12px; border:1px solid var(--border); padding:16px 18px; }
        .stat-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center;
            justify-content:center; font-size:16px; margin-bottom:10px; }
        .stat-val { font-size:26px; font-weight:700; font-family:'Playfair Display',serif; color:var(--navy); line-height:1; }
        .stat-lbl { font-size:11px; color:var(--muted); margin-top:3px; }

        /* ── Tabs ── */
        .tab-bar { display:flex; gap:4px; margin-bottom:20px; background:#fff;
            border:1px solid var(--border); border-radius:12px; padding:5px; width:fit-content; }
        .tab-btn { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:500;
            border:none; background:none; cursor:pointer; color:var(--muted);
            font-family:'DM Sans',sans-serif; display:flex; align-items:center; gap:7px; transition:all .2s; }
        .tab-btn.active { background:var(--navy); color:#fff; }
        .tab-btn .cnt { background:rgba(255,255,255,.2); padding:0 6px; border-radius:10px; font-size:11px; }
        .tab-btn:not(.active) .cnt { background:#f1f5f9; color:var(--muted); }
        .tab-content { display:none; }
        .tab-content.active { display:block; }

        /* ── Cards ── */
        .card { background:#fff; border-radius:14px; border:1px solid var(--border); overflow:hidden; }
        .card-head { padding:16px 20px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; }
        .card-head h3 { font-family:'Playfair Display',serif; font-size:16px; font-weight:600; }
        .card-body { padding:0; }

        /* ── Booking Row ── */
        .booking-row { display:flex; align-items:center; gap:14px; padding:14px 20px;
            border-bottom:1px solid #f8fafc; transition:background .15s; }
        .booking-row:last-child { border-bottom:none; }
        .booking-row:hover { background:#fafbfc; }
        .guest-avatar { width:40px; height:40px; border-radius:10px; background:var(--bg);
            display:flex; align-items:center; justify-content:center; font-size:15px;
            font-weight:700; color:var(--navy); flex-shrink:0; }
        .booking-info { flex:1; min-width:0; }
        .booking-name { font-weight:600; font-size:14px; }
        .booking-ref  { font-size:12px; color:var(--muted); }
        .booking-prop { font-size:12px; color:var(--muted); margin-top:2px; }
        .booking-meta { display:flex; gap:10px; font-size:11px; color:var(--muted); margin-top:3px; }
        .booking-action { flex-shrink:0; }

        /* Buttons */
        .btn-checkin { background:#16a34a; color:#fff; border:none; border-radius:8px;
            padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer;
            font-family:'DM Sans',sans-serif; display:flex; align-items:center; gap:6px; transition:all .2s; }
        .btn-checkin:hover { background:#15803d; }
        .btn-checkout { background:var(--navy); color:#fff; border:none; border-radius:8px;
            padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer;
            font-family:'DM Sans',sans-serif; display:flex; align-items:center; gap:6px; transition:all .2s; }
        .btn-checkout:hover { background:var(--navy-mid); }
        .btn-sm { padding:6px 12px; font-size:11px; border-radius:7px; border:none; cursor:pointer;
            font-family:'DM Sans',sans-serif; font-weight:600; display:inline-flex; align-items:center; gap:5px; }
        .btn-start    { background:#dbeafe; color:#1d4ed8; }
        .btn-complete { background:#dcfce7; color:#15803d; }
        .btn-start:hover    { background:#bfdbfe; }
        .btn-complete:hover { background:#bbf7d0; }

        /* Badges */
        .badge { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
            text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
        .b-pending    { background:#fef9c3; color:#a16207; }
        .b-confirmed  { background:#dcfce7; color:#15803d; }
        .b-checked_in { background:#dbeafe; color:#1d4ed8; }
        .b-checked_out{ background:#f1f5f9; color:#475569; }

        /* Property grid */
        .property-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; padding:16px; }
        .prop-card { border-radius:10px; border:1px solid var(--border); padding:14px; }
        .prop-card.available { border-color:#86efac; background:#f0fdf4; }
        .prop-card.occupied  { border-color:#fca5a5; background:#fef2f2; }
        .prop-card.maintenance { border-color:#fcd34d; background:#fffbeb; }
        .prop-card-name { font-weight:600; font-size:13px; margin-bottom:4px; }
        .prop-card-type { font-size:11px; color:var(--muted); margin-bottom:8px; }
        .prop-status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:5px; }
        .dot-available  { background:#16a34a; }
        .dot-occupied   { background:#dc2626; }
        .dot-maintenance{ background:#d97706; }
        .prop-guest { font-size:11px; color:#374151; margin-top:6px; }

        /* Housekeeping */
        .task-row { display:flex; align-items:center; gap:12px; padding:12px 20px;
            border-bottom:1px solid #f8fafc; }
        .task-row:last-child { border-bottom:none; }
        .task-type-icon { width:34px; height:34px; border-radius:9px; display:flex;
            align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
        .task-info { flex:1; }
        .task-prop { font-weight:600; font-size:13px; }
        .task-details { font-size:12px; color:var(--muted); margin-top:1px; }
        .task-date { font-size:11px; margin-top:3px; }
        .task-date.overdue { color:#dc2626; font-weight:600; }
        .task-date.today   { color:#d97706; font-weight:600; }

        /* Empty state */
        .empty { text-align:center; padding:40px; color:var(--muted); }
        .empty i { font-size:36px; display:block; margin-bottom:10px; opacity:.35; }
        .empty p { font-size:13px; }

        @media(max-width:900px) {
            .stats-row { grid-template-columns:repeat(3,1fr); }
        }
    </style>
</head>
<body>

{{-- Sidebar --}}
<aside class="sidebar">
    <a href="{{ route('staff.frontdesk') }}" class="sidebar-brand">
        <div class="brand-name">Villa Elena</div>
        <div class="brand-role">Staff Portal</div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-label">Operations</div>
        <a href="{{ route('staff.frontdesk') }}" class="nav-item active">
            <i class="bi bi-house-door"></i> Frontdesk
            @if($stats['check_ins_today'] + $stats['check_outs_today'] > 0)
            <span class="nav-badge">{{ $stats['check_ins_today'] + $stats['check_outs_today'] }}</span>
            @endif
        </a>
        
        {{-- Walk-in Booking Link --}}
        <a href="{{ route('staff.walkin') }}" class="nav-item">
            <i class="bi bi-person-plus"></i> Walk-in Booking
        </a>

        <div class="nav-label" style="margin-top:8px;">Quick Links</div>
    </nav>
    <div class="sidebar-footer">
        <div class="staff-info">
            <div class="staff-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
            <div>
                <div class="staff-name">{{ auth()->user()->full_name }}</div>
                <div class="staff-tag">Staff</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;margin-left:auto;">
                @csrf
                <button type="submit" class="logout-btn" title="Sign out">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Topbar --}}
<div class="topbar">
    <div>
        <div class="topbar-title">Frontdesk Operations</div>
        <div class="topbar-date">{{ today()->format('l, F j, Y') }}</div>
    </div>
    <div class="topbar-right">
        <div style="text-align:right;">
            <div class="live-clock" id="liveClock"></div>
            <div class="live-date">Philippine Standard Time</div>
        </div>
    </div>
</div>

<main class="main">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="stat-val">{{ $stats['check_ins_today'] }}</div>
            <div class="stat-lbl">Check-ins Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-box-arrow-right"></i></div>
            <div class="stat-val">{{ $stats['check_outs_today'] }}</div>
            <div class="stat-lbl">Check-outs Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-people-fill"></i></div>
            <div class="stat-val">{{ $stats['occupied'] }}</div>
            <div class="stat-lbl">Occupied</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-house-check"></i></div>
            <div class="stat-val">{{ $stats['available'] }}</div>
            <div class="stat-lbl">Available</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">{{ $stats['pending_bookings'] }}</div>
            <div class="stat-lbl">Pending Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-brush"></i></div>
            <div class="stat-val">{{ $stats['pending_tasks'] }}</div>
            <div class="stat-lbl">Housekeeping</div>
        </div>
    </div>

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
        <button class="tab-btn" onclick="switchTab('properties', this)">
            <i class="bi bi-buildings"></i> Properties
        </button>
    </div>

    {{-- Tab: Check-ins --}}
    <div class="tab-content active" id="tab-checkins">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-box-arrow-in-right me-2" style="color:#16a34a;"></i>Expected Check-ins Today</h3>
                <span style="font-size:12px;color:var(--muted);">{{ today()->format('M d, Y') }}</span>
            </div>
            <div class="card-body">
                @forelse($checkIns as $booking)
                <div class="booking-row">
                    <div class="guest-avatar">{{ strtoupper(substr($booking->user->full_name, 0, 1)) }}</div>
                    <div class="booking-info">
                        <div class="booking-name">{{ $booking->user->full_name }}</div>
                        <div class="booking-ref">{{ $booking->booking_ref }}</div>
                        <div class="booking-prop"><i class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                        <div class="booking-meta">
                            <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                            <span><i class="bi bi-moon"></i> {{ $booking->num_nights }} nights</span>
                            <span><i class="bi bi-cash"></i> ₱{{ number_format($booking->balance_due,0) }} balance</span>
                        </div>
                    </div>
                    <div class="booking-action" style="display:flex; gap:8px; flex-direction:column; align-items:flex-end;">
                        <form method="POST" action="{{ route('staff.checkin', $booking) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-checkin"
                                onclick="return confirm('Check in {{ $booking->user->full_name }}?')">
                                <i class="bi bi-box-arrow-in-right"></i> Check In
                            </button>
                        </form>
                        <button type="button" class="btn-sm"
                            style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size:11px;font-weight:600;cursor:pointer;"
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
                        <div class="booking-prop"><i class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                        <div class="booking-meta">
                            <span><i class="bi bi-calendar3"></i> Checked in {{ $booking->check_in_date->format('M d') }}</span>
                            <span><i class="bi bi-cash"></i>
                                @if($booking->balance_due > 0)
                                    <span style="color:#dc2626;">₱{{ number_format($booking->balance_due,0) }} unpaid</span>
                                @else
                                    <span style="color:#16a34a;">Fully paid</span>
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="booking-action" style="display:flex; gap:8px; flex-direction:column; align-items:flex-end;">
                        <form method="POST" action="{{ route('staff.checkout', $booking) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-checkout"
                                onclick="return confirm('Check out {{ $booking->user->full_name }}?')">
                                <i class="bi bi-box-arrow-right"></i> Check Out
                            </button>
                        </form>
                        <button type="button" class="btn-sm"
                            style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size:11px;font-weight:600;cursor:pointer;"
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
                <span style="font-size:12px;color:var(--muted);">{{ $currentGuests->count() }} guest{{ $currentGuests->count()!=1?'s':'' }}</span>
            </div>
            <div class="card-body">
                @forelse($currentGuests as $booking)
                <div class="booking-row">
                    <div class="guest-avatar" style="background:#ede9fe;color:#7c3aed;">
                        {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                    </div>
                    <div class="booking-info">
                        <div class="booking-name">{{ $booking->user->full_name }}</div>
                        <div class="booking-prop"><i class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                        <div class="booking-meta">
                            <span><i class="bi bi-calendar3"></i> In: {{ $booking->check_in_date->format('M d') }}</span>
                            <span><i class="bi bi-calendar3"></i> Out: {{ $booking->check_out_date->format('M d, Y') }}</span>
                            @php $daysLeft = today()->diffInDays($booking->check_out_date, false); @endphp
                            @if($daysLeft <= 0)
                                <span style="color:#dc2626;font-weight:600;"><i class="bi bi-exclamation-triangle"></i> Overdue</span>
                            @elseif($daysLeft == 1)
                                <span style="color:#d97706;font-weight:600;"><i class="bi bi-clock"></i> Checking out tomorrow</span>
                            @else
                                <span><i class="bi bi-moon"></i> {{ $daysLeft }} nights left</span>
                            @endif
                        </div>
                    </div>
                    <div class="booking-action" style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <span class="badge b-checked_in">Checked In</span>
                        <button type="button" class="btn-sm"
                            style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size:11px;font-weight:600;cursor:pointer;"
                            onclick="openPaymentModal({{ $booking->id }}, '{{ $booking->booking_ref }}', {{ $booking->balance_due ?? 0 }})">
                            <i class="bi bi-cash"></i> Payment
                        </button>
                        @if($booking->check_out_date->isToday())
                        <form method="POST" action="{{ route('staff.checkout', $booking) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-checkout" style="font-size:11px;padding:5px 12px;"
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
                <span style="font-size:12px;color:var(--muted);">Contact admin to confirm</span>
            </div>
            <div class="card-body">
                @forelse($pendingBookings as $booking)
                <div class="booking-row">
                    <div class="guest-avatar" style="background:#fef9c3;color:#a16207;">
                        {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                    </div>
                    <div class="booking-info">
                        <div class="booking-name">{{ $booking->user->full_name }}</div>
                        <div class="booking-ref">{{ $booking->booking_ref }} · {{ $booking->user->email }}</div>
                        <div class="booking-prop"><i class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                        <div class="booking-meta">
                            <span><i class="bi bi-calendar3"></i> {{ $booking->check_in_date->format('M d') }} – {{ $booking->check_out_date->format('M d, Y') }}</span>
                            <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                            <span>₱{{ number_format($booking->total_amount,0) }}</span>
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
        @if($inProgressTasks->count())
        <div class="card" style="margin-bottom:16px;">
            <div class="card-head">
                <h3><i class="bi bi-brush me-2" style="color:#1d4ed8;"></i>In Progress</h3>
            </div>
            <div class="card-body">
                @foreach($inProgressTasks as $task)
                <div class="task-row">
                    <div class="task-type-icon" style="background:#dbeafe;color:#1d4ed8;">
                        <i class="bi bi-brush"></i>
                    </div>
                    <div class="task-info">
                        <div class="task-prop">{{ $task->property->property_name }}</div>
                        <div class="task-details">{{ ucfirst(str_replace('_',' ',$task->task_type)) }}
                            @if($task->notes) · {{ $task->notes }} @endif
                        </div>
                        <div class="task-date {{ $task->scheduled_date?->isToday() ? 'today' : '' }}">
                            Scheduled: {{ $task->scheduled_date?->format('M d, Y') }}
                        </div>
                    </div>
                    <div>
                        <form method="POST" action="{{ route('staff.tasks.complete', $task) }}">
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
                    <div class="task-type-icon" style="background:#fef9c3;color:#a16207;">
                        <i class="bi bi-{{ $task->task_type === 'checkout_clean' ? 'brush' : 'tools' }}"></i>
                    </div>
                    <div class="task-info">
                        <div class="task-prop">{{ $task->property->property_name }}</div>
                        <div class="task-details">{{ ucfirst(str_replace('_',' ',$task->task_type)) }}
                            @if($task->notes) · {{ $task->notes }} @endif
                        </div>
                        @php
                            $isOverdue = $task->scheduled_date && $task->scheduled_date->isPast();
                            $isToday   = $task->scheduled_date && $task->scheduled_date->isToday();
                        @endphp
                        <div class="task-date {{ $isOverdue ? 'overdue' : ($isToday ? 'today' : '') }}">
                            @if($isOverdue) ⚠️ Overdue —
                            @elseif($isToday) 📅 Today —
                            @endif
                            {{ $task->scheduled_date?->format('M d, Y') }}
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <form method="POST" action="{{ route('staff.tasks.start', $task) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-sm btn-start">
                                <i class="bi bi-play-fill"></i> Start
                            </button>
                        </form>
                        <form method="POST" action="{{ route('staff.tasks.complete', $task) }}">
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

    {{-- Tab: Properties --}}
    <div class="tab-content" id="tab-properties">
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-buildings me-2"></i>Property Status Overview</h3>
            </div>
            <div class="property-grid">
                @foreach($properties as $property)
                <div class="prop-card {{ $property->status }}">
                    <div class="prop-card-name">{{ $property->property_name }}</div>
                    <div class="prop-card-type">{{ ucfirst($property->type) }}</div>
                    <div>
                        <span class="prop-status-dot dot-{{ $property->status }}"></span>
                        <span style="font-size:12px;font-weight:600;">
                            {{ ucfirst($property->status) }}
                        </span>
                    </div>
                    @if($property->status === 'occupied' && $property->currentBooking)
                    <div class="prop-guest">
                        <i class="bi bi-person"></i> {{ $property->currentBooking->user->full_name ?? 'Guest' }}<br>
                        <i class="bi bi-calendar3"></i> Out: {{ $property->currentBooking->check_out_date->format('M d') }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

</main>

<!-- Payment Modal -->
<div id="paymentModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;width:420px;max-width:calc(100vw - 32px);overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.2);">
        <!-- Modal Header -->
        <div style="background:#0D1B2A;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-family:'Playfair Display',serif;color:#fff;font-size:17px;font-weight:600;">Record Payment</div>
                <div style="color:rgba(255,255,255,.4);font-size:12px;margin-top:2px;" id="modalBookingRef"></div>
            </div>
            <button onclick="closePaymentModal()" style="background:rgba(255,255,255,.1);border:none;color:#fff;width:30px;height:30px;border-radius:7px;cursor:pointer;font-size:15px;">✕</button>
        </div>
        <!-- Modal Body -->
        <form id="paymentForm" method="POST">
            @csrf
            <div style="padding:22px;">
                <!-- Balance Info -->
                <div style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:13px;color:#6B7A8D;">Balance Due</span>
                    <span style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#dc2626;" id="modalBalance">₱0.00</span>
                </div>
                <!-- Amount -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Amount</label>
                    <input type="number" name="amount" id="modalAmount" required min="1" step="0.01"
                        style="border:1.5px solid #E2E8F0;border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s;"
                        placeholder="Enter amount" onfocus="this.style.borderColor='#0D1B2A'" onblur="this.style.borderColor='#E2E8F0'">
                </div>
                <!-- Method + Type -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Method</label>
                        <select name="payment_method" required style="border:1.5px solid #E2E8F0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;background:#fff;">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Type</label>
                        <select name="payment_type" required style="border:1.5px solid #E2E8F0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;background:#fff;">
                            <option value="deposit">Deposit</option>
                            <option value="balance">Balance Payment</option>
                            <option value="full_payment">Full Payment</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                </div>
                <!-- Notes -->
                <div style="margin-bottom:18px;">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Notes <span style="color:#6B7A8D;font-weight:400;">(optional)</span></label>
                    <input type="text" name="notes"
                        style="border:1.5px solid #E2E8F0;border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;"
                        placeholder="e.g. Cash received at frontdesk">
                </div>
                <!-- Submit -->
                <button type="submit"
                    style="background:#0D1B2A;color:#fff;border:none;border-radius:9px;padding:13px;font-size:14px;font-weight:600;cursor:pointer;width:100%;font-family:'DM Sans',sans-serif;transition:all .2s;"
                    onmouseover="this.style.background='#C9A84C';this.style.color='#0D1B2A'"
                    onmouseout="this.style.background='#0D1B2A';this.style.color='#fff'">
                    <i class="bi bi-check-circle me-2"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live clock
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
function openPaymentModal(bookingId, bookingRef, balanceDue) {
    document.getElementById('modalBookingRef').textContent = bookingRef;
    document.getElementById('modalBalance').textContent = '₱' + parseFloat(balanceDue).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('modalAmount').value = balanceDue > 0 ? balanceDue : '';
    document.getElementById('paymentForm').action = '/villa-elena/public/staff/bookings/' + bookingId + '/payment';
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Close modal when clicking on backdrop
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
}, 5000);
</script>
</body>
</html>