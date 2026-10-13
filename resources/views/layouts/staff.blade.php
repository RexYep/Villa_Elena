<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Required by the frontdesk realtime partial: `private-staff-frontdesk`
         is authorised by a POST to /broadcasting/auth, which is in the `web`
         group and so needs the token. Without this tag the header is
         `undefined`, the POST is 419, and the subscription fails silently. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Staff')</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/js/staff.js'])
    @stack('styles')
</head>
<body>

<aside class="sidebar">
    <a href="{{ route('staff.frontdesk') }}" class="sidebar-brand">
        <div class="brand-name"><img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark"> Villa Elena</div>
        <div class="brand-role">Staff Portal</div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-label">Operations</div>
        <a href="{{ route('staff.frontdesk') }}" class="nav-item {{ request()->routeIs('staff.frontdesk') ? 'active' : '' }}">
            <i class="bi bi-house-door"></i> Frontdesk
            {{-- Today's arrivals and departures still to be handled. From
                 the layouts.staff composer, so it is on every staff page;
                 always in the page and hidden at zero, so the live update
                 (staff/partials/realtime) has an element to write to. --}}
            <span class="nav-badge" id="fdNavBadge" @if (($frontdeskBadge ?? 0) === 0) hidden @endif>{{ $frontdeskBadge ?? 0 }}</span>
        </a>
        <a href="{{ route('staff.availability') }}" class="nav-item {{ request()->routeIs('staff.availability') ? 'active' : '' }}">
            <i class="bi bi-calendar3"></i> Availability
        </a>
        <a href="{{ route('staff.bookings.index') }}" class="nav-item {{ request()->routeIs('staff.bookings.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i> Bookings
        </a>
        <a href="{{ route('staff.walkin') }}" class="nav-item {{ request()->routeIs('staff.walkin') ? 'active' : '' }}">
            <i class="bi bi-person-plus"></i> Walk-in Booking
        </a>
        @yield('sidebar-extra')
        <div class="nav-label">Account</div>
        <a href="{{ route('staff.profile.edit') }}" class="nav-item {{ request()->routeIs('staff.profile.*') ? 'active' : '' }}">
            <i class="bi bi-person-gear"></i> My Account
        </a>
        {{-- An admin may use the staff portal (role:staff,admin) and had no
             way back from it except the address bar. --}}
        @if (Auth::user()->role === 'admin')
            <a href="{{ route('admin.dashboard') }}" class="nav-item">
                <i class="bi bi-arrow-left-circle"></i> Admin Panel
            </a>
        @endif
    </nav>
    <div class="sidebar-footer">
        <div class="staff-info">
            <div class="staff-avatar">{{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}</div>
            <div>
                <div class="staff-name">{{ Auth::user()->full_name }}</div>
                <div class="staff-tag">{{ Auth::user()->role === 'admin' ? 'Admin' : 'Staff' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn" title="Sign out" aria-label="Sign out">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
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
            <h1 class="topbar-title">@yield('page-title')</h1>
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

@include('partials.confirm_dialog')
@include('partials.password_ui')
@stack('scripts')
@include('staff.partials.realtime')
</body>
</html>
