@extends('layouts.admin')

@section('title', 'Reports & Analytics — Villa Elena Admin')
@section('page-title', 'Reports & Analytics')
@section('page-subtitle', 'Revenue, occupancy, and booking insights')

@push('styles')
@vite(['resources/js/admin-charts.js'])
<style>
/* Period Tabs */
.period-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;align-items:center;}
.period-btn{padding:7px 16px;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;border:1.5px solid var(--border);color:var(--muted);background:#fff;transition:all .2s;cursor:pointer;}
.period-btn:hover{border-color:var(--terracotta);color:var(--terracotta);}
.period-btn.active{background:var(--terracotta);color:#fff;border-color:var(--terracotta);}
.custom-range{display:flex;gap:8px;align-items:center;margin-left:8px;}
.date-input{border:1.5px solid var(--border);border-radius:8px;padding:7px 10px;font-size: 14px;font-family:'DM Sans',sans-serif;background:#fff;color:var(--text-main);}

/* KPI Grid — top accent bars are semantic per-metric colors, unchanged except gold updated to new hex */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.kpi-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);padding:20px 22px;position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi-green::before{background:linear-gradient(90deg,#16a34a,#4ade80);}
.kpi-blue::before{background:linear-gradient(90deg,#1d4ed8,#60a5fa);}
.kpi-gold::before{background:linear-gradient(90deg,var(--gold),var(--gold-light));}
.kpi-purple::before{background:linear-gradient(90deg,#7c3aed,#c084fc);}
.kpi-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px;}
.kpi-val{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;line-height:1;color:var(--text-main);}
.kpi-label{font-size: 14px;color:var(--muted);margin-top:4px;}
.kpi-sub{font-size: 13px;margin-top:6px;}

/* Charts */
.charts-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.chart-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;}
.chart-header{padding:16px 22px;border-bottom:1px solid var(--border);}
.chart-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;color:var(--text-main);}
.chart-header p{font-size: 14px;color:var(--muted);margin-top:2px;}
.chart-body{padding:20px;position:relative;}
.chart-full{grid-column:1/-1;}

/* Table (page-specific spacing, slightly denser than the shared default) */
th{padding:11px 20px;letter-spacing:.7px;}
td{padding:13px 20px;}

/* Donut legend */
.legend-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;color:var(--text-main);}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

/* Progress bar */
.progress-bar-wrap{background:var(--sand);border-radius:100px;height:6px;overflow:hidden;margin-top:4px;}
.progress-bar-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--terracotta),var(--gold));}

/* Period label */
.period-label{font-size: 14px;color:var(--muted);margin-bottom:20px;}
.period-label strong{color:var(--text-main);}

@media (max-width: 900px) {
    .kpi-grid{grid-template-columns:1fr 1fr;}
    .charts-row{grid-template-columns:minmax(0, 1fr);}
}
@media (max-width: 480px) {
    .kpi-grid{grid-template-columns:1fr;}
    .custom-range{margin-left:0;width:100%;}
}
</style>
@endpush

@section('content')

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
                <span class="text-muted-theme" style="font-size:13px;">—</span>
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

    <div class="period-label" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <span>
            Showing data from <strong>{{ $from->format('M d, Y') }}</strong>
            to <strong>{{ $to->format('M d, Y') }}</strong>
        </span>
        <span style="display:flex;gap:8px;">
            <a href="{{ route('admin.reports.export.pdf', request()->query()) }}" class="period-btn">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
            <a href="{{ route('admin.reports.export.excel', request()->query()) }}" class="period-btn">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </span>
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
            <div class="kpi-icon tag-blue"><i class="bi bi-calendar-check"></i></div>
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
            <div class="kpi-icon tag-purple"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-val">₱{{ number_format($avgBookingValue, 0) }}</div>
            <div class="kpi-label">Avg. Booking Value</div>
            <div class="kpi-sub text-muted-theme">{{ $newGuests }} new guests</div>
        </div>
    </div>

    {{-- Revenue Over Time (full width) --}}
    <div class="table-card chart-full" style="margin-bottom:20px;">
        <div class="chart-header" style="padding:16px 22px;border-bottom:1px solid var(--border);">
            <h3>Revenue — Last 12 Months</h3>
            <p class="text-muted-theme" style="font-size: 14px;margin-top:2px;">Monthly collected payments (excl. refunds)</p>
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
            <span class="text-muted-theme" style="font-size: 14px;">Selected period</span>
        </div>
        @if($topProperties->isEmpty())
            <div class="text-muted-theme" style="text-align:center;padding:40px;font-size:13px;">
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
                    <td class="text-muted-theme" style="font-weight:700;">{{ $i+1 }}</td>
                    <td style="font-weight:600;">{{ $row->property->property_name ?? 'N/A' }}</td>
                    <td class="text-center">{{ $row->bookings }}</td>
                    <td style="font-weight:600;color:#15803d;">₱{{ number_format($row->revenue, 2) }}</td>
                    <td class="text-muted-theme">₱{{ number_format($row->bookings > 0 ? $row->revenue / $row->bookings : 0, 2) }}</td>
                    <td style="width:140px;">
                        <div class="text-muted-theme" style="font-size: 13px;margin-bottom:3px;">
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

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
@endpush
