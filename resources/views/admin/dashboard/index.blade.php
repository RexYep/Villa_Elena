@extends('layouts.admin')

@section('title', 'Admin Dashboard — Villa Elena Resort')
@section('page-title', 'Good ' . (now()->hour < 12 ? 'Morning' : (now()->hour < 18 ? 'Afternoon' : 'Evening' )) . ', ' .
        explode(' ', Auth::user()->full_name)[0] . ' 👋') @section('page-subtitle', now()->format('l, F j, Y') . " ​·​
            Here's what's happening at the resort today")

        @section('topbar-right')
            <div class="topbar-btn" onclick="openSearch()" style="cursor:pointer;">
                <i class="bi bi-search"></i>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        @endsection

        @push('styles')
            @vite(['resources/js/admin-charts.js'])
            <style>
                /* ── KPI CARDS ────────────────────────────────────── */
                .kpi-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    margin-bottom: 28px;
                }

                .kpi-card {
                    background: var(--cream);
                    border-radius: 14px;
                    padding: 22px 24px;
                    border: 1px solid var(--border);
                    position: relative;
                    overflow: hidden;
                    transition: transform .2s, box-shadow .2s;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
                }

                .kpi-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
                }

                .kpi-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                }

                .kpi-card.gold::before {
                    background: linear-gradient(90deg, var(--gold), var(--gold-light));
                }

                .kpi-card.teal::before {
                    background: linear-gradient(90deg, var(--teal, #14b8a6), #5ee7e7);
                }

                .kpi-card.navy::before {
                    background: linear-gradient(90deg, var(--navy-mid, #1e3a5f), #3a6186);
                }

                .kpi-card.green::before {
                    background: linear-gradient(90deg, #16a34a, #4ade80);
                }

                .kpi-card.orange::before {
                    background: linear-gradient(90deg, #ea580c, #fb923c);
                }

                .kpi-card.purple::before {
                    background: linear-gradient(90deg, #7c3aed, #a78bfa);
                }

                .kpi-card.rose::before {
                    background: linear-gradient(90deg, #e11d48, #fb7185);
                }

                .kpi-card.sky::before {
                    background: linear-gradient(90deg, #0284c7, #38bdf8);
                }

                .kpi-label {
                    font-size: 14px;
                    font-weight: 500;
                    color: var(--muted);
                    text-transform: uppercase;
                    letter-spacing: 0.8px;
                    margin-bottom: 10px;
                }

                .kpi-value {
                    font-family: 'Cormorant Garamond', serif;
                    font-size: 36px;
                    font-weight: 700;
                    color: var(--stone);
                    line-height: 1;
                    margin-bottom: 8px;
                }

                .guest-name-card .kpi-value {
                    font-size: 20px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .kpi-sub {
                    font-size: 14px;
                    color: var(--muted);
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .kpi-sub .up {
                    color: #16a34a;
                }

                .kpi-sub .down {
                    color: #ef4444;
                }

                .kpi-icon {
                    position: absolute;
                    bottom: 16px;
                    right: 20px;
                    font-size: 38px;
                    opacity: 0.06;
                    color: var(--stone);
                }

                /* ── CHARTS ROW ───────────────────────────────────── */
                .charts-row {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 20px;
                    margin-bottom: 28px;
                }

                .card-panel {
                    background: var(--cream);
                    border-radius: 14px;
                    border: 1px solid var(--border);
                    overflow: hidden;
                }

                .card-panel-header {
                    background: #faf7f2;
                    padding: 18px 24px;
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .card-panel-header h3 {
                    font-family: 'Cormorant Garamond', serif;
                    font-size: 17px;
                    font-weight: 600;
                    color: var(--stone);
                }

                .card-panel-header p {
                    font-size: 14px;
                    color: var(--muted);
                    margin-top: 1px;
                }

                .card-panel-body {
                    padding: 20px 24px;
                }

                .badge-pill {
                    font-size: 13px;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-weight: 500;
                }

                /* ── BOTTOM ROW ───────────────────────────────────── */
                .bottom-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                }

                /* ── TABLE ────────────────────────────────────────── */
                .custom-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 13px;
                }

                .custom-table th {
                    font-size: 13px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.8px;
                    color: var(--muted);
                    padding: 0 12px 12px;
                    border-bottom: 1px solid var(--border);
                    text-align: left;
                }

                .custom-table td {
                    padding: 12px;
                    border-bottom: 1px solid #f1f5f9;
                    color: var(--stone);
                    vertical-align: middle;
                }

                .custom-table tr:last-child td {
                    border-bottom: none;
                }

                .custom-table tr:hover td {
                    background: #faf7f2;
                }

                /* ── STATUS BADGES ────────────────────────────────── */
                .status-badge {
                    display: inline-block;
                }

                .status-pending {
                    background: var(--tag-amber-bg);
                    color: var(--tag-amber-fg);
                }

                .status-confirmed {
                    background: var(--tag-green-bg);
                    color: var(--tag-green-fg);
                }

                .status-checked_in {
                    background: var(--tag-blue-bg);
                    color: var(--tag-blue-fg);
                }

                .status-cancelled {
                    background: var(--tag-red-bg);
                    color: var(--tag-red-fg);
                }

                .status-checked_out {
                    background: var(--tag-slate-bg);
                    color: var(--tag-slate-fg);
                }

                .pay-paid {
                    background: var(--tag-green-bg);
                    color: var(--tag-green-fg);
                }

                .pay-partial {
                    background: var(--tag-amber-bg);
                    color: var(--tag-amber-fg);
                }

                .pay-unpaid {
                    background: var(--tag-red-bg);
                    color: var(--tag-red-fg);
                }

                .pay-refunded {
                    background: var(--tag-cyan-bg);
                    color: var(--tag-cyan-fg);
                }

                /* ── QUICK ACTIONS ────────────────────────────────── */
                .quick-actions {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .quick-action-btn {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 14px 16px;
                    border-radius: 10px;
                    border: 1px solid var(--border);
                    background: var(--cream);
                    color: var(--stone);
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all .2s;
                }

                .quick-action-btn:hover {
                    background: var(--terracotta);
                    color: white;
                    border-color: var(--terracotta);
                }

                .quick-action-btn .qa-icon {
                    width: 34px;
                    height: 34px;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                    flex-shrink: 0;
                }

                /* ── PROPERTY STATUS LIST ─────────────────────────── */
                .property-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 11px 0;
                    border-bottom: 1px solid var(--border);
                }

                .property-item:last-child {
                    border-bottom: none;
                }

                .prop-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    flex-shrink: 0;
                }

                .dot-available {
                    background: #22c55e;
                }

                .dot-occupied {
                    background: #3b82f6;
                }

                .dot-maintenance {
                    background: #f59e0b;
                }

                /* ── RESPONSIVE ───────────────────────────────────── */
                @media (max-width: 1200px) {
                    .kpi-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (max-width: 768px) {
                    .kpi-grid {
                        grid-template-columns: 1fr 1fr;
                    }

                    .charts-row,
                    .bottom-row {
                        grid-template-columns: minmax(0, 1fr);
                    }

                    .card-panel {
                        overflow-x: auto;
                    }

                    .custom-table {
                        min-width: 520px;
                    }
                }

                @media (max-width: 480px) {
                    .kpi-grid {
                        grid-template-columns: 1fr;
                    }
                }

                /* ── ANIMATIONS ───────────────────────────────────── */
                @keyframes fadeUp {
                    from {
                        opacity: 0;
                        transform: translateY(16px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .kpi-card {
                    animation: fadeUp .4s ease both;
                }

                .kpi-card:nth-child(1) {
                    animation-delay: .05s;
                }

                .kpi-card:nth-child(2) {
                    animation-delay: .10s;
                }

                .kpi-card:nth-child(3) {
                    animation-delay: .15s;
                }

                .kpi-card:nth-child(4) {
                    animation-delay: .20s;
                }

                .kpi-card:nth-child(5) {
                    animation-delay: .25s;
                }

                .kpi-card:nth-child(6) {
                    animation-delay: .30s;
                }

                .kpi-card:nth-child(7) {
                    animation-delay: .35s;
                }

                .kpi-card:nth-child(8) {
                    animation-delay: .40s;
                }

                .charts-row,
                .bottom-row {
                    animation: fadeUp .5s ease .3s both;
                }
            </style>
        @endpush

        @section('content')

            {{-- KPI Cards --}}
            <div class="kpi-grid">

                <div class="kpi-card gold" id="rt-revenue-card">
                    <div class="kpi-label">Revenue Today</div>
                    <div class="kpi-value" id="rt-today-revenue" data-value="{{ $stats['revenue_today'] }}">
                        ₱{{ number_format($stats['revenue_today'], 0) }}</div>
                    <div class="kpi-sub"><i class="bi bi-arrow-up up"></i> <span class="up">Live</span></div>
                    <i class="bi bi-cash-coin kpi-icon"></i>
                </div>

                <div class="kpi-card teal">
                    <div class="kpi-label">Revenue This Month</div>
                    <div class="kpi-value">₱{{ number_format($stats['revenue_this_month'], 0) }}</div>
                    <div class="kpi-sub"><i class="bi bi-calendar3"></i> {{ now()->format('F Y') }}</div>
                    <i class="bi bi-graph-up kpi-icon"></i>
                </div>

                <div class="kpi-card navy" id="rt-bookings-card">
                    <div class="kpi-label">Total Bookings</div>
                    <div class="kpi-value" id="rt-total-bookings">{{ number_format($stats['total_bookings']) }}</div>
                    <div class="kpi-sub"><i class="bi bi-calendar-check"></i> All time</div>
                    <i class="bi bi-calendar2-week kpi-icon"></i>
                </div>

                <div class="kpi-card orange">
                    <div class="kpi-label">Pending Bookings</div>
                    <div class="kpi-value" id="rt-pending-count">{{ $stats['pending_bookings'] }}</div>
                    <div class="kpi-sub"><i class="bi bi-clock"></i> Awaiting confirmation</div>
                    <i class="bi bi-hourglass-split kpi-icon"></i>
                </div>

                <div class="kpi-card sky">
                    <div class="kpi-label">Total Guests</div>
                    <div class="kpi-value">{{ number_format($stats['total_guests']) }}</div>
                    <div class="kpi-sub"><i class="bi bi-people"></i> Registered accounts</div>
                    <i class="bi bi-person-hearts kpi-icon"></i>
                </div>

                <div class="kpi-card purple guest-name-card" id="rt-guest-card">
                    <div class="kpi-label">Current Guest</div>
                    <div class="kpi-value" title="{{ $stats['current_guest']->user->full_name ?? '' }}">
                        {{ $stats['current_guest']->user->full_name ?? 'Vacant' }}
                    </div>
                    <div class="kpi-sub"><i class="bi bi-house-check"></i>
                        {{ $stats['current_guest'] ? 'Checked in · out ' . $stats['current_guest']->check_out_date->format('M d, g:iA') : 'No guest checked in' }}
                    </div>
                    <i class="bi bi-person-check kpi-icon"></i>
                </div>

            </div>

            {{-- Charts Row --}}
            <div class="charts-row">

                {{-- Revenue Chart --}}
                <div class="card-panel">
                    <div class="card-panel-header">
                        <div>
                            <h3>Revenue Overview</h3>
                            <p>Monthly revenue for the past 6 months</p>
                        </div>
                        <span class="badge-pill" style="background:#dcfce7; color:#15803d;">
                            <i class="bi bi-circle-fill" style="font-size:7px"></i> Live
                        </span>
                    </div>
                    <div class="card-panel-body">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>

                {{-- Booking Sources Pie --}}
                <div class="card-panel">
                    <div class="card-panel-header">
                        <div>
                            <h3>Booking Sources</h3>
                            <p>Where bookings come from</p>
                        </div>
                    </div>
                    <div class="card-panel-body">
                        <canvas id="sourceChart" height="180"></canvas>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; justify-content:center;">
                            <span class="badge-pill" style="background:#e0f2fe; color:#0369a1;">🌐 Online</span>
                            <span class="badge-pill" style="background:#dcfce7; color:#15803d;">🚶 Walk-in</span>
                            <span class="badge-pill" style="background:#fef9c3; color:#a16207;">📞 Phone</span>
                            <span class="badge-pill" style="background:#f3e8ff; color:#7c3aed;">🤝 Partner</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Bottom Row --}}
            <div class="bottom-row">

                {{-- Recent Bookings --}}
                <div class="card-panel">
                    <div class="card-panel-header">
                        <div>
                            <h3>Recent Bookings</h3>
                            <p>Latest 5 reservations</p>
                        </div>
                        <a href="{{ route('admin.bookings.index') }}"
                            style="font-size: 14px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                            View all <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-panel-body" style="padding: 0 24px;">
                        @php
                            $recentBookings = \App\Models\Booking::with(['user', 'property'])
                                ->latest()
                                ->take(5)
                                ->get();
                        @endphp

                        @if ($recentBookings->isEmpty())
                            <div style="text-align:center; padding:40px 0; color:#94a3b8;">
                                <i class="bi bi-calendar-x" style="font-size:36px; display:block; margin-bottom:8px;"></i>
                                No bookings yet
                            </div>
                        @else
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Guest</th>
                                        <th>Property</th>
                                        <th>Check-in</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentBookings as $booking)
                                        <tr>
                                            <td>
                                                <div class="fw-medium">{{ $booking->user->full_name ?? 'N/A' }}</div>
                                                <div style="font-size: 13px; color:#94a3b8;">{{ $booking->booking_ref }}
                                                </div>
                                            </td>
                                            <td style="font-size:13px;">{{ $booking->property->property_name ?? 'N/A' }}
                                            </td>
                                            <td style="font-size: 14px; color:#64748b;">
                                                {{ $booking->check_in_date->format('M d, Y') }}</td>
                                            <td>
                                                <span class="status-badge status-{{ $booking->status }}">
                                                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                {{-- Right Column: Property Status + Quick Actions --}}
                <div style="display:flex; flex-direction:column; gap:20px;">

                    {{-- Property Status --}}
                    <div class="card-panel">
                        <div class="card-panel-header">
                            <div>
                                <h3>Property Status</h3>
                                <p>Villa & room housekeeping status</p>
                            </div>
                            <a href="{{ route('admin.properties.index') }}"
                                style="font-size: 14px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                                Manage <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-panel-body">
                            @php
                                $properties = \App\Models\Property::take(5)->get();
                            @endphp

                            @if ($properties->isEmpty())
                                <div style="text-align:center; padding:20px 0; color:#94a3b8; font-size:13px;">
                                    <i class="bi bi-house-x"
                                        style="font-size:28px; display:block; margin-bottom:8px;"></i>
                                    No properties yet
                                </div>
                            @else
                                @foreach ($properties as $property)
                                    <div class="property-item">
                                        <div class="d-flex-gap-10">
                                            <div class="prop-dot dot-{{ $property->status }}"></div>
                                            <div>
                                                <div style="font-size:13px; font-weight:500;">
                                                    {{ $property->property_name }}</div>
                                                <div style="font-size: 13px; color:#94a3b8;">{{ ucfirst($property->type) }}
                                                    · {{ $property->max_capacity }} guests</div>
                                            </div>
                                        </div>
                                        <span class="status-badge status-{{ $property->status }}">
                                            {{ ucfirst($property->status) }}
                                        </span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="card-panel">
                        <div class="card-panel-header">
                            <div>
                                <h3>Quick Actions</h3>
                            </div>
                        </div>
                        <div class="card-panel-body">
                            <div class="quick-actions">
                                <a href="{{ route('admin.bookings.create') }}" class="quick-action-btn">
                                    <div class="qa-icon" style="background:#dbeafe; color:#1d4ed8;">
                                        <i class="bi bi-plus-circle"></i>
                                    </div>
                                    New Booking
                                </a>
                                <a href="{{ route('admin.properties.create') }}" class="quick-action-btn">
                                    <div class="qa-icon" style="background:#dcfce7; color:#15803d;">
                                        <i class="bi bi-house-add"></i>
                                    </div>
                                    Add Property
                                </a>
                                <a href="{{ route('admin.reports.index') }}" class="quick-action-btn">
                                    <div class="qa-icon" style="background:#fef9c3; color:#a16207;">
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                    </div>
                                    Generate Report
                                </a>
                                <a href="{{ route('admin.reviews.index') }}" class="quick-action-btn">
                                    <div class="qa-icon" style="background:#f3e8ff; color:#7c3aed;">
                                        <i class="bi bi-star-half"></i>
                                    </div>
                                    Moderate Reviews
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        @endsection

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // ── Revenue Chart ────────────────────────────────────────────
                    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                    new Chart(revenueCtx, {
                        type: 'bar',
                        data: {
                            labels: @json(collect($stats['revenue_by_month'])->pluck('label')),
                            datasets: [{
                                label: 'Revenue (₱)',
                                data: @json(collect($stats['revenue_by_month'])->pluck('amount')),
                                backgroundColor: 'rgba(196,103,58,0.20)',
                                borderColor: '#c4673a',
                                borderWidth: 2,
                                borderRadius: 6,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ' ₱' + ctx.parsed.y.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#ece4d8'
                                    },
                                    ticks: {
                                        callback: v => '₱' + v.toLocaleString(),
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: {
                                            size: 12
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // ── Booking Sources Chart ────────────────────────────────────
                    const sourceCtx = document.getElementById('sourceChart').getContext('2d');
                    new Chart(sourceCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Online', 'Walk-in', 'Phone', 'Partner'],
                            datasets: [{
                                data: [
                                    {{ $stats['booking_sources']['online'] }},
                                    {{ $stats['booking_sources']['walk_in'] }},
                                    {{ $stats['booking_sources']['phone'] }},
                                    {{ $stats['booking_sources']['partner'] }}
                                ],
                                backgroundColor: ['#c4673a', '#b8943f', '#d4aa5a', '#8b6b43'],
                                borderWidth: 0,
                                hoverOffset: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' booking' + (ctx
                                            .parsed === 1 ? '' : 's')
                                    }
                                }
                            }
                        }
                    });
                });
            </script>
        @endpush

