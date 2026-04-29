<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Smart Insights — Villa Elena Resort</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
            --navy-light: #243b55;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dim:   rgba(201,168,76,0.15);
            --white:      #ffffff;
            --off-white:  #f4f6f9;
            --text-main:  #1a2f45;
            --text-muted: #6b7a8d;
            --border:     #e2e8f0;
            --sidebar-w:  260px;
            --topbar-h:   68px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--off-white);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .sidebar-brand h1 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--gold-light);
            font-size: 22px;
            font-weight: 700;
        }

        .sidebar-brand p {
            color: rgba(255,255,255,0.35);
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .sidebar-section { padding: 20px 16px 8px; }

        .sidebar-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .nav-item-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all .2s;
            margin-bottom: 2px;
        }

        .nav-item-custom:hover {
            background: rgba(255,255,255,0.07);
            color: var(--white);
        }

        .nav-item-custom.active {
            background: var(--gold-dim);
            color: var(--gold-light);
            font-weight: 500;
        }

        .nav-icon {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
            background: rgba(255,255,255,0.05);
        }

        .nav-item-custom.active .nav-icon {
            background: var(--gold-dim);
            color: var(--gold);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }

        .user-card {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
        }

        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--gold-dim);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 600; flex-shrink: 0;
        }

        .user-info .name { color: var(--white); font-size: 13px; font-weight: 500; }
        .user-info .role-badge { font-size: 10px; color: var(--gold); text-transform: uppercase; }

        /* TOPBAR */
        .topbar {
            position: fixed;
            top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px;
            z-index: 900;
        }

        .topbar-left h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px; font-weight: 600;
            color: var(--text-main);
        }

        .topbar-left p { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        /* MAIN */
        main {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .page-content { padding: 32px; }

        /* KPI CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--white);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        .kpi-label { font-size: 12px; color: var(--text-muted); font-weight: 500; margin-bottom: 6px; }
        .kpi-value { font-size: 28px; font-weight: 700; color: var(--navy); line-height: 1; }
        .kpi-sub { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

        /* AI CARD */
        .ai-card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .ai-card-header {
            background: var(--navy);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ai-card-header .title {
            font-family: 'Cormorant Garamond', serif;
            color: var(--white);
            font-size: 18px;
            font-weight: 600;
        }

        .ai-badge {
            margin-left: auto;
            background: var(--gold);
            color: var(--navy);
            font-size: 11px;
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
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-refresh {
            background: var(--gold);
            color: var(--navy);
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

        .btn-refresh:hover { opacity: 0.85; color: var(--navy); }
    </style>
</head>
<body>

{{-- SIDEBAR --}}
@include('admin.partials.sidebar')


{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <h2>AI Smart Insights</h2>
        <p>Powered by Google Gemini — {{ now()->format('F d, Y') }}</p>
    </div>
    <a href="{{ route('admin.insights.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-clockwise"></i> Refresh Insights
    </a>
</div>

{{-- MAIN CONTENT --}}
<main>
    <div class="page-content">

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
                <div class="kpi-label">Occupancy Rate</div>
                <div class="kpi-value">{{ $occupancyRate }}%</div>
                <div class="kpi-sub">of all properties</div>
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
            @foreach($lines as $line)
                @if(trim($line))
                <div style="
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
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('admin.partials.realtime') 
</body>
</html>