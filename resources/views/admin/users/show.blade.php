<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $user->full_name }} — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card-side{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar-side{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge-side{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--text-muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}
        .breadcrumb-row .sep{color:#cbd5e1;}
        .breadcrumb-row .current{color:var(--text-main);font-weight:500;}

        .profile-grid{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;}
        .card-panel{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .card-header-custom{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .card-header-custom h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .card-body-custom{padding:20px 24px;}

        /* Profile Card */
        .profile-hero{background:var(--navy);padding:28px 24px;text-align:center;}
        .profile-avatar{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 12px;border:3px solid rgba(255,255,255,0.15);}
        .profile-name{font-family:'Cormorant Garamond',serif;color:#fff;font-size:20px;font-weight:700;}
        .profile-email{color:rgba(255,255,255,0.5);font-size:12px;margin-top:3px;}
        .role-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;margin-top:8px;}
        .role-admin    {background:#fef9c3;color:#a16207;}
        .role-staff    {background:#dbeafe;color:#1d4ed8;}
        .role-customer {background:#f3e8ff;color:#7c3aed;}

        /* Stat Cards */
        .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px;}
        .mini-stat{background:var(--off-white);border-radius:10px;padding:14px;text-align:center;}
        .mini-stat .val{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:var(--text-main);}
        .mini-stat .lbl{font-size:11px;color:var(--text-muted);margin-top:2px;}

        /* Info rows */
        .info-row{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
        .info-row:last-child{border-bottom:none;}
        .info-row .info-icon{width:28px;height:28px;border-radius:7px;background:var(--off-white);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;margin-top:1px;}
        .info-row .info-label{font-size:11px;color:var(--text-muted);margin-bottom:2px;}
        .info-row .info-value{font-weight:500;color:var(--text-main);}

        /* Action Buttons */
        .profile-actions{padding:16px;display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--border);}
        .btn-action{display:flex;align-items:center;justify-content:center;gap:7px;border-radius:9px;padding:9px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;border:none;width:100%;}
        .btn-edit{background:var(--navy);color:#fff;}
        .btn-edit:hover{opacity:.88;color:#fff;}
        .btn-toggle-active{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;}
        .btn-toggle-active:hover{background:#16a34a;color:#fff;border-color:#16a34a;}
        .btn-toggle-inactive{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;}
        .btn-toggle-inactive:hover{background:#ef4444;color:#fff;border-color:#ef4444;}

        /* Booking Table */
        table{width:100%;border-collapse:collapse;font-size:13px;}
        th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);padding:10px 16px;border-bottom:1px solid var(--border);text-align:left;}
        td{padding:12px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:#fafbfc;}
        .status-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .s-pending   {background:#fef9c3;color:#a16207;}
        .s-confirmed {background:#dcfce7;color:#15803d;}
        .s-checked_in{background:#dbeafe;color:#1d4ed8;}
        .s-checked_out{background:#f1f5f9;color:#475569;}
        .s-cancelled {background:#fee2e2;color:#dc2626;}
        .p-unpaid    {background:#fee2e2;color:#dc2626;}
        .p-partial   {background:#fef9c3;color:#a16207;}
        .p-paid      {background:#dcfce7;color:#15803d;}

        .empty-state{text-align:center;padding:40px;color:var(--text-muted);font-size:13px;}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Guest Profile</h2>
        <p>{{ $user->full_name }}</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="{{ route('admin.users.index') }}" style="display:flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:13px;border:1px solid var(--border);padding:7px 14px;border-radius:9px;background:#fff;">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</header>

<main class="main-content">

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.users.index') }}">Guests & Staff</a>
        <span class="sep">›</span>
        <span class="current">{{ $user->full_name }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="profile-grid">

        {{-- LEFT: Profile Card --}}
        <div>
            <div class="card-panel">
                {{-- Hero --}}
                <div class="profile-hero">
                    @php
                        $colors = ['#1d4ed8','#7c3aed','#15803d','#c9a84c','#dc2626','#0369a1'];
                        $color  = $colors[ord(strtolower($user->full_name[0])) % count($colors)];
                    @endphp
                    <div class="profile-avatar" style="background:{{ $color }}33;color:{{ $color }};">
                        {{ strtoupper(substr($user->full_name, 0, 1)) }}
                    </div>
                    <div class="profile-name">{{ $user->full_name }}</div>
                    <div class="profile-email">{{ $user->email }}</div>
                    <span class="role-badge role-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                    @if(!$user->status)
                        <div style="margin-top:8px;">
                            <span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Inactive</span>
                        </div>
                    @endif
                </div>

                {{-- Mini Stats --}}
                <div class="stats-grid">
                    <div class="mini-stat">
                        <div class="val">{{ $stats['total_bookings'] }}</div>
                        <div class="lbl">Total Bookings</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val">{{ $stats['completed'] }}</div>
                        <div class="lbl">Completed</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val">{{ $stats['cancelled'] }}</div>
                        <div class="lbl">Cancelled</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val" style="font-size:18px;">₱{{ number_format($stats['total_spent'], 0) }}</div>
                        <div class="lbl">Total Spent</div>
                    </div>
                </div>

                {{-- Info Rows --}}
                <div style="padding:16px 20px;">
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-telephone"></i></div>
                        <div>
                            <div class="info-label">Phone</div>
                            <div class="info-value">{{ $user->phone ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <div class="info-label">Address</div>
                            <div class="info-value">{{ $user->address ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-card-text"></i></div>
                        <div>
                            <div class="info-label">ID Type</div>
                            <div class="info-value">{{ $user->id_type ?? '—' }}</div>
                        </div>
                    </div>
                    @if($user->id_number)
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-upc"></i></div>
                        <div>
                            <div class="info-label">ID Number</div>
                            <div class="info-value">{{ $user->id_number }}</div>
                        </div>
                    </div>
                    @endif
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="info-label">Last Login</div>
                            <div class="info-value">
                                {{ $user->last_login ? $user->last_login->format('M d, Y h:i A') : 'Never' }}
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-calendar-plus"></i></div>
                        <div>
                            <div class="info-label">Registered</div>
                            <div class="info-value">{{ $user->created_at->format('M d, Y') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="profile-actions">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-action btn-edit">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </a>
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}" style="margin:0">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-action {{ $user->status ? 'btn-toggle-inactive' : 'btn-toggle-active' }}">
                            <i class="bi bi-{{ $user->status ? 'person-dash' : 'person-check' }}"></i>
                            {{ $user->status ? 'Deactivate Account' : 'Activate Account' }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT: Booking History --}}
        <div>
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Booking History</h3>
                    <a href="{{ route('admin.bookings.index', ['search' => $user->email]) }}"
                       style="font-size:12px;color:#2e5fa3;text-decoration:none;font-weight:500;">
                        View all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body-custom" style="padding:0;">
                    @if($bookings->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-calendar-x" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No bookings yet
                        </div>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking Ref</th>
                                    <th>Property</th>
                                    <th>Check-in</th>
                                    <th>Nights</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bookings as $booking)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.bookings.show', $booking) }}"
                                           style="font-weight:600;color:var(--navy);text-decoration:none;">
                                            {{ $booking->booking_ref }}
                                        </a>
                                    </td>
                                    <td>{{ $booking->property->property_name ?? 'N/A' }}</td>
                                    <td style="font-size:12px;color:var(--text-muted);">
                                        {{ $booking->check_in_date->format('M d, Y') }}
                                    </td>
                                    <td style="text-align:center;">{{ $booking->num_nights }}</td>
                                    <td style="font-weight:500;">₱{{ number_format($booking->total_amount, 2) }}</td>
                                    <td><span class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span></td>
                                    <td><span class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('admin.partials.realtime') 
</body>
</html>