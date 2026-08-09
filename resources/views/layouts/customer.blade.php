<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Resort')</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/portal.js'])
    @stack('styles')
</head>
<body>

<nav class="topnav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <div class="nav-links" id="accountNavLinks">
        <a href="{{ route('customer.home') }}" class="nav-link-item {{ request()->routeIs('customer.home') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('customer.bookings') }}" class="nav-link-item {{ request()->routeIs('customer.bookings*') ? 'active' : '' }}">My Bookings</a>
        <a href="{{ route('customer.notifications') }}" class="nav-link-item {{ request()->routeIs('customer.notifications') ? 'active' : '' }}">Notifications</a>
        <a href="{{ route('customer.reviews.index') }}" class="nav-link-item {{ request()->routeIs('customer.reviews.*') ? 'active' : '' }}">My Reviews</a>
        <a href="{{ route('customer.payments.index') }}" class="nav-link-item {{ request()->routeIs('customer.payments.*') ? 'active' : '' }}">Payments</a>
        <a href="{{ route('customer.profile.edit') }}" class="nav-link-item {{ request()->routeIs('customer.profile.*') ? 'active' : '' }}">Profile</a>
        <form method="POST" action="{{ route('logout') }}" class="nav-link-mobile-signout">
            @csrf
            <button type="submit" class="nav-link-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;">Sign out</button>
        </form>
    </div>
    <div class="nav-right">
        <a href="{{ route('customer.notifications') }}" class="notif-btn" id="notifBellLink">
            <i class="bi bi-bell"></i>
            @isset($unreadNotifications)
                @if($unreadNotifications->count() > 0)
                    <span class="notif-dot" id="notifDot"></span>
                @endif
            @endisset
        </a>
        <a href="{{ route('customer.profile.edit') }}" class="user-pill" style="text-decoration:none;">
            @if(auth()->user()->profile_image)
                <img src="{{ auth()->user()->profile_image_url }}" alt="Avatar" class="user-avatar" style="object-fit:cover;">
            @else
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
            @endif
            <span class="user-name">{{ explode(' ', auth()->user()->full_name)[0] }}</span>
        </a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                <span class="logout-link">Sign out</span>
            </button>
        </form>
        <button type="button" class="nav-hamburger" id="accountNavToggle" aria-label="Toggle menu" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
    </div>
</nav>

<main class="main">
@yield('content')
</main>

@stack('scripts')

{{-- Live notification bell: lights up the moment a new Notification row
     is created for this guest, no page refresh needed. --}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function () {
    const PUSHER_KEY     = '{{ env('PUSHER_APP_KEY') }}';
    const PUSHER_CLUSTER = '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}';
    const authUserId     = {{ auth()->id() ?? 'null' }};

    if (!PUSHER_KEY || !authUserId) return;

    const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        channelAuthorization: {
            endpoint: '{{ url('/broadcasting/auth') }}',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        },
    });

    const channel = pusher.subscribe('private-notifications.' + authUserId);
    channel.bind('notification.created', function () {
        const link = document.getElementById('notifBellLink');
        if (link && !document.getElementById('notifDot')) {
            const dot = document.createElement('span');
            dot.className = 'notif-dot';
            dot.id = 'notifDot';
            link.appendChild(dot);
        }
    });
})();
</script>
</body>
</html>
