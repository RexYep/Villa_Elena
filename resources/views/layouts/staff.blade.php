<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Villa Elena Staff')</title>
    @include('partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/staff.js'])
    @stack('styles')
</head>
<body>

<aside class="sidebar">
    <a href="{{ route('staff.frontdesk') }}" class="sidebar-brand">
        <div class="brand-name">Villa Elena</div>
        <div class="brand-role">Staff Portal</div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-label">Operations</div>
        <a href="{{ route('staff.frontdesk') }}" class="nav-item {{ request()->routeIs('staff.frontdesk') ? 'active' : '' }}">
            <i class="bi bi-house-door"></i> Frontdesk
            @yield('frontdesk-badge')
        </a>
        <a href="{{ route('staff.availability') }}" class="nav-item {{ request()->routeIs('staff.availability') ? 'active' : '' }}">
            <i class="bi bi-calendar3"></i> Availability
        </a>
        <a href="{{ route('staff.walkin') }}" class="nav-item {{ request()->routeIs('staff.walkin') ? 'active' : '' }}">
            <i class="bi bi-person-plus"></i> Walk-in Booking
        </a>
        @yield('sidebar-extra')
    </nav>
    <div class="sidebar-footer">
        <div class="staff-info">
            <div class="staff-avatar">{{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}</div>
            <div>
                <div class="staff-name">{{ Auth::user()->full_name }}</div>
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
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="topbar">
    <div class="topbar-left">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <div class="topbar-title">@yield('page-title')</div>
            <div class="topbar-sub">@yield('page-subtitle')</div>
        </div>
    </div>
    @hasSection('topbar-right')
        <div class="topbar-right">
            @yield('topbar-right')
        </div>
    @endif
</div>

<main class="main">
@yield('content')
</main>

@yield('modals')

@stack('scripts')
@include('staff.partials.realtime')
</body>
</html>
