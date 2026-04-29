<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sand:#f5f0e8; --sand-dark:#ede6d6; --stone:#2c2416;
            --olive:#5c5a3c; --cream:#fdfbf7; --terracotta:#c4673a;
            --gold:#b8943f; --gold-light:#d4aa5a; --muted:#8a7f6e;
            --border:#e4ddd0; --white:#ffffff;
            --nav-h:72px;
        }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Jost',sans-serif; background:var(--sand); color:var(--stone); min-height:100vh; }

        /* ── Topnav ── */
        .topnav { position:fixed; top:0; left:0; right:0; height:var(--nav-h); background:var(--stone); z-index:100;
            display:flex; align-items:center; justify-content:space-between; padding:0 40px; }
        .nav-brand { font-family:'Playfair Display',serif; color:var(--gold-light); font-size:20px; letter-spacing:1px; text-decoration:none; }
        .nav-brand span { color:rgba(255,255,255,.35); font-style:italic; font-size:14px; margin-left:8px; }
        .nav-links { display:flex; align-items:center; gap:4px; }
        .nav-link { color:rgba(255,255,255,.55); text-decoration:none; font-size:13px; font-weight:500;
            padding:8px 14px; border-radius:6px; transition:all .2s; letter-spacing:.3px; }
        .nav-link:hover { color:#fff; background:rgba(255,255,255,.08); }
        .nav-link.active { color:var(--gold-light); background:rgba(184,148,63,.15); }
        .nav-right { display:flex; align-items:center; gap:12px; }
        .notif-btn { position:relative; color:rgba(255,255,255,.55); text-decoration:none; padding:8px; font-size:18px;
            border-radius:6px; transition:all .2s; }
        .notif-btn:hover { color:#fff; }
        .notif-dot { position:absolute; top:5px; right:5px; width:8px; height:8px; background:var(--terracotta);
            border-radius:50%; border:2px solid var(--stone); }
        .user-pill { display:flex; align-items:center; gap:8px; background:rgba(255,255,255,.08);
            border-radius:100px; padding:5px 14px 5px 6px; cursor:pointer; }
        .user-avatar { width:30px; height:30px; border-radius:50%; background:var(--gold); color:var(--stone);
            display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
        .user-name { color:rgba(255,255,255,.8); font-size:13px; }
        .logout-link { color:rgba(255,255,255,.4); font-size:12px; text-decoration:none; margin-left:8px; transition:color .2s; }
        .logout-link:hover { color:#fff; }

        /* ── Main ── */
        .main { margin-top:var(--nav-h); padding:40px; max-width:1100px; margin-left:auto; margin-right:auto; }

        /* ── Welcome Hero ── */
        .welcome-hero { background:var(--stone); border-radius:20px; padding:36px 40px;
            display:flex; align-items:center; justify-content:space-between; margin-bottom:32px;
            position:relative; overflow:hidden; }
        .welcome-hero::before { content:''; position:absolute; right:-60px; top:-60px;
            width:300px; height:300px; border-radius:50%;
            background:radial-gradient(circle, rgba(184,148,63,.2) 0%, transparent 70%); }
        .welcome-text h1 { font-family:'Playfair Display',serif; color:#fff; font-size:30px; font-weight:600; }
        .welcome-text h1 span { color:var(--gold-light); font-style:italic; }
        .welcome-text p { color:rgba(255,255,255,.45); font-size:14px; margin-top:6px; }
        .welcome-cta { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-primary { background:var(--gold); color:var(--stone); border:none; border-radius:8px;
            padding:11px 22px; font-size:13px; font-weight:600; font-family:'Jost',sans-serif;
            cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:all .2s; }
        .btn-primary:hover { background:var(--gold-light); color:var(--stone); }
        .btn-outline { background:transparent; color:rgba(255,255,255,.7); border:1px solid rgba(255,255,255,.2);
            border-radius:8px; padding:11px 22px; font-size:13px; font-weight:500; font-family:'Jost',sans-serif;
            cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:all .2s; }
        .btn-outline:hover { border-color:rgba(255,255,255,.5); color:#fff; }

        /* ── Stats ── */
        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px; }
        .stat-card { background:var(--white); border-radius:14px; border:1px solid var(--border); padding:20px 22px; }
        .stat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center;
            justify-content:center; font-size:17px; margin-bottom:12px; }
        .stat-val { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; color:var(--stone); line-height:1; }
        .stat-lbl { font-size:12px; color:var(--muted); margin-top:4px; letter-spacing:.3px; }

        /* ── Grid ── */
        .content-grid { display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start; }

        /* ── Cards ── */
        .card { background:var(--white); border-radius:16px; border:1px solid var(--border); overflow:hidden; margin-bottom:20px; }
        .card-head { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .card-head h3 { font-family:'Playfair Display',serif; font-size:17px; font-weight:600; }
        .card-head a { font-size:12px; color:var(--gold); text-decoration:none; font-weight:500; }
        .card-head a:hover { color:var(--terracotta); }
        .card-body { padding:20px 22px; }

        /* ── Booking Row ── */
        .booking-row { display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid #f4efe6; }
        .booking-row:last-child { border-bottom:none; }
        .booking-thumb { width:52px; height:52px; border-radius:10px; background:var(--sand-dark);
            object-fit:cover; flex-shrink:0; }
        .booking-thumb-placeholder { width:52px; height:52px; border-radius:10px; background:var(--sand-dark);
            display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; color:var(--muted); }
        .booking-info { flex:1; min-width:0; }
        .booking-ref { font-weight:600; font-size:13px; color:var(--stone); }
        .booking-property { font-size:12px; color:var(--muted); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .booking-dates { font-size:11px; color:var(--muted); margin-top:3px; }
        .booking-right { text-align:right; flex-shrink:0; }
        .booking-amount { font-weight:600; font-size:13px; color:var(--stone); }
        .booking-bal { font-size:11px; color:var(--terracotta); margin-top:2px; }

        /* ── Status Badges ── */
        .badge { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:600; white-space:nowrap; letter-spacing:.3px; text-transform:uppercase; }
        .b-pending    { background:#fef9c3; color:#a16207; }
        .b-confirmed  { background:#dcfce7; color:#15803d; }
        .b-checked_in { background:#dbeafe; color:#1d4ed8; }
        .b-checked_out{ background:#f1f5f9; color:#475569; }
        .b-cancelled  { background:#fee2e2; color:#dc2626; }

        /* ── Upcoming Card ── */
        .upcoming-card { background:linear-gradient(135deg, var(--stone) 0%, #3d3020 100%);
            border-radius:14px; padding:20px; margin-bottom:14px; position:relative; overflow:hidden; }
        .upcoming-card::after { content:''; position:absolute; right:-20px; bottom:-20px;
            width:100px; height:100px; border-radius:50%; background:rgba(184,148,63,.15); }
        .upcoming-label { font-size:10px; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .upcoming-property { font-family:'Playfair Display',serif; color:#fff; font-size:16px; font-weight:600; }
        .upcoming-dates { color:var(--gold-light); font-size:13px; margin-top:4px; }
        .upcoming-nights { color:rgba(255,255,255,.5); font-size:12px; margin-top:2px; }
        .upcoming-badge { display:inline-block; margin-top:10px; background:rgba(184,148,63,.25);
            color:var(--gold-light); padding:3px 10px; border-radius:20px; font-size:10px; font-weight:600; letter-spacing:.5px; }
        .no-upcoming { text-align:center; padding:28px 16px; color:var(--muted); font-size:13px; }
        .no-upcoming i { font-size:32px; display:block; margin-bottom:8px; opacity:.4; }

        /* ── Notification Row ── */
        .notif-row { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid #f4efe6; }
        .notif-row:last-child { border-bottom:none; }
        .notif-dot-item { width:8px; height:8px; border-radius:50%; background:var(--gold); margin-top:5px; flex-shrink:0; }
        .notif-dot-read { background:#d1cdc5; }
        .notif-title { font-size:13px; font-weight:500; }
        .notif-msg { font-size:12px; color:var(--muted); margin-top:2px; }
        .notif-time { font-size:11px; color:#c4bdb2; margin-top:2px; }
        .empty-notif { text-align:center; padding:24px; color:var(--muted); font-size:13px; }

        .alert { border-radius:10px; font-size:13px; padding:12px 16px; margin-bottom:20px; border:none; }
        .alert-success { background:#dcfce7; color:#15803d; }
        .alert-error { background:#fee2e2; color:#dc2626; }

        @media(max-width:768px) {
            .main { padding:20px; }
            .stats-row { grid-template-columns:1fr 1fr; }
            .content-grid { grid-template-columns:1fr; }
            .topnav { padding:0 20px; }
            .welcome-hero { flex-direction:column; gap:20px; text-align:center; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <div class="nav-links">
        <a href="{{ route('customer.home') }}" class="nav-link active">Dashboard</a>
        <a href="{{ route('customer.bookings') }}" class="nav-link">My Bookings</a>
        <a href="{{ route('customer.notifications') }}" class="nav-link">Notifications</a>
    </div>
    <div class="nav-right">
        <a href="{{ route('customer.notifications') }}" class="notif-btn">
            <i class="bi bi-bell"></i>
           @if($unreadNotifications->count() > 0)
                <span class="notif-dot"></span>
            @endif
        </a>
        <div class="user-pill">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
            <span class="user-name">{{ explode(' ', auth()->user()->full_name)[0] }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                <span class="logout-link">Sign out</span>
            </button>
        </form>
    </div>
</nav>

<main class="main">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Welcome Hero --}}
    <div class="welcome-hero">
        <div class="welcome-text">
            <h1>Welcome back, <span>{{ explode(' ', auth()->user()->full_name)[0] }}</span></h1>
            <p>{{ today()->format('l, F j, Y') }} · Your personal resort dashboard</p>
        </div>
        <div class="welcome-cta">
            <a href="{{ route('home') }}" class="btn-primary">
                <i class="bi bi-search"></i> Browse Properties
            </a>
            <a href="{{ route('customer.bookings') }}" class="btn-outline">
                <i class="bi bi-calendar3"></i> My Bookings
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-val">{{ $stats['total'] }}</div>
            <div class="stat-lbl">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">{{ $stats['upcoming'] }}</div>
            <div class="stat-lbl">Upcoming Stays</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-val">{{ $stats['completed'] }}</div>
            <div class="stat-lbl">Completed Stays</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(184,148,63,.15);color:#b8943f;"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-val" style="font-size:22px;">₱{{ number_format($stats['total_spent'], 0) }}</div>
            <div class="stat-lbl">Total Spent</div>
        </div>
    </div>

    <div class="content-grid">

        {{-- Left: Recent Bookings --}}
        <div>
            <div class="card">
                <div class="card-head">
                    <h3>Recent Bookings</h3>
                    <a href="{{ route('customer.bookings') }}">View all →</a>
                </div>
                <div class="card-body">
                    @forelse($recentBookings as $booking)
                    <div class="booking-row">
                        @if($booking->property?->primaryImage)
                            <img src="{{ asset('storage/'.$booking->property->primaryImage->image_path) }}"
                                class="booking-thumb" alt="">
                        @else
                            <div class="booking-thumb-placeholder"><i class="bi bi-house"></i></div>
                        @endif
                        <div class="booking-info">
                            <div class="booking-ref">
                                <a href="{{ route('customer.bookings.show', $booking) }}"
                                   style="color:var(--stone);text-decoration:none;">{{ $booking->booking_ref }}</a>
                            </div>
                            <div class="booking-property">{{ $booking->property->property_name ?? 'N/A' }}</div>
                            <div class="booking-dates">
                                {{ $booking->check_in_date->format('M d') }} — {{ $booking->check_out_date->format('M d, Y') }}
                                · {{ $booking->num_nights }} night{{ $booking->num_nights != 1 ? 's' : '' }}
                            </div>
                        </div>
                        <div class="booking-right">
                            <span class="badge b-{{ $booking->status }}">
                                {{ ucfirst(str_replace('_',' ',$booking->status)) }}
                            </span>
                            <div class="booking-amount" style="margin-top:5px;">₱{{ number_format($booking->total_amount,2) }}</div>
                            @if($booking->balance_due > 0)
                                <div class="booking-bal">₱{{ number_format($booking->balance_due,2) }} due</div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center;padding:40px;color:var(--muted);">
                        <i class="bi bi-calendar-x" style="font-size:36px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        No bookings yet.
                        <a href="{{ route('home') }}" style="color:var(--gold);display:block;margin-top:8px;font-size:13px;">
                            Browse our properties →
                        </a>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: Upcoming + Notifications --}}
        <div>
            {{-- Upcoming Stays --}}
            <div class="card">
                <div class="card-head">
                    <h3>Upcoming Stays</h3>
                    @if($upcomingBookings->count() > 0)
                    <a href="{{ route('customer.bookings') }}">All →</a>
                    @endif
                </div>
                <div class="card-body" style="padding:16px;">
                    @forelse($upcomingBookings as $booking)
                    <a href="{{ route('customer.bookings.show', $booking) }}" style="text-decoration:none;">
                        <div class="upcoming-card">
                            <div class="upcoming-label">Upcoming reservation</div>
                            <div class="upcoming-property">{{ $booking->property->property_name ?? 'N/A' }}</div>
                            <div class="upcoming-dates">
                                <i class="bi bi-calendar3" style="font-size:11px;"></i>
                                {{ $booking->check_in_date->format('M d') }} → {{ $booking->check_out_date->format('M d, Y') }}
                            </div>
                            <div class="upcoming-nights">{{ $booking->num_nights }} night{{ $booking->num_nights != 1 ? 's' : '' }} · {{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}</div>
                            <span class="upcoming-badge">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span>
                        </div>
                    </a>
                    @empty
                    <div class="no-upcoming">
                        <i class="bi bi-moon-stars"></i>
                        No upcoming stays.<br>
                        <a href="{{ route('home') }}" style="color:var(--gold);font-size:12px;margin-top:6px;display:inline-block;">
                            Plan your next visit →
                        </a>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Notifications --}}
            <div class="card">
                <div class="card-head">
                    <h3>Notifications</h3>
                    <a href="{{ route('customer.notifications') }}">All →</a>
                </div>
                <div class="card-body">
                    @forelse($unreadNotifications as $notif)
                    <div class="notif-row">
                        <div class="notif-dot-item {{ $notif->read_at ? 'notif-dot-read' : '' }}"></div>
                        <div>
                            <div class="notif-title">{{ $notif->title }}</div>
                            <div class="notif-msg">{{ Str::limit($notif->message, 70) }}</div>
                            <div class="notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="empty-notif">
                        <i class="bi bi-bell-slash" style="font-size:28px;display:block;margin-bottom:6px;opacity:.4;"></i>
                        No new notifications
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</main>

</body>
</html>