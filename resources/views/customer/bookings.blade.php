{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- SAVE THIS AS: resources/views/customer/bookings.blade.php   --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--olive:#5c5a3c;--cream:#fdfbf7;--terracotta:#c4673a;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--white:#ffffff;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);color:var(--stone);min-height:100vh;}
        .topnav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--stone);z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;letter-spacing:1px;text-decoration:none;}
        .nav-brand span{color:rgba(255,255,255,.35);font-style:italic;font-size:14px;margin-left:8px;}
        .nav-link-item{color:rgba(255,255,255,.55);text-decoration:none;font-size:13px;font-weight:500;padding:8px 14px;border-radius:6px;transition:all .2s;}
        .nav-link-item:hover{color:#fff;background:rgba(255,255,255,.08);}
        .nav-link-item.active{color:var(--gold-light);}
        .logout-link{color:rgba(255,255,255,.4);font-size:12px;text-decoration:none;transition:color .2s;}
        .logout-link:hover{color:#fff;}
        .main{margin-top:var(--nav-h);padding:40px;max-width:900px;margin-left:auto;margin-right:auto;}
        .page-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:600;margin-bottom:6px;}
        .page-sub{color:var(--muted);font-size:14px;margin-bottom:28px;}
        .filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
        .filter-btn{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;border:1.5px solid var(--border);color:var(--muted);background:#fff;transition:all .2s;font-family:'Jost',sans-serif;cursor:pointer;}
        .filter-btn:hover,.filter-btn.active{background:var(--stone);color:#fff;border-color:var(--stone);}
        .booking-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:14px;transition:box-shadow .2s;}
        .booking-card:hover{box-shadow:0 4px 20px rgba(44,36,22,.08);}
        .booking-card-inner{display:flex;align-items:center;gap:16px;padding:18px 22px;}
        .bc-img{width:68px;height:68px;border-radius:12px;object-fit:cover;flex-shrink:0;background:var(--sand);}
        .bc-img-placeholder{width:68px;height:68px;border-radius:12px;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--muted);flex-shrink:0;}
        .bc-info{flex:1;min-width:0;}
        .bc-ref{font-weight:600;font-size:14px;color:var(--stone);text-decoration:none;}
        .bc-ref:hover{color:var(--gold);}
        .bc-property{font-size:13px;color:var(--muted);margin-top:2px;}
        .bc-dates{font-size:12px;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:6px;}
        .bc-right{text-align:right;flex-shrink:0;}
        .bc-amount{font-weight:700;font-size:15px;font-family:'Playfair Display',serif;}
        .bc-balance{font-size:11px;color:var(--terracotta);margin-top:2px;}
        .badge{padding:3px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
        .b-pending{background:#fef9c3;color:#a16207;}
        .b-confirmed{background:#dcfce7;color:#15803d;}
        .b-checked_in{background:#dbeafe;color:#1d4ed8;}
        .b-checked_out{background:#f1f5f9;color:#475569;}
        .b-cancelled{background:#fee2e2;color:#dc2626;}
        .empty-state{text-align:center;padding:60px;color:var(--muted);}
        .empty-state i{font-size:44px;display:block;margin-bottom:12px;opacity:.35;}
        .pagination .page-link{border-radius:7px;font-size:13px;color:var(--stone);border-color:var(--border);font-family:'Jost',sans-serif;}
        .pagination .page-item.active .page-link{background:var(--stone);border-color:var(--stone);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}
    </style>
</head>
<body>
<nav class="topnav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <div style="display:flex;gap:4px;">
        <a href="{{ route('customer.home') }}" class="nav-link-item">Dashboard</a>
        <a href="{{ route('customer.bookings') }}" class="nav-link-item active">My Bookings</a>
        <a href="{{ route('customer.notifications') }}" class="nav-link-item">Notifications</a>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
            <span class="logout-link">Sign out</span>
        </button>
    </form>
</nav>

<main class="main">
    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="page-title">My Bookings</div>
    <div class="page-sub">All your reservations at Villa Elena Resort</div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('customer.bookings') }}">
        <div class="filter-bar">
            <button type="submit" name="status" value="" class="filter-btn {{ !request('status') ? 'active' : '' }}">All</button>
            <button type="submit" name="status" value="pending"     class="filter-btn {{ request('status')=='pending'     ? 'active' : '' }}">Pending</button>
            <button type="submit" name="status" value="confirmed"   class="filter-btn {{ request('status')=='confirmed'   ? 'active' : '' }}">Confirmed</button>
            <button type="submit" name="status" value="checked_in"  class="filter-btn {{ request('status')=='checked_in'  ? 'active' : '' }}">Checked In</button>
            <button type="submit" name="status" value="checked_out" class="filter-btn {{ request('status')=='checked_out' ? 'active' : '' }}">Completed</button>
            <button type="submit" name="status" value="cancelled"   class="filter-btn {{ request('status')=='cancelled'   ? 'active' : '' }}">Cancelled</button>
        </div>
    </form>

    @forelse($bookings as $booking)
    <div class="booking-card">
        <div class="booking-card-inner">
            @if($booking->property?->primaryImage)
                <img src="{{ asset('storage/'.$booking->property->primaryImage->image_path) }}" class="bc-img" alt="">
            @else
                <div class="bc-img-placeholder"><i class="bi bi-house"></i></div>
            @endif
            <div class="bc-info">
                <a href="{{ route('customer.bookings.show', $booking) }}" class="bc-ref">{{ $booking->booking_ref }}</a>
                <div class="bc-property">{{ $booking->property->property_name ?? 'N/A' }}</div>
                <div class="bc-dates">
                    <i class="bi bi-calendar3" style="font-size:11px;"></i>
                    {{ $booking->check_in_date->format('M d, Y') }} &rarr; {{ $booking->check_out_date->format('M d, Y') }}
                    &nbsp;·&nbsp; {{ $booking->num_nights }} night{{ $booking->num_nights!=1?'s':'' }}
                    &nbsp;·&nbsp; {{ $booking->num_guests }} guest{{ $booking->num_guests!=1?'s':'' }}
                </div>
            </div>
            <div class="bc-right">
                <span class="badge b-{{ $booking->status }}">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span>
                <div class="bc-amount" style="margin-top:7px;">₱{{ number_format($booking->total_amount,2) }}</div>
                @if($booking->balance_due > 0 && $booking->status !== 'cancelled')
                    <div class="bc-balance">₱{{ number_format($booking->balance_due,2) }} balance due</div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <p style="font-size:15px;margin-bottom:6px;">No bookings found</p>
        <a href="{{ route('home') }}" style="color:var(--gold);font-size:13px;">Browse our properties →</a>
    </div>
    @endforelse

    @if($bookings->hasPages())
        <div style="margin-top:20px;">{{ $bookings->links() }}</div>
    @endif
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>