{{-- Shell ng bisita. Ang `$unreadNotifications` ay galing sa view
     composer sa AppServiceProvider, kaya nandito ito sa bawat pahina
     ng customer portal at hindi kailangang ipasa ng bawat controller. --}}
@php
    $unreadCount = isset($unreadNotifications) ? $unreadNotifications->count() : 0;
@endphp

<aside class="cust-sidebar" id="custSidebar">

    <div class="cust-sidebar-brand">
        <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    </div>

    <nav class="cust-sidebar-nav" aria-label="Main">
        <a href="{{ route('customer.home') }}"
            class="cust-nav-item {{ request()->routeIs('customer.home') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="{{ route('customer.bookings') }}"
            class="cust-nav-item {{ request()->routeIs('customer.bookings*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i> My Bookings
        </a>
        <a href="{{ route('customer.notifications') }}"
            class="cust-nav-item {{ request()->routeIs('customer.notifications') ? 'active' : '' }}">
            <i class="bi bi-bell"></i> Notifications
            {{-- Laging naka-render, gaya ng badge sa sidebar ng admin: kapag
                 dumating ang isang bagong notification habang bukas ang
                 pahina, kailangang may mahanap na elemento ang script. Ang
                 `hidden` ang nagtatago nito kapag wala pang bago. --}}
            <span class="cust-nav-count" id="custNavUnread" title="Unread notifications"
                @if (!$unreadCount) hidden @endif>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        </a>
        <a href="{{ route('customer.reviews.index') }}"
            class="cust-nav-item {{ request()->routeIs('customer.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-star"></i> My Reviews
        </a>
        <a href="{{ route('customer.payments.index') }}"
            class="cust-nav-item {{ request()->routeIs('customer.payments.*') ? 'active' : '' }}">
            <i class="bi bi-credit-card"></i> Payments
        </a>
        <a href="{{ route('customer.profile.edit') }}"
            class="cust-nav-item {{ request()->routeIs('customer.profile.*') ? 'active' : '' }}">
            <i class="bi bi-gear"></i> Settings
        </a>
    </nav>

    <div class="cust-sidebar-footer">
        <a href="{{ route('customer.profile.edit') }}" class="cust-user-card">
            @if (Auth::user()->profile_image)
                <img src="{{ Auth::user()->profile_image_url }}" alt="" class="user-avatar">
            @else
                <div class="user-avatar">{{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}</div>
            @endif
            <div class="cust-user-text">
                <div class="cust-user-name">{{ Auth::user()->full_name }}</div>
                <div class="cust-user-role">Guest</div>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="cust-signout">
                <i class="bi bi-box-arrow-right"></i> Sign out
            </button>
        </form>
    </div>

</aside>

{{-- Ang bar na ito ay wala sa desktop: ang bawat pahina ay may sariling
     pamagat sa loob ng laman, kaya walang ipapakita rito. Sa telepono ay
     ito lang ang paraan para buksan ang sidebar. --}}
<header class="cust-topbar">
    <button type="button" class="cust-bar-btn" id="custSidebarToggle" aria-label="Open menu" aria-expanded="false"
        aria-controls="custSidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena <span>Resort</span></a>
    <a href="{{ route('customer.notifications') }}" class="notif-btn" id="notifBellLink">
        <i class="bi bi-bell"></i>
        @if ($unreadCount > 0)
            <span class="notif-dot" id="notifDot"></span>
        @endif
    </a>
</header>

<div class="cust-backdrop" id="custBackdrop"></div>
