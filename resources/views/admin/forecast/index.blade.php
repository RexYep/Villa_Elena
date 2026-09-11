@extends('layouts.admin')

@section('title', 'Forecasting — Villa Elena Resort')
@section('page-title', 'Forecasting')
@section('page-subtitle', 'Revenue & Occupancy Predictions — ' . now()->format('F d, Y'))

@section('topbar-right')
    <a href="{{ route('admin.forecast.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-clockwise"></i> Refresh Forecast
    </a>
@endsection

@push('styles')
    @vite(['resources/js/admin-charts.js'])
    <style>
        /* CARDS */
        .panel,
        .chart-panel {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            overflow: hidden;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .chart-panel {
            padding: 24px;
        }

        .chart-panel h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .chart-panel p {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .panel-header {
            background: var(--terracotta);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .panel-header .title {
            font-family: 'Cormorant Garamond', serif;
            color: #fff;
            font-size: 18px;
            font-weight: 600;
        }

        .panel-badge {
            margin-left: auto;
            background: var(--gold);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .panel-body {
            padding: 28px 32px;
            background: var(--sand);
        }

        .forecast-text {
            font-size: 15px;
            line-height: 1.8;
            color: var(--stone);
            /* Model output — an unbroken token (a URL, a reference code) would
               otherwise set the min-content width inside an overflow:hidden
               panel and be clipped. */
            overflow-wrap: anywhere;
        }

        .forecast-text>*:first-child {
            margin-top: 0;
        }

        .forecast-text>*:last-child {
            margin-bottom: 0;
        }

        .forecast-text h1,
        .forecast-text h2,
        .forecast-text h3 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--terracotta);
            font-weight: 700;
            margin: 28px 0 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .forecast-text h1 {
            font-size: 22px;
        }

        .forecast-text h2 {
            font-size: 20px;
        }

        .forecast-text h3 {
            font-size: 18px;
        }

        .forecast-text p {
            margin: 0 0 14px;
        }

        .forecast-text strong {
            color: var(--stone);
            font-weight: 700;
        }

        .forecast-text ul,
        .forecast-text ol {
            margin: 0 0 16px;
            padding-left: 22px;
        }

        .forecast-text li {
            margin-bottom: 6px;
        }

        .forecast-text table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 20px;
            font-size: 14px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        .forecast-text th,
        .forecast-text td {
            padding: 10px 14px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .forecast-text th {
            background: var(--terracotta);
            color: #fff;
            font-weight: 600;
        }

        .forecast-text tr:nth-child(even) td {
            background: #fafaf8;
        }

        .btn-refresh {
            background: var(--terracotta);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .2s;
        }

        .btn-refresh:hover {
            background: #b95a31;
            color: #fff;
        }

        /* Charts were sized by aspect ratio (canvas height="120" on a 300px
           default width = 2.5:1), so their height tracked their width. When the
           grid went two-up the charts got *shorter* on a wider screen — 810x324
           at 900px, 273x109 at 993px, where the plot area itself was 51px tall
           and the month labels were rotated 28deg. On a 320px phone the plot
           area was 39px. A fixed-height box with maintainAspectRatio:false keeps
           the plot readable at every width. */
        .chart-box {
            position: relative;
            height: 240px;
        }

        /* Two-up only once each chart is wide enough that the six month labels
           sit flat: the bookings axis still rotated at 1150px (351px canvas) and
           stopped at 1200px (376px). The old 900px breakpoint put two starved
           charts side by side through the whole 993-1199px band. */
        @media (max-width: 1199px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .panel-body {
                padding: 20px;
            }

            .panel-header {
                flex-wrap: wrap;
            }

            .panel-badge {
                margin-left: 0;
            }
        }

        /* "Refresh Forecast" wraps to two lines at 390px and below, making the
           button 57px tall inside a topbar admin.css fixes at 68px. Same
           icon-only treatment as insights, calendar and properties. */
        @media (max-width: 560px) {
            .topbar-right .btn-refresh {
                font-size: 0;
                padding: 9px 12px;
                gap: 0;
            }

            .topbar-right .btn-refresh i {
                font-size: 15px;
            }
        }

        /* Keyed on pointer type, not width: only a finger needs the bigger
           target. */
        @media (hover: none) and (pointer: coarse) {

            .btn-refresh,
            .logout-btn {
                min-height: 44px;
            }

            .topbar-right .btn-refresh {
                min-width: 44px;
                justify-content: center;
            }
        }
    </style>
@endpush

@section('content')

    {{-- Charts --}}
    <div class="chart-grid">
        <div class="chart-panel">
            <h3>Historical Bookings</h3>
            <p>Number of bookings per month (last 6 months)</p>
            <div class="chart-box"><canvas id="bookingsChart"></canvas></div>
        </div>
        <div class="chart-panel">
            <h3>Historical Revenue</h3>
            <p>Revenue in PHP per month (last 6 months)</p>
            <div class="chart-box"><canvas id="revenueChart"></canvas></div>
        </div>
    </div>

    {{-- Forecast --}}
    <div class="panel">
        <div class="panel-header">
            <i class="bi bi-graph-up-arrow fs-5" style="color:var(--gold);"></i>
            <span class="title">Forecast — Next 3 Months</span>
            <span class="panel-badge">LIVE</span>
        </div>
        <div class="panel-body">
            <div class="forecast-text">{!! $forecastHtml !!}</div>
        </div>
        <div class="text-muted-theme" style="padding: 14px 24px; border-top: 1px solid var(--border); font-size: 14px;">
            <i class="bi bi-info-circle me-1"></i>
            Forecast is generated based on your historical booking and revenue data. Use as a guide only.
            This page is the <strong>outlook</strong> — for what to actually do about it, with the peso value of each
            option worked out, see <a href="{{ route('admin.prescriptive.index') }}">Recommendations</a>.
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const months = @json($months);
            const bookingsData = @json(array_column($historicalData, 'bookings'));
            const revenueData = @json(array_column($historicalData, 'revenue'));

            // Bookings Chart
            new Chart(document.getElementById('bookingsChart'), {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Bookings',
                        data: bookingsData,
                        backgroundColor: 'rgba(196,103,58,0.2)',
                        borderColor: '#c4673a',
                        borderWidth: 2,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            },
                            ticks: {
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
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // Revenue Chart
            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: revenueData,
                        backgroundColor: 'rgba(180,148,63,0.15)',
                        borderColor: '#b8943f',
                        borderWidth: 2,
                        pointBackgroundColor: '#c4673a',
                        pointRadius: 5,
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
                                color: '#f1f5f9'
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
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush

