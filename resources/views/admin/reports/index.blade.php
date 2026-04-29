<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}

        /* Period Tabs */
        .period-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;align-items:center;}
        .period-btn{padding:7px 16px;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;border:1.5px solid var(--border);color:var(--text-muted);background:#fff;transition:all .2s;cursor:pointer;}
        .period-btn:hover{border-color:var(--navy-mid);color:var(--navy);}
        .period-btn.active{background:var(--navy);color:#fff;border-color:var(--navy);}
        .custom-range{display:flex;gap:8px;align-items:center;margin-left:8px;}
        .date-input{border:1.5px solid var(--border);border-radius:8px;padding:7px 10px;font-size:12px;font-family:'DM Sans',sans-serif;}

        /* KPI Grid */
        .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
        .kpi-card{background:#fff;border-radius:14px;border:1px solid var(--border);padding:20px 22px;position:relative;overflow:hidden;}
        .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .kpi-green::before{background:linear-gradient(90deg,#16a34a,#4ade80);}
        .kpi-blue::before{background:linear-gradient(90deg,#1d4ed8,#60a5fa);}
        .kpi-gold::before{background:linear-gradient(90deg,#c9a84c,#e8c97a);}
        .kpi-purple::before{background:linear-gradient(90deg,#7c3aed,#c084fc);}
        .kpi-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px;}
        .kpi-val{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;line-height:1;color:var(--text-main);}
        .kpi-label{font-size:12px;color:var(--text-muted);margin-top:4px;}
        .kpi-sub{font-size:11px;margin-top:6px;}

        /* Charts */
        .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
        .chart-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;}
        .chart-header{padding:16px 22px;border-bottom:1px solid var(--border);}
        .chart-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .chart-header p{font-size:12px;color:var(--text-muted);margin-top:2px;}
        .chart-body{padding:20px;position:relative;}
        .chart-full{grid-column:1/-1;}

        /* Table */
        .table-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .table-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .table-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        table{width:100%;border-collapse:collapse;font-size:13px;}
        th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);padding:11px 20px;border-bottom:1px solid var(--border);text-align:left;}
        td{padding:13px 20px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}

        /* Donut legend */
        .legend-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;}
        .legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

        /* Progress bar */
        .progress-bar-wrap{background:#f1f5f9;border-radius:100px;height:6px;overflow:hidden;margin-top:4px;}
        .progress-bar-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--navy),#2563eb);}

        /* Period label */
        .period-label{font-size:12px;color:var(--text-muted);margin-bottom:20px;}
        .period-label strong{color:var(--text-main);}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Reports & Analytics</h2>
        <p>Revenue, occupancy, and booking insights</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
</header>

<main class="main-content">

    {{-- Period Selector --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" id="periodForm">
        <div class="period-bar">
            @foreach(['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year'] as $val=>$label)
                <button type="submit" name="period" value="{{ $val }}"
                    class="period-btn {{ $period === $val ? 'active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
            <div class="custom-range">
                <input type="date" name="from" class="date-input"
                    value="{{ $period === 'custom' ? $from->toDateString() : '' }}"
                    placeholder="From">
                <span style="color:var(--text-muted);font-size:13px;">—</span>
                <input type="date" name="to" class="date-input"
                    value="{{ $period === 'custom' ? $to->toDateString() : '' }}"
                    placeholder="To">
                <button type="submit" name="period" value="custom"
                    class="period-btn {{ $period === 'custom' ? 'active' : '' }}">
                    Apply
                </button>
            </div>
        </div>
    </form>

    <div class="period-label">
        Showing data from <strong>{{ $from->format('M d, Y') }}</strong>
        to <strong>{{ $to->format('M d, Y') }}</strong>
    </div>

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-cash-stack"></i></div>
            <div class="kpi-val">₱{{ number_format($netRevenue, 0) }}</div>
            <div class="kpi-label">Net Revenue</div>
            @if($totalRefunds > 0)
            <div class="kpi-sub" style="color:#dc2626;">-₱{{ number_format($totalRefunds, 0) }} refunds</div>
            @endif
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-calendar-check"></i></div>
            <div class="kpi-val">{{ $confirmedBookings }}</div>
            <div class="kpi-label">Confirmed Bookings</div>
            @if($cancelledBookings > 0)
            <div class="kpi-sub" style="color:#dc2626;">{{ $cancelledBookings }} cancelled</div>
            @endif
        </div>
        <div class="kpi-card kpi-gold">
            <div class="kpi-icon" style="background:var(--gold-dim);color:var(--gold);"><i class="bi bi-percent"></i></div>
            <div class="kpi-val">{{ $occupancyRate }}%</div>
            <div class="kpi-label">Occupancy Rate</div>
            <div class="progress-bar-wrap" style="margin-top:8px;">
                <div class="progress-bar-fill" style="width:{{ min(100,$occupancyRate) }}%;background:linear-gradient(90deg,var(--gold),var(--gold-light));"></div>
            </div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-val">₱{{ number_format($avgBookingValue, 0) }}</div>
            <div class="kpi-label">Avg. Booking Value</div>
            <div class="kpi-sub" style="color:var(--text-muted);">{{ $newGuests }} new guests</div>
        </div>
    </div>

    {{-- Revenue Over Time (full width) --}}
    <div class="table-card chart-full" style="margin-bottom:20px;">
        <div class="chart-header" style="padding:16px 22px;border-bottom:1px solid var(--border);">
            <h3>Revenue — Last 12 Months</h3>
            <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Monthly collected payments (excl. refunds)</p>
        </div>
        <div style="padding:20px 24px;">
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>

    {{-- Bookings Trend + Sources --}}
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Trend</h3>
                <p>Confirmed bookings per month</p>
            </div>
            <div class="chart-body">
                <canvas id="bookingsChart" height="160"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Sources</h3>
                <p>Where bookings come from — period</p>
            </div>
            <div class="chart-body" style="display:flex;align-items:center;gap:24px;">
                <canvas id="sourceChart" style="max-width:160px;max-height:160px;"></canvas>
                <div id="sourceLegend"></div>
            </div>
        </div>
    </div>

    {{-- Daily Revenue + Status Breakdown --}}
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Daily Revenue</h3>
                <p>Payments collected per day — selected period</p>
            </div>
            <div class="chart-body">
                <canvas id="dailyChart" height="160"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Status Mix</h3>
                <p>Breakdown by status — selected period</p>
            </div>
            <div class="chart-body" style="display:flex;align-items:center;gap:24px;">
                <canvas id="statusChart" style="max-width:160px;max-height:160px;"></canvas>
                <div id="statusLegend"></div>
            </div>
        </div>
    </div>

    {{-- Top Properties Table --}}
    <div class="table-card">
        <div class="table-header">
            <h3>Top Properties by Revenue</h3>
            <span style="font-size:12px;color:var(--text-muted);">Selected period</span>
        </div>
        @if($topProperties->isEmpty())
            <div style="text-align:center;padding:40px;color:var(--text-muted);font-size:13px;">
                No booking data for this period.
            </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Property</th>
                    <th>Bookings</th>
                    <th>Revenue</th>
                    <th>Avg / Booking</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                @php $maxRev = $topProperties->max('revenue') ?: 1; @endphp
                @foreach($topProperties as $i => $row)
                <tr>
                    <td style="font-weight:700;color:var(--text-muted);">{{ $i+1 }}</td>
                    <td style="font-weight:600;">{{ $row->property->property_name ?? 'N/A' }}</td>
                    <td style="text-align:center;">{{ $row->bookings }}</td>
                    <td style="font-weight:600;color:#15803d;">₱{{ number_format($row->revenue, 2) }}</td>
                    <td style="color:var(--text-muted);">₱{{ number_format($row->bookings > 0 ? $row->revenue / $row->bookings : 0, 2) }}</td>
                    <td style="width:140px;">
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">
                            {{ round(($row->revenue / ($netRevenue ?: 1)) * 100, 1) }}%
                        </div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:{{ ($row->revenue/$maxRev)*100 }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const NAVY   = '#0d1b2a';
const GOLD   = '#c9a84c';
const BLUE   = '#1d4ed8';
const GREEN  = '#16a34a';
const RED    = '#dc2626';
const PURPLE = '#7c3aed';
const SLATE  = '#475569';
const ORANGE = '#ea580c';

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#6b7a8d';

// Revenue Bar Chart
const revData = @json($revenueByMonth);
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: revData.map(d => d.label),
        datasets: [{
            label: 'Revenue (₱)',
            data: revData.map(d => d.amount),
            backgroundColor: revData.map((d, i) => i === revData.length - 1 ? GOLD : NAVY + 'cc'),
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// Bookings Line Chart
const bkData = @json($bookingsByMonth);
new Chart(document.getElementById('bookingsChart'), {
    type: 'line',
    data: {
        labels: bkData.map(d => d.label),
        datasets: [{
            label: 'Bookings',
            data: bkData.map(d => d.count),
            borderColor: BLUE,
            backgroundColor: BLUE + '18',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: BLUE,
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// Booking Source Donut
const srcRaw   = @json($bookingsBySource);
const srcLabels = Object.keys(srcRaw).map(k => k.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase()));
const srcData   = Object.values(srcRaw);
const srcColors = [NAVY, GOLD, BLUE, GREEN, PURPLE, ORANGE];
new Chart(document.getElementById('sourceChart'), {
    type: 'doughnut',
    data: { labels: srcLabels, datasets: [{ data: srcData, backgroundColor: srcColors, borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: false, plugins: { legend: { display: false } }, cutout: '65%' }
});
const srcLegendEl = document.getElementById('sourceLegend');
srcLabels.forEach((l, i) => {
    srcLegendEl.innerHTML += `<div class="legend-row"><div class="legend-dot" style="background:${srcColors[i]}"></div><span>${l}: <strong>${srcData[i]}</strong></span></div>`;
});

// Daily Revenue Bar
const dailyRaw = @json($dailyRevenue);
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: dailyRaw.map(d => d.label),
        datasets: [{
            label: 'Revenue (₱)',
            data: dailyRaw.map(d => d.amount),
            backgroundColor: GREEN + 'cc',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
        }
    }
});

// Status Donut
const stRaw    = @json($bookingsByStatus);
const stColors = { pending: GOLD, confirmed: GREEN, checked_in: BLUE, checked_out: SLATE, cancelled: RED, no_show: '#1e293b' };
const stLabels = Object.keys(stRaw).map(k => k.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase()));
const stData   = Object.values(stRaw);
const stColArr = Object.keys(stRaw).map(k => stColors[k] || SLATE);
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: stLabels, datasets: [{ data: stData, backgroundColor: stColArr, borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: false, plugins: { legend: { display: false } }, cutout: '65%' }
});
const stLegendEl = document.getElementById('statusLegend');
stLabels.forEach((l, i) => {
    stLegendEl.innerHTML += `<div class="legend-row"><div class="legend-dot" style="background:${stColArr[i]}"></div><span>${l}: <strong>${stData[i]}</strong></span></div>`;
});
</script>
@include('admin.partials.realtime') 
</body>
</html>