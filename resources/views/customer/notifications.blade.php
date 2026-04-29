<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Villa Elena Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sand:#f5f0e8;--stone:#2c2416;--cream:#fdfbf7;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--white:#ffffff;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);color:var(--stone);}
        .topnav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--stone);z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;letter-spacing:1px;text-decoration:none;}
        .nav-brand span{color:rgba(255,255,255,.35);font-style:italic;font-size:14px;margin-left:8px;}
        .nav-link-item{color:rgba(255,255,255,.55);text-decoration:none;font-size:13px;padding:8px 14px;border-radius:6px;transition:all .2s;}
        .nav-link-item:hover{color:#fff;}
        .nav-link-item.active{color:var(--gold-light);}
        .logout-link{color:rgba(255,255,255,.4);font-size:12px;text-decoration:none;}
        .logout-link:hover{color:#fff;}
        .main{margin-top:var(--nav-h);padding:40px;max-width:700px;margin-left:auto;margin-right:auto;}
        .page-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:600;margin-bottom:6px;}
        .page-sub{color:var(--muted);font-size:14px;margin-bottom:28px;}
        .notif-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:10px;transition:box-shadow .2s;}
        .notif-card.unread{border-left:3px solid var(--gold);}
        .notif-inner{display:flex;gap:16px;padding:18px 22px;}
        .notif-icon-wrap{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
        .notif-body{flex:1;}
        .notif-title{font-weight:600;font-size:14px;}
        .notif-msg{font-size:13px;color:var(--muted);margin-top:3px;line-height:1.5;}
        .notif-time{font-size:11px;color:#c4bdb2;margin-top:5px;}
        .unread-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:6px;}
        .empty-state{text-align:center;padding:60px;color:var(--muted);}
        .empty-state i{font-size:44px;display:block;margin-bottom:12px;opacity:.35;}
        .pagination .page-link{border-radius:7px;font-size:13px;color:var(--stone);border-color:var(--border);}
        .pagination .page-item.active .page-link{background:var(--stone);border-color:var(--stone);}
    </style>
</head>
<body>
<nav class="topnav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <div style="display:flex;gap:4px;">
        <a href="{{ route('customer.home') }}" class="nav-link-item">Dashboard</a>
        <a href="{{ route('customer.bookings') }}" class="nav-link-item">My Bookings</a>
        <a href="{{ route('customer.notifications') }}" class="nav-link-item active">Notifications</a>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
            <span class="logout-link">Sign out</span>
        </button>
    </form>
</nav>

<main class="main">
    <div class="page-title">Notifications</div>
    <div class="page-sub">Your booking updates and resort announcements</div>

    @forelse($notifications as $notif)
    @php
        $icons = [
            'booking_update'    => ['icon'=>'bi-calendar-check', 'bg'=>'#dcfce7', 'color'=>'#15803d'],
            'payment'           => ['icon'=>'bi-credit-card',    'bg'=>'#dbeafe', 'color'=>'#1d4ed8'],
            'cancellation'      => ['icon'=>'bi-x-circle',       'bg'=>'#fee2e2', 'color'=>'#dc2626'],
            'reminder'          => ['icon'=>'bi-bell',           'bg'=>'#fef9c3', 'color'=>'#a16207'],
        ];
        $style = $icons[$notif->type] ?? ['icon'=>'bi-info-circle','bg'=>'#f1f5f9','color'=>'#475569'];
    @endphp
   <div class="notif-card {{ !$notif->is_read ? 'unread' : '' }}">
        <div class="notif-inner">
            <div class="notif-icon-wrap" style="background:{{ $style['bg'] }};color:{{ $style['color'] }};">
                <i class="bi {{ $style['icon'] }}"></i>
            </div>
            <div class="notif-body">
                <div class="notif-title">{{ $notif->title }}</div>
                <div class="notif-msg">{{ $notif->message }}</div>
                <div class="notif-time">{{ $notif->created_at->diffForHumans() }} · {{ $notif->created_at->format('M d, Y h:i A') }}</div>
            </div>
            @if(!$notif->is_read)
            <div class="unread-dot"></div>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="bi bi-bell-slash"></i>
        <p style="font-size:15px;margin-bottom:4px;">No notifications yet</p>
        <p style="font-size:13px;">We'll notify you about booking updates and important information.</p>
    </div>
    @endforelse

    @if($notifications->hasPages())
        <div style="margin-top:20px;">{{ $notifications->links() }}</div>
    @endif
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>