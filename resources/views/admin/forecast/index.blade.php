<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Forecasting — Villa Elena Resort</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
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
        body { font-family: 'DM Sans', sans-serif; background: var(--off-white); color: var(--text-main); overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--navy);
            display: flex; flex-direction: column;
            z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .sidebar-brand h1 { font-family: 'Cormorant Garamond', serif; color: var(--gold-light); font-size: 22px; font-weight: 700; }
        .sidebar-brand p { color: rgba(255,255,255,0.35); font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 3px; }
        .sidebar-section { padding: 20px 16px 8px; }
        .sidebar-section-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.25); padding: 0 8px; margin-bottom: 6px; }
        .nav-item-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 14px; transition: all .2s; margin-bottom: 2px; }
        .nav-item-custom:hover { background: rgba(255,255,255,0.07); color: var(--white); }
        .nav-item-custom.active { background: var(--gold-dim); color: var(--gold-light); font-weight: 500; }
        .nav-icon { width: 32px; height: 32px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; background: rgba(255,255,255,0.05); }
        .nav-item-custom.active .nav-icon { background: var(--gold-dim); color: var(--gold); }
        .sidebar-footer { margin-top: auto; padding: 16px; border-top: 1px solid rgba(255,255,255,0.07); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,0.05); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gold-dim); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 600; }
        .user-info .name { color: var(--white); font-size: 13px; font-weight: 500; }
        .user-info .role-badge { font-size: 10px; color: var(--gold); text-transform: uppercase; }

        /* TOPBAR */
        .topbar { position: fixed; top: 0; left: var(--sidebar-w); right: 0; height: var(--topbar-h); background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 900; }
        .topbar-left h2 { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; }
        .topbar-left p { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        /* MAIN */
        main { margin-left: var(--sidebar-w); padding-top: var(--topbar-h); min-height: 100vh; padding-bottom: 40px; }
        .page-content { padding: 32px; }

        /* CARDS */
        .panel { background: var(--white); border-radius: 14px; border: 1px solid var(--border); box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; }
        .panel-header { background: var(--navy); padding: 18px 24px; display: flex; align-items: center; gap: 12px; }
        .panel-header .title { font-family: 'Cormorant Garamond', serif; color: var(--white); font-size: 18px; font-weight: 600; }
        .panel-badge { margin-left: auto; background: var(--gold); color: var(--navy); font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
        .panel-body { padding: 28px 32px; background: #fafaf8; }

        /* CHART SECTION */
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .chart-panel { background: var(--white); border-radius: 14px; border: 1px solid var(--border); padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .chart-panel h3 { font-family: 'Cormorant Garamond', serif; font-size: 16px; font-weight: 600; color: var(--navy); margin-bottom: 4px; }
        .chart-panel p { font-size: 12px; color: var(--text-muted); margin-bottom: 20px; }

        .btn-refresh { background: var(--gold); color: var(--navy); border: none; padding: 9px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: opacity .2s; }
        .btn-refresh:hover { opacity: 0.85; color: var(--navy); }

        .forecast-text { white-space: pre-wrap; font-family: 'DM Sans', sans-serif; font-size: 15px; line-height: 1.9; color: var(--text-main); }
    </style>
</head>
<body>

{{-- SIDEBAR --}}
@include('admin.partials.sidebar')


{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <h2>AI Forecasting</h2>
        <p>Revenue & Occupancy Predictions — {{ now()->format('F d, Y') }}</p>
    </div>
    <a href="{{ route('admin.forecast.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-clockwise"></i> Refresh Forecast
    </a>
</div>

{{-- MAIN --}}
<main>
    <div class="page-content">

        {{-- Charts --}}
        <div class="chart-grid">
            <div class="chart-panel">
                <h3>Historical Bookings</h3>
                <p>Number of bookings per month (last 6 months)</p>
                <canvas id="bookingsChart" height="120"></canvas>
            </div>
            <div class="chart-panel">
                <h3>Historical Revenue</h3>
                <p>Revenue in PHP per month (last 6 months)</p>
                <canvas id="revenueChart" height="120"></canvas>
            </div>
        </div>

        {{-- AI Forecast --}}
        <div class="panel">
            <div class="panel-header">
                <i class="bi bi-graph-up-arrow fs-5" style="color:var(--gold);"></i>
                <span class="title">AI Forecast — Next 3 Months</span>
                <span class="panel-badge">LIVE</span>
            </div>
            <div class="panel-body">
                <div class="forecast-text">{{ $forecast }}</div>
            </div>
            <div style="padding: 14px 24px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-muted);">
                <i class="bi bi-info-circle me-1"></i>
                Forecast is AI-generated based on your historical booking and revenue data. Use as a guide only.
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
                backgroundColor: 'rgba(201,168,76,0.2)',
                borderColor: '#c9a84c',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
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
                backgroundColor: 'rgba(13,27,42,0.08)',
                borderColor: '#0d1b2a',
                borderWidth: 2,
                pointBackgroundColor: '#c9a84c',
                pointRadius: 5,
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString() } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
            }
        }
    });
</script>
@include('admin.partials.realtime') 
</body>
</html>