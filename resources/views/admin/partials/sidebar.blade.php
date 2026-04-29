<aside class="sidebar">

    <div class="sidebar-brand">
        <h1>🏝️ Villa Elena</h1>
        <p>Resort Management</p>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-item-custom {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-grid-1x2"></i></span>
            Dashboard
        </a>
        <a href="{{ route('admin.bookings.index') }}" class="nav-item-custom {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-calendar-check"></i></span>
            Bookings
        </a>
        <a href="{{ route('admin.properties.index') }}" class="nav-item-custom {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-house-door"></i></span>
            Properties
        </a>
        <a href="{{ route('admin.payments.index') }}" class="nav-item-custom {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-credit-card"></i></span>
            Payments
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">People</div>
        <a href="{{ route('admin.users.index') }}" class="nav-item-custom {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-people"></i></span>
            Guests
        </a>
        <a href="#" class="nav-item-custom {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
            Staff
        </a>
        <a href="{{ route('admin.reviews.index') }}" class="nav-item-custom {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-star"></i></span>
            Reviews
        </a>
        <a href="{{ route('admin.calendar.index') }}" class="nav-item-custom {{ request()->routeIs('admin.calendar.*') ? 'active' : '' }}">
        <span class="nav-icon"><i class="bi bi-calendar3"></i></span>
            Calendar
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Analytics</div>
        <a href="{{ route('admin.insights.index') }}" class="nav-item-custom {{ request()->routeIs('admin.insights.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-stars"></i></span>
            Smart Insights
        </a>
        <a href="{{ route('admin.forecast.index') }}" class="nav-item-custom {{ request()->routeIs('admin.forecast.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
            Forecast
        </a>
        <a href="{{ route('admin.reports.index') }}" class="nav-item-custom {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span>
            Reports
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">System</div>
        <a href="{{ route('admin.settings.index') }}" class="nav-item-custom {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-gear"></i></span>
            Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                {{ strtoupper(substr(auth()->user()->full_name ?? 'A', 0, 1)) }}
            </div>
            <div class="user-info">
                <div class="name">{{ auth()->user()->full_name ?? 'Admin' }}</div>
                <div class="role-badge">{{ ucfirst(auth()->user()->role ?? 'admin') }}</div>
            </div>
        </div>
    </div>

</aside>
