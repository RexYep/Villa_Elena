<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Admin')</title>
    @include('partials.favicon')

    @include('partials.fonts')
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
            <h1>@yield('page-title')</h1>
            <p>@yield('page-subtitle')</p>
        </div>
    </div>
    <div class="topbar-right">
        <div style="position:relative;display:inline-block;" id="notifWrapper">
            <button type="button" class="topbar-btn" id="notifBtn" onclick="toggleNotif()" aria-label="Notifications"
                aria-haspopup="true" aria-controls="notifDropdown">
                <i class="bi bi-bell" aria-hidden="true"></i>
                @if(($unreadCount ?? 0) > 0)
                    <span class="badge-dot" id="notifDot"></span>
                @endif
            </button>
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
        {{-- Ang `topbar-right` ay para sa mga aksyon ng pahina LANG. Laging
             narito ang Logout: dati ay pinapalitan ito ng section, kaya
             kinokopya ito ng bawat pahina — at ang mga nakalimot (Insights,
             Forecast, Recommendations) ay walang Logout. --}}
        @yield('topbar-right')
        {{-- Sa bawat pahina, hindi lang sa dashboard: dati ay Ctrl+K o "/"
             lang ang daan papunta sa search sa ibang pahina. --}}
        <button type="button" class="topbar-btn" onclick="openSearch()" aria-label="Search (Ctrl+K)"
            title="Search (Ctrl+K)">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</header>

<main class="main-content">
@yield('content')
</main>

@yield('modals')

{{-- Phone layout for tables: copy each column heading onto its cells so
     admin.css can show a row as labelled blocks (see "TABLES ON A PHONE").
     The observer covers rows that arrive later from a live refetch. --}}
<script>
    (function() {
        function label(table) {
            const heads = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            if (!heads.length) return;
            table.querySelectorAll('tbody tr').forEach(function(tr) {
                Array.from(tr.children).forEach(function(td, i) {
                    if (td.tagName === 'TD' && !td.hasAttribute('colspan') && !td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', heads[i] || '');
                    }
                });
            });
            table.classList.add('is-stacked');
        }

        function run() {
            document.querySelectorAll('.table-card table, table.audit-table').forEach(label);
        }
        run();

        const main = document.querySelector('.main-content');
        if (main && 'MutationObserver' in window) {
            let queued = false;
            new MutationObserver(function() {
                if (queued) return;
                queued = true;
                requestAnimationFrame(function() {
                    queued = false;
                    run();
                });
            }).observe(main, {
                childList: true,
                subtree: true
            });
        }
    })();
</script>
@include('partials.confirm_dialog')
@include('partials.password_ui')
@stack('scripts')
@include('admin.partials.realtime')
@include('admin.partials.topbar_features')
</body>
</html>
