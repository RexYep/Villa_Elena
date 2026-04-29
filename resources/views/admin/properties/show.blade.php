{{-- SAVE AS: resources/views/admin/properties/show.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $property->property_name }} — Villa Elena Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
            --navy-light: #243b55;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dim:   rgba(201,168,76,0.15);
            --white:      #ffffff;
            --off-white:  #f4f6f9;
            --text-main:  #1a2f45;
            --text-muted: #6b7a8d;
            --border:     #e2e8f0;
            --sidebar-w:  260px;
            --topbar-h:   68px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--off-white); color: var(--text-main); overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar { position: fixed; top:0; left:0; width: var(--sidebar-w); height:100vh; background: var(--navy); display:flex; flex-direction:column; z-index:1000; overflow-y:auto; transition: transform .3s ease; }
        .sidebar-brand { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .sidebar-brand h1 { font-family: 'Cormorant Garamond', serif; color: var(--gold-light); font-size: 22px; font-weight:700; letter-spacing:0.3px; line-height:1.2; }
        .sidebar-brand p { color: rgba(255,255,255,0.35); font-size:11px; letter-spacing:1.5px; text-transform:uppercase; margin-top:3px; }
        .sidebar-section { padding: 20px 16px 8px; }
        .sidebar-section-label { font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:rgba(255,255,255,0.25); padding:0 8px; margin-bottom:6px; }
        .nav-item-custom { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:8px; color:rgba(255,255,255,0.6); text-decoration:none; font-size:14px; font-weight:400; transition:all .2s; margin-bottom:2px; }
        .nav-item-custom:hover { background:rgba(255,255,255,0.07); color:var(--white); }
        .nav-item-custom.active { background:var(--gold-dim); color:var(--gold-light); font-weight:500; }
        .nav-item-custom .nav-icon { width:32px; height:32px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; background:rgba(255,255,255,0.05); }
        .nav-item-custom.active .nav-icon { background:var(--gold-dim); color:var(--gold); }
        .sidebar-footer { margin-top:auto; padding:16px; border-top:1px solid rgba(255,255,255,0.07); }
        .user-card { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,0.05); }
        .user-avatar { width:36px; height:36px; border-radius:50%; background:var(--gold-dim); color:var(--gold); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:600; flex-shrink:0; }
        .user-info .name { color:var(--white); font-size:13px; font-weight:500; }
        .user-info .role-badge { font-size:10px; color:var(--gold); letter-spacing:0.5px; text-transform:uppercase; }

        /* TOPBAR */
        .topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:var(--white); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 32px; z-index:900; }
        .topbar-left h2 { font-family:'Cormorant Garamond',serif; font-size:22px; font-weight:600; color:var(--text-main); }
        .topbar-left p { font-size:12px; color:var(--text-muted); margin-top:1px; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .btn-navy { background:var(--navy); color:#fff; border:none; border-radius:9px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; display:inline-flex; align-items:center; gap:6px; transition:all .2s; text-decoration:none; }
        .btn-navy:hover { background:var(--gold); color:var(--navy); }
        .btn-outline { background:#fff; color:var(--text-main); border:1.5px solid var(--border); border-radius:9px; padding:8px 16px; font-size:13px; font-weight:500; cursor:pointer; font-family:'DM Sans',sans-serif; display:inline-flex; align-items:center; gap:6px; text-decoration:none; transition:all .2s; }
        .btn-outline:hover { border-color:var(--navy); color:var(--navy); }
        .logout-btn { display:flex; align-items:center; gap:7px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:9px; padding:7px 14px; font-size:13px; font-weight:500; cursor:pointer; transition:all .2s; text-decoration:none; }
        .logout-btn:hover { background:#ef4444; color:white; border-color:#ef4444; }

        /* MAIN */
        .main-content { margin-left:var(--sidebar-w); margin-top:var(--topbar-h); padding:32px; min-height:calc(100vh - var(--topbar-h)); }

        /* BREADCRUMB */
        .breadcrumb-bar { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--text-muted); margin-bottom:20px; }
        .breadcrumb-bar a { color:var(--text-muted); text-decoration:none; }
        .breadcrumb-bar a:hover { color:var(--navy); }
        .breadcrumb-bar .sep { opacity:.4; }
        .breadcrumb-bar .current { color:var(--text-main); font-weight:500; }

        /* STATUS BADGE */
        .status-pill { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .status-available   { background:#dcfce7; color:#15803d; }
        .status-occupied    { background:#fee2e2; color:#dc2626; }
        .status-maintenance { background:#fef9c3; color:#a16207; }

        /* CARDS */
        .panel { background:var(--white); border-radius:14px; border:1px solid var(--border); overflow:hidden; margin-bottom:20px; }
        .panel-head { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .panel-head h3 { font-family:'Cormorant Garamond',serif; font-size:17px; font-weight:600; color:var(--navy); }
        .panel-body { padding:22px; }

        /* STAT MINI CARDS */
        .mini-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }
        .mini-card { background:var(--white); border-radius:12px; border:1px solid var(--border); padding:18px 20px; }
        .mini-card .val { font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:700; color:var(--navy); line-height:1; }
        .mini-card .lbl { font-size:11px; color:var(--text-muted); margin-top:5px; text-transform:uppercase; letter-spacing:.5px; }
        .mini-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; margin-bottom:12px; }

        /* GALLERY */
        .gallery-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; }
        .gallery-img { width:100%; height:130px; object-fit:cover; border-radius:10px; border:1px solid var(--border); }
        .gallery-primary { border:2px solid var(--gold); }
        .no-image { width:100%; height:130px; background:#f8fafc; border-radius:10px; border:1px dashed var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:13px; flex-direction:column; gap:6px; }

        /* INFO GRID */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .info-item label { font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:4px; }
        .info-item .val { font-size:14px; color:var(--text-main); font-weight:500; }

        /* AMENITIES */
        .amenity-tag { display:inline-flex; align-items:center; gap:5px; background:#f1f5f9; border-radius:20px; padding:4px 12px; font-size:12px; color:var(--text-main); margin:3px; }

        /* TABLE */
        table { width:100%; border-collapse:collapse; }
        thead th { padding:10px 14px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--border); background:#f8fafc; }
        tbody td { padding:12px 14px; font-size:13px; border-bottom:1px solid #f8fafc; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#fafbfc; }

        .booking-status { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .booking-status.confirmed  { background:#dcfce7; color:#15803d; }
        .booking-status.pending    { background:#fef9c3; color:#a16207; }
        .booking-status.cancelled  { background:#fee2e2; color:#dc2626; }
        .booking-status.checked_in { background:#dbeafe; color:#1d4ed8; }
        .booking-status.checked_out{ background:#f1f5f9; color:#475569; }
        .booking-status.completed  { background:#f0fdf4; color:#16a34a; }

        /* REVIEW STARS */
        .stars { color:#f59e0b; font-size:13px; }
        .empty-state { text-align:center; padding:40px; color:var(--text-muted); }
        .empty-state i { font-size:36px; display:block; margin-bottom:8px; opacity:.35; }

        /* DANGER ZONE */
        .danger-zone { background:#fff5f5; border:1px solid #fecaca; border-radius:14px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .btn-danger { background:#dc2626; color:#fff; border:none; border-radius:9px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .2s; }
        .btn-danger:hover { background:#b91c1c; }

        .two-col { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        @media(max-width:900px) { .two-col { grid-template-columns:1fr; } .mini-stats { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>

@include('admin.partials.sidebar')

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <h2>{{ $property->property_name }}</h2>
        <p>Property Details</p>
    </div>
    <div class="topbar-right">
        <a href="{{ route('admin.properties.index') }}" class="btn-outline">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="{{ route('admin.properties.edit', $property) }}" class="btn-navy">
            <i class="bi bi-pencil"></i> Edit Property
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</div>

<div class="main-content">

    {{-- BREADCRUMB --}}
    <div class="breadcrumb-bar">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.properties.index') }}">Properties</a>
        <span class="sep">›</span>
        <span class="current">{{ $property->property_name }}</span>
    </div>

    @if(session('success'))
        <div style="background:#dcfce7; color:#15803d; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:13px;">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    {{-- MINI STAT CARDS --}}
    <div class="mini-stats">
        <div class="mini-card">
            <div class="mini-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-cash-stack"></i></div>
            <div class="val">₱{{ number_format($property->base_price, 0) }}</div>
            <div class="lbl">Base Price / Night</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-people"></i></div>
            <div class="val">{{ $property->max_capacity }}</div>
            <div class="lbl">Max Capacity</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-calendar-check"></i></div>
            <div class="val">{{ $property->bookings->count() }}</div>
            <div class="lbl">Total Bookings</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#f3e8ff; color:#7c3aed;"><i class="bi bi-star"></i></div>
            <div class="val">{{ $property->reviews->count() > 0 ? number_format($property->reviews->avg('rating'), 1) : '—' }}</div>
            <div class="lbl">Avg Rating</div>
        </div>
    </div>

    <div class="two-col">
        {{-- LEFT COLUMN --}}
        <div>

            {{-- PROPERTY INFO --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Property Information</h3>
                    <span class="status-pill status-{{ $property->status }}">{{ ucfirst($property->status) }}</span>
                </div>
                <div class="panel-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Property Name</label>
                            <div class="val">{{ $property->property_name }}</div>
                        </div>
                        <div class="info-item">
                            <label>Type</label>
                            <div class="val">{{ ucfirst($property->type) }}</div>
                        </div>
                        <div class="info-item">
                            <label>Base Price</label>
                            <div class="val">₱{{ number_format($property->base_price, 2) }}</div>
                        </div>
                        <div class="info-item">
                            <label>Weekend Price</label>
                            <div class="val">{{ $property->weekend_price ? '₱'.number_format($property->weekend_price,2) : '—' }}</div>
                        </div>
                        <div class="info-item">
                            <label>Floor Area</label>
                            <div class="val">{{ $property->floor_area_sqm ? $property->floor_area_sqm.' sqm' : '—' }}</div>
                        </div>
                        <div class="info-item">
                            <label>Max Capacity</label>
                            <div class="val">{{ $property->max_capacity }} guests</div>
                        </div>
                        <div class="info-item">
                            <label>Featured</label>
                            <div class="val">
                                @if($property->is_featured)
                                    <span style="color:#d97706;"><i class="bi bi-star-fill"></i> Yes</span>
                                @else
                                    <span style="color:var(--text-muted);">No</span>
                                @endif
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Sort Order</label>
                            <div class="val">{{ $property->sort_order }}</div>
                        </div>
                    </div>

                    @if($property->description)
                        <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <label style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:8px;">Description</label>
                            <p style="font-size:14px; color:var(--text-main); line-height:1.7;">{{ $property->description }}</p>
                        </div>
                    @endif

                    @if(!empty($property->amenities))
                        <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <label style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:8px;">Amenities</label>
                            <div>
                                @foreach($property->amenities as $amenity)
                                    <span class="amenity-tag"><i class="bi bi-check-circle-fill" style="color:#16a34a; font-size:11px;"></i> {{ $amenity }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- IMAGES --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Photos</h3>
                    <a href="{{ route('admin.properties.edit', $property) }}" style="font-size:12px; color:var(--gold); text-decoration:none; font-weight:600;">
                        <i class="bi bi-plus-lg"></i> Add Photos
                    </a>
                </div>
                <div class="panel-body">
                    @if($property->images->count() > 0)
                        <div class="gallery-grid">
                            @foreach($property->images as $image)
                                <div style="position:relative;">
                                    <img src="{{ Storage::url($image->image_path) }}"
                                         alt="{{ $image->alt_text }}"
                                         class="gallery-img {{ $image->is_primary ? 'gallery-primary' : '' }}">
                                    @if($image->is_primary)
                                        <span style="position:absolute; top:6px; left:6px; background:var(--gold); color:#fff; font-size:9px; font-weight:700; padding:2px 7px; border-radius:10px; text-transform:uppercase; letter-spacing:.3px;">Primary</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="no-image">
                            <i class="bi bi-image" style="font-size:28px; opacity:.3;"></i>
                            No photos uploaded yet
                        </div>
                    @endif
                </div>
            </div>

            {{-- RECENT BOOKINGS --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Recent Bookings</h3>
                    <a href="{{ route('admin.bookings.index') }}?property={{ $property->id }}" style="font-size:12px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                        View all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                @if($recentBookings->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        No bookings yet for this property.
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Booking Ref</th>
                                <th>Guest</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentBookings as $booking)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.bookings.show', $booking) }}" style="font-weight:600; color:var(--navy); text-decoration:none; font-size:12px;">
                                        {{ $booking->booking_ref }}
                                    </a>
                                </td>
                                <td>{{ $booking->user->full_name ?? 'N/A' }}</td>
                                <td style="font-size:12px; color:var(--text-muted);">{{ $booking->check_in_date->format('M d, Y') }}</td>
                                <td style="font-size:12px; color:var(--text-muted);">{{ $booking->check_out_date->format('M d, Y') }}</td>
                                <td>
                                    <span class="booking-status {{ $booking->status }}">
                                        {{ ucfirst(str_replace('_',' ',$booking->status)) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- REVIEWS --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Reviews <span style="font-size:13px; font-weight:400; color:var(--text-muted);">({{ $property->reviews->count() }})</span></h3>
                </div>
                @if($property->reviews->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-star"></i>
                        No reviews yet.
                    </div>
                @else
                    <div class="panel-body" style="display:flex; flex-direction:column; gap:14px;">
                        @foreach($property->reviews->take(5) as $review)
                        <div style="padding:14px; background:#f8fafc; border-radius:10px; border:1px solid var(--border);">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <span style="font-weight:600; font-size:13px;">{{ $review->user->full_name ?? 'Guest' }}</span>
                                <div class="stars">
                                    @for($i=1; $i<=5; $i++)
                                        <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                    @endfor
                                </div>
                            </div>
                            @if($review->comment)
                                <p style="font-size:13px; color:var(--text-muted); line-height:1.5;">{{ $review->comment }}</p>
                            @endif
                            <div style="font-size:11px; color:#94a3b8; margin-top:6px;">{{ $review->created_at->format('M d, Y') }}</div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- RIGHT COLUMN --}}
        <div>

            {{-- QUICK ACTIONS --}}
            <div class="panel">
                <div class="panel-head"><h3>Quick Actions</h3></div>
                <div class="panel-body" style="display:flex; flex-direction:column; gap:8px;">
                    <a href="{{ route('admin.properties.edit', $property) }}" class="btn-navy" style="justify-content:center;">
                        <i class="bi bi-pencil"></i> Edit Property
                    </a>
                    <a href="{{ route('admin.bookings.create') }}?property_id={{ $property->id }}" class="btn-outline" style="justify-content:center;">
                        <i class="bi bi-plus-circle"></i> New Booking
                    </a>
                    <button class="btn-outline" style="justify-content:center; width:100%;" onclick="document.getElementById('blockDatesPanel').scrollIntoView({behavior:'smooth'})">
                        <i class="bi bi-calendar-x"></i> Block Dates
                    </button>
                </div>
            </div>

            {{-- PRICING RULES --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Pricing Rules</h3>
                </div>
                @if($property->pricingRules->isEmpty())
                    <div class="empty-state" style="padding:24px;">
                        <i class="bi bi-tag" style="font-size:24px;"></i>
                        <p style="font-size:12px; margin-top:6px;">No special pricing rules</p>
                    </div>
                @else
                    <div class="panel-body" style="display:flex; flex-direction:column; gap:8px;">
                        @foreach($property->pricingRules as $rule)
                        <div style="background:#f8fafc; border-radius:9px; padding:10px 14px; border:1px solid var(--border); font-size:13px;">
                            <div style="font-weight:600;">{{ $rule->name ?? ucfirst($rule->type) }}</div>
                            <div style="color:var(--text-muted); font-size:12px; margin-top:2px;">
                                ₱{{ number_format($rule->price ?? $rule->amount, 2) }}
                                @if(isset($rule->start_date))
                                    · {{ \Carbon\Carbon::parse($rule->start_date)->format('M d') }} – {{ \Carbon\Carbon::parse($rule->end_date)->format('M d, Y') }}
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- AVAILABILITY BLOCKS --}}
            <div class="panel" id="blockDatesPanel">
                <div class="panel-head"><h3>Block Dates</h3></div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.properties.block', $property) }}"
                        @csrf
                        <div style="margin-bottom:12px;">
                            <label style="font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Start Date</label>
                            <input type="date" name="start_date" required min="{{ date('Y-m-d') }}"
                                style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">End Date</label>
                            <input type="date" name="end_date" required min="{{ date('Y-m-d') }}"
                                style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Reason</label>
                            <select name="reason" required style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif; background:#fff;">
                                <option value="maintenance">Maintenance</option>
                                <option value="owner_use">Owner Use</option>
                                <option value="private_event">Private Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label style="font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Notes <span style="font-weight:400; color:var(--text-muted);">(optional)</span></label>
                            <input type="text" name="notes" placeholder="e.g. Repainting the walls"
                                style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                        </div>
                        <button type="submit" class="btn-navy" style="width:100%; justify-content:center;">
                            <i class="bi bi-calendar-x"></i> Block These Dates
                        </button>
                    </form>

                    {{-- Existing blocks --}}
                    @if($property->availabilityBlocks->count() > 0)
                        <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <div style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px;">Blocked Periods</div>
                            @foreach($property->availabilityBlocks->sortByDesc('start_date')->take(5) as $block)
                            <div style="background:#fff5f5; border-radius:8px; padding:9px 12px; margin-bottom:6px; border:1px solid #fecaca; font-size:12px;">
                                <div style="font-weight:600; color:#dc2626;">
                                    {{ \Carbon\Carbon::parse($block->start_date)->format('M d') }} – {{ \Carbon\Carbon::parse($block->end_date)->format('M d, Y') }}
                                </div>
                                <div style="color:#6b7a8d; margin-top:2px;">{{ ucfirst(str_replace('_',' ',$block->reason)) }}{{ $block->notes ? ' · '.$block->notes : '' }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- DANGER ZONE --}}
            <div class="danger-zone">
                <div>
                    <div style="font-weight:600; color:#dc2626; font-size:14px;"><i class="bi bi-exclamation-triangle me-2"></i>Delete Property</div>
                    <div style="font-size:12px; color:#6b7a8d; margin-top:2px;">This will permanently delete this property and all its data.</div>
                </div>
                <button class="btn-danger" onclick="confirmDelete()">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>

            <form id="deleteForm" method="POST" action="{{ route('admin.properties.destroy', $property) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>

        </div>
    </div>

</div>{{-- end main-content --}}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete "{{ addslashes($property->property_name) }}"? This cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@include('admin.partials.realtime') 
@include('admin.partials.topbar_features')
</body>
</html>