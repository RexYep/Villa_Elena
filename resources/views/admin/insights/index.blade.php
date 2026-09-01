@extends('layouts.admin')

@section('title', 'Insights — Villa Elena Resort')
@section('page-title', 'Insights')
@section('page-subtitle', 'Powered by Google Gemini — ' . now()->format('F d, Y'))

@section('topbar-right')
    <a href="{{ route('admin.insights.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-clockwise"></i> Refresh Insights
    </a>
@endsection

@push('styles')
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--cream);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .kpi-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1;
        }

        .kpi-sub {
            font-size: 14px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* AI CARD */
        .ai-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .ai-card-header {
            background: var(--stone);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ai-card-header .title {
            font-family: 'Cormorant Garamond', serif;
            color: var(--cream);
            font-size: 18px;
            font-weight: 600;
        }

        .ai-badge {
            margin-left: auto;
            background: var(--gold);
            color: var(--stone);
            font-size: 13px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        .ai-card-body {
            padding: 28px 32px;
            background: #fafaf8;
        }

        .ai-insights-text {
            white-space: pre-wrap;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            line-height: 1.9;
            color: var(--text-main);
        }

        .ai-card-footer {
            padding: 14px 24px;
            border-top: 1px solid var(--border);
            font-size: 14px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-refresh {
            background: var(--gold);
            color: var(--stone);
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }

        .btn-refresh:hover {
            opacity: 0.85;
            color: var(--stone);
        }

        @media (max-width: 900px) {
            .kpi-grid {
                grid-template-columns: 1fr 1fr;
            }

            .ai-card-body {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .ai-card-header {
                flex-wrap: wrap;
            }

            .ai-badge {
                margin-left: 0;
            }
        }
    </style>
@endpush

@section('content')

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Bookings</div>
            <div class="kpi-value">{{ $totalBookings }}</div>
            <div class="kpi-sub" style="color:#f59e0b;">{{ $pendingBookings }} pending</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Revenue This Month</div>
            <div class="kpi-value" style="font-size:22px;">₱{{ number_format($revenueThisMonth, 2) }}</div>
            <div class="kpi-sub">Last month: ₱{{ number_format($revenueLastMonth, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Villa Status</div>
            <div class="kpi-value">{{ $checkedIn > 0 ? 'Occupied' : 'Available' }}</div>
            <div class="kpi-sub">{{ $checkedIn > 0 ? 'Guest currently checked in' : 'No guest checked in' }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">New Guests This Month</div>
            <div class="kpi-value">{{ $newGuestsThisMonth }}</div>
            <div class="kpi-sub">of {{ $totalGuests }} total guests</div>
        </div>
    </div>

    {{-- AI Insights Box --}}
    <div class="ai-card">
        <div class="ai-card-header">
            <i class="bi bi-bar-chart-steps fs-5" style="color:var(--gold);"></i>
            <span class="title">Smart Data Insights</span>
            <span class="ai-badge">AUTO</span>
        </div>
        <div class="ai-card-body">
            @php
                $lines = array_filter(explode("\n", trim($insights)));
            @endphp
            <div style="display: flex; flex-direction: column; gap: 12px;">
                @foreach ($lines as $line)
                    @if (trim($line))
                        <div
                            style="
                        display: flex;
                        align-items: flex-start;
                        gap: 14px;
                        background: #fff;
                        border: 1px solid var(--border);
                        border-left: 4px solid var(--gold);
                        border-radius: 10px;
                        padding: 14px 18px;
                        font-size: 14.5px;
                        color: var(--text-main);
                        line-height: 1.5;
                    ">
                            {{ trim($line) }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="ai-card-footer">
            <i class="bi bi-info-circle"></i>
            Automatically interpreted from your current resort data — not a prediction.
        </div>
    </div>

@endsection

