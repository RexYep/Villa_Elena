<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Admin')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/admin.js'])
    @stack('styles')
</head>
<body>

@include('admin.partials.sidebar')
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h2>@yield('page-title')</h2>
            <p>@yield('page-subtitle')</p>
        </div>
    </div>
    <div class="topbar-right">
        <div style="position:relative;display:inline-block;" id="notifWrapper">
            <div class="topbar-btn" id="notifBtn" onclick="toggleNotif()" style="position:relative;cursor:pointer;">
                <i class="bi bi-bell"></i>
                @if(($unreadCount ?? 0) > 0)
                    <span class="badge-dot" id="notifDot"></span>
                @endif
            </div>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <div class="notif-header-title">Notifications</div>
                    <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList">
                    @forelse(($notifications ?? []) as $notif)
                    @php
                        $iconMap = [
                            'booking_update' => ['icon'=>'bi-calendar-check','bg'=>'#dcfce7','color'=>'#16a34a'],
                            'payment'        => ['icon'=>'bi-credit-card',   'bg'=>'#dbeafe','color'=>'#1d4ed8'],
                            'cancellation'   => ['icon'=>'bi-x-circle',      'bg'=>'#fee2e2','color'=>'#dc2626'],
                            'reminder'       => ['icon'=>'bi-bell',          'bg'=>'#fef9c3','color'=>'#a16207'],
                            'in_app'         => ['icon'=>'bi-info-circle',   'bg'=>'#f1f5f9','color'=>'#475569'],
                        ];
                        $ic = $iconMap[$notif->type] ?? $iconMap['in_app'];
                    @endphp
                    <a href="{{ route('admin.notifications.open', $notif) }}" class="notif-item {{ !$notif->is_read ? 'unread' : '' }}" style="text-decoration:none;color:inherit;display:flex;">
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
                    </a>
                    @empty
                    <div class="notif-empty">
                        <i class="bi bi-bell-slash"></i>
                        <p>No notifications yet</p>
                    </div>
                    @endforelse
                </div>
                <div class="notif-footer">
                    <a href="{{ route('admin.notifications.index') }}">View all notifications →</a>
                </div>
            </div>
        </div>
        @hasSection('topbar-right')
            @yield('topbar-right')
        @else
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
            </form>
        @endif
    </div>
</header>

<main class="main-content">
@yield('content')
</main>

@yield('modals')

@stack('scripts')
@include('admin.partials.realtime')
@include('admin.partials.topbar_features')
</body>
</html>
