@extends('layouts.admin')

@section('title', 'Reports & Analytics — Villa Elena Admin')
@section('page-title', 'Reports & Analytics')
@section('page-subtitle', 'Revenue, occupancy, and booking insights')

@push('styles')
@vite(['resources/js/admin-charts.js'])
<style>
/* Period Tabs */
.period-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;align-items:center;}
.period-btn{padding:7px 16px;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;border:1.5px solid var(--border);color:var(--muted);background:var(--cream);transition:all .2s;cursor:pointer;}
.period-btn:hover{border-color:var(--terracotta);color:var(--terracotta);}
.period-btn.active{background:var(--terracotta);color:var(--cream);border-color:var(--terracotta);}
.custom-range{display:flex;gap:8px;align-items:center;margin-left:8px;}
.date-input{border:1.5px solid var(--border-strong);border-radius:8px;padding:7px 10px;font-size: 14px;font-family: var(--font-body);background:var(--cream);color:var(--text-main);}

/* KPI Grid — the top accent bar is a semantic colour per metric */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.kpi-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);padding:20px 22px;position:relative;overflow:hidden;}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi-green::before{background:var(--tag-green-fg);}
.kpi-blue::before{background:var(--tag-blue-fg);}
.kpi-gold::before{background:linear-gradient(90deg,var(--gold),var(--gold-light));}
.kpi-purple::before{background:var(--tag-purple-fg);}
.kpi-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px;}
.kpi-val{font-family: var(--font-display);font-size: 25px;font-weight:700;line-height:1;color:var(--text-main);}
.kpi-label{font-size: 14px;color:var(--muted);margin-top:4px;}
.kpi-sub{font-size: 13px;margin-top:6px;}
.kpi-sub + .kpi-sub{margin-top:2px;}

/* Charts */
.charts-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.chart-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;}
.chart-header{padding:16px 22px;border-bottom:1px solid var(--border);}
.chart-header h3{font-family: var(--font-display);font-size: 16px;font-weight:600;color:var(--text-main);}
.chart-header p{font-size: 14px;color:var(--muted);margin-top:2px;}
.chart-body{padding:20px;position:relative;}
.chart-full{grid-column:1/-1;}
.chart-empty{padding:40px 20px;text-align:center;font-size:14px;color:var(--muted);}

/* Table (page-specific spacing, slightly denser than the shared default).
   Scoped to this table: a bare th/td rule here restyled every table on the
   page, the layout's included. */
.report-table th{padding:11px 20px;letter-spacing:.7px;}
.report-table td{padding:13px 20px;}
.slot-times{display:block;font-size:13px;font-weight:400;color:var(--muted);}
.table-note{padding:12px 20px 16px;}

/* Donut legend */
.legend-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;color:var(--text-main);}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

/* Progress bar */
.progress-bar-wrap{background:var(--sand);border-radius:100px;height:6px;overflow:hidden;margin-top:4px;}
.progress-bar-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--terracotta),var(--gold));}

/* Period label */
.period-label{font-size: 14px;color:var(--muted);margin-bottom:20px;}
.period-label strong{color:var(--text-main);}
.period-basis{display:block;font-size:13px;margin-top:4px;max-width:78ch;}

/* Line/bar charts were sized by aspect ratio (canvas height attr + Chart.js's
   default maintainAspectRatio), so height followed width: the 12-month revenue
   chart (300:80) had a 22px-tall plot area on a 320px phone, and the two
   half-width charts went from a 398px plot at 900px to 78px at 993px when the
   row turned two-up. A fixed box with maintainAspectRatio:false fixes both. */
.chart-box{position:relative;height:240px;}

/* Donut + legend rows: let the legend drop under the 160px donut rather than be
   squeezed to 66-84px beside it on a phone. No effect on non-flex chart bodies. */
.chart-body{flex-wrap:wrap;}

/* One chart per row until 1199px, as on the forecast page: two-up in the
   993-1199px band halved each chart to 282-420px, rotating the month labels
   50deg and dropping half of them. */
@media (max-width: 1199px) {
    .charts-row{grid-template-columns:minmax(0, 1fr);}
}
/* Two-up until 1100px, not 900px. A card needs about 177px for its label on
   one line (22px padding a side + border), so four need 756px of content — a
   1080px viewport once the sidebar is back. The old breakpoint put four 155px
   cards in the 993px band, wrapping two labels and making the cards 194px
   tall: taller than the two-up cards at 900px. */
@media (max-width: 1100px) {
    .kpi-grid{grid-template-columns:1fr 1fr;}
}
@media (max-width: 480px) {
    .kpi-grid{grid-template-columns:1fr;}
    .custom-range{margin-left:0;width:100%;}
}
/* The custom range (two date inputs, a dash, Apply) needs 403px in one row and
   did not wrap, so below ~430px Apply ran to R417 and was clipped by
   body{overflow-x:hidden} — no way to submit a custom period on a phone.
   From/To side by side, Apply full width beneath. */
@media (max-width: 430px) {
    .custom-range{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
    .custom-range>span{display:none;}
    .custom-range .date-input{width:100%;min-width:0;}
    .custom-range .period-btn{grid-column:1/-1;}
}
/* Period buttons and date inputs were 36-37px. Keyed on pointer type, not
   width: only a finger needs the larger target. */
@media (hover: none) and (pointer: coarse) {
    .period-btn,.date-input{min-height:44px;}
    a.period-btn{display:inline-flex;align-items:center;gap:6px;}
}
</style>
@endpush

@section('content')

    {{-- A custom range that could not be used. The controller has already
         fallen back to this month; this says why the dates were ignored. --}}
    @if($rangeProblem)
        <div class="alert alert-warning" role="alert">{{ $rangeProblem }}</div>
    @endif

    {{-- Period Selector --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" id="periodForm">
        <div class="period-bar">
            @foreach(['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year'] as $val=>$label)
                <button type="submit" name="period" value="{{ $val }}"
                    class="period-btn {{ $period === $val ? 'active' : '' }}"
                    @if($period === $val) aria-current="true" @endif>
                    {{ $label }}
                </button>
            @endforeach
            <div class="custom-range">
                <input type="date" name="from" class="date-input" aria-label="Custom range, from date"
                    value="{{ $customFrom }}">
                <span class="text-muted-theme fs-13" aria-hidden="true">—</span>
                <input type="date" name="to" class="date-input" aria-label="Custom range, to date"
                    value="{{ $customTo }}">
                <button type="submit" name="period" value="custom"
                    class="period-btn {{ $period === 'custom' ? 'active' : '' }}"
                    @if($period === 'custom') aria-current="true" @endif>
                    Apply
                </button>
            </div>
        </div>
    </form>

    <div class="period-label" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <span>
            Showing data from <strong>{{ $from->format('M d, Y') }}</strong>
            to <strong>{{ $to->format('M d, Y') }}</strong>
            {{-- Three date bases share this page. Unstated, "This Month" reads as
                 one thing when it is three. --}}
            <span class="period-basis">
                Bookings are counted by the date they were made, payments by the date they were paid,
                and occupancy by the date of the stay.
                @if($runsPastToday)
                    This period runs past today, so occupancy includes dates still to come.
                @endif
            </span>
        </span>
        <span class="flex-gap-8">
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
            <div class="kpi-icon tag-green"><i class="bi bi-cash-stack"></i></div>
            <div class="kpi-val">{{ \App\Models\Payment::formatPesos($netRevenue) }}</div>
            <div class="kpi-label">Net Revenue</div>
            {{-- Collected − refunds = net. The two revenue charts below are net
                 too, so their bars add up to the figure above; these two
                 lines are the only place the parts are shown. --}}
            <div class="kpi-sub text-muted-theme">₱{{ number_format($totalRevenue, 0) }} collected</div>
            @if($totalRefunds > 0)
            <div class="kpi-sub text-red">−₱{{ number_format($totalRefunds, 0) }} refunds</div>
            @endif
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon tag-blue"><i class="bi bi-calendar-check"></i></div>
            <div class="kpi-val">{{ $totalBookings }}</div>
            <div class="kpi-label">Bookings Made</div>
            @if($cancelledBookings > 0)
            <div class="kpi-sub text-red">{{ $cancelledBookings }} cancelled</div>
            @endif
            @if($noShowBookings > 0)
            <div class="kpi-sub text-red">{{ $noShowBookings }} no show</div>
            @endif
        </div>
        <div class="kpi-card kpi-gold">
            <div class="kpi-icon" style="background:var(--gold-dim);color:var(--gold-text);"><i class="bi bi-percent"></i></div>
            @if($occupancy['offered'] > 0)
                <div class="kpi-val">{{ $occupancyRate }}%</div>
                <div class="kpi-label">Occupancy Rate</div>
                {{-- The two counts behind the percentage, so it can be checked
                     against the calendar by hand. --}}
                <div class="kpi-sub text-muted-theme">{{ $occupancy['booked'] }} of {{ $occupancy['offered'] }} slots booked</div>
                <div class="progress-bar-wrap mt-8">
                    <div class="progress-bar-fill" style="width:{{ min(100,$occupancyRate) }}%;background:linear-gradient(90deg,var(--gold),var(--gold-light));"></div>
                </div>
            @else
                <div class="kpi-val">—</div>
                <div class="kpi-label">Occupancy Rate</div>
                <div class="kpi-sub text-muted-theme">No slots were open for booking in this period.</div>
            @endif
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon tag-purple"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-val">₱{{ number_format($avgBookingValue, 0) }}</div>
            <div class="kpi-label">Avg. Booking Value</div>
            <div class="kpi-sub text-muted-theme">{{ $newGuests }} new {{ Str::plural('guest', $newGuests) }}</div>
        </div>
    </div>

    {{-- Net Revenue Over Time (full width) --}}
    <div class="table-card chart-full mb-20">
        <div class="chart-header" style="padding:16px 22px;border-bottom:1px solid var(--border);">
            <h3>Net Revenue, Last 12 Months</h3>
            <p class="text-muted-theme" style="font-size: 14px;margin-top:2px;">Per month, after refunds. Always the last 12 months, whatever period is selected.</p>
        </div>
        <div style="padding:20px 24px;">
            <div class="chart-box"><canvas id="revenueChart" role="img" aria-label="Bar chart of net revenue in each of the last 12 months"></canvas></div>
        </div>
    </div>

    {{-- Bookings Trend + Sources --}}
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Trend</h3>
                <p>Bookings made per month, cancelled and no-show left out</p>
            </div>
            <div class="chart-body">
                <div class="chart-box"><canvas id="bookingsChart" role="img" aria-label="Line chart of bookings made in each of the last 12 months"></canvas></div>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Sources</h3>
                <p>Where the bookings made in this period came from</p>
            </div>
            @if(empty($bookingsBySource))
                <div class="chart-empty">No bookings were made in this period.</div>
            @else
            <div class="chart-body" style="display:flex;align-items:center;gap:24px;">
                <canvas id="sourceChart" style="max-width:160px;max-height:160px;" role="img" aria-label="Donut chart of bookings by source"></canvas>
                <div id="sourceLegend"></div>
            </div>
            @endif
        </div>
    </div>

    {{-- Collected in the period + Status Breakdown --}}
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Net Revenue in This Period</h3>
                <p>
                    @switch($revenueUnit)
                        @case('week') Per week, after refunds. Each bar is labelled by its first day. @break
                        @case('month') Per month, after refunds. @break
                        @default Per day, after refunds.
                    @endswitch
                    {{-- A refund is dated when it was issued, so a bar can fall
                         below zero. Said only when one does. --}}
                    @if(collect($revenueSeries)->min('amount') < 0)
                        A red bar is a {{ $revenueUnit }} that refunded more than it collected.
                    @endif
                </p>
            </div>
            @if($totalRevenue == 0 && $totalRefunds == 0)
                <div class="chart-empty">No payments or refunds in this period.</div>
            @else
            <div class="chart-body">
                <div class="chart-box"><canvas id="dailyChart" role="img" aria-label="Bar chart of net revenue across the selected period"></canvas></div>
            </div>
            @endif
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Booking Status Mix</h3>
                <p>The bookings made in this period, by their status now</p>
            </div>
            @if(empty($bookingsByStatus))
                <div class="chart-empty">No bookings were made in this period.</div>
            @else
            <div class="chart-body" style="display:flex;align-items:center;gap:24px;">
                <canvas id="statusChart" style="max-width:160px;max-height:160px;" role="img" aria-label="Donut chart of bookings by status"></canvas>
                <div id="statusLegend"></div>
            </div>
            @endif
        </div>
    </div>

    {{-- Bookings by slot. This was "Revenue by Property": with one villa it
         could only ever show one row, and its Share divided booked value by
         payments collected, so it read above 100%. --}}
    <div class="table-card">
        <div class="table-header">
            <h3>Bookings by Slot</h3>
            <span class="text-muted-theme fs-14">Made in this period, cancelled and no-show left out</span>
        </div>
        @if(empty($bySlot))
            <div class="chart-empty">No bookings were made in this period.</div>
        @else
        <table class="report-table">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Bookings</th>
                    <th>Booked Value</th>
                    <th>Avg / Booking</th>
                    <th>Share of Booked Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bySlot as $row)
                <tr>
                    <td class="fw-600">
                        {{ $row['label'] }}
                        <span class="slot-times">{{ $row['times'] }}</span>
                    </td>
                    <td>{{ $row['bookings'] }}</td>
                    <td class="fw-600">₱{{ number_format($row['value'], 2) }}</td>
                    <td class="text-muted-theme">₱{{ number_format($row['average'], 2) }}</td>
                    <td style="width:180px;">
                        <div class="text-muted-theme" style="font-size: 13px;margin-bottom:3px;">{{ $row['share'] }}%</div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:{{ min(100, $row['share']) }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note-text table-note">
            Booked value is the price of these bookings: ₱{{ number_format($bookedValue, 2) }} in all.
            It is not the money collected, which is counted by the date it was paid.
        </p>
        @endif
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
const NAVY   = '#2c2416'; // --stone; canvas colours can't read CSS variables
const GOLD   = '#c9a84c';
const BLUE   = '#1d4ed8';
const GREEN  = '#16a34a';
const RED    = '#dc2626';
const PURPLE = '#7c3aed';
const SLATE  = '#475569';

Chart.defaults.font.family = "'DM Sans', sans-serif"; // keep in step with --font-body
Chart.defaults.color = '#746a59';

// Net revenue can be below zero, so the sign goes in front of the peso sign.
const peso = v => (v < 0 ? '−' : '') + '₱' + (Math.abs(v) >= 1000 ? (Math.abs(v)/1000).toFixed(0)+'k' : Math.abs(v));
const pesoFull = v => (v < 0 ? '−' : '') + '₱' + Math.abs(v).toLocaleString('en-PH');
const netTooltip = { callbacks: { label: ctx => ' ' + pesoFull(ctx.parsed.y) } };

// A card with nothing to show renders a sentence instead of its canvas.
const canvas = id => document.getElementById(id);

// Revenue Bar Chart
const revData = @json($revenueByMonth);
new Chart(canvas('revenueChart'), {
    type: 'bar',
    data: {
        labels: revData.map(d => d.label),
        datasets: [{
            label: 'Net revenue (₱)',
            data: revData.map(d => d.amount),
            backgroundColor: revData.map((d, i) => d.amount < 0 ? RED + 'cc' : (i === revData.length - 1 ? GOLD : NAVY + 'cc')),
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: netTooltip },
        scales: {
            y: { ticks: { callback: peso }, grid: { color: '#e4ddd0' } },
            x: { grid: { display: false } }
        }
    }
});

// Bookings Line Chart
const bkData = @json($bookingsByMonth);
new Chart(canvas('bookingsChart'), {
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
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { stepSize: 1 }, grid: { color: '#e4ddd0' } },
            x: { grid: { display: false } }
        }
    }
});

// One donut and its legend. Rows are { key, label, count } with the label
// already worded by the server; the colour is looked up by key, so a source
// keeps its colour whichever others are present.
function donut(canvasId, legendId, rows, colours) {
    const el = canvas(canvasId);
    if (!el) return;

    const fills = rows.map(r => colours[r.key] || SLATE);

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: rows.map(r => r.label),
            datasets: [{ data: rows.map(r => r.count), backgroundColor: fills, borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive: false, plugins: { legend: { display: false } }, cutout: '65%' }
    });

    const legend = document.getElementById(legendId);
    rows.forEach((r, i) => {
        const row = document.createElement('div');
        row.className = 'legend-row';

        const dot = document.createElement('div');
        dot.className = 'legend-dot';
        dot.style.background = fills[i];

        const text = document.createElement('span');
        const count = document.createElement('strong');
        count.textContent = r.count;
        text.append(r.label + ': ', count);

        row.append(dot, text);
        legend.append(row);
    });
}

donut('sourceChart', 'sourceLegend', @json($bookingsBySource),
    { online: NAVY, walk_in: GOLD, phone: BLUE, partner: GREEN });

donut('statusChart', 'statusLegend', @json($bookingsByStatus),
    { pending: GOLD, confirmed: GREEN, checked_in: BLUE, checked_out: SLATE, cancelled: RED, no_show: PURPLE });

// Net revenue across the period: a day, a week or a month per bar. Red when
// the bar is below zero — more was refunded than collected.
const periodNet = @json($revenueSeries);
if (canvas('dailyChart')) {
    new Chart(canvas('dailyChart'), {
        type: 'bar',
        data: {
            labels: periodNet.map(d => d.label),
            datasets: [{
                label: 'Net revenue (₱)',
                data: periodNet.map(d => d.amount),
                backgroundColor: periodNet.map(d => (d.amount < 0 ? RED : GREEN) + 'cc'),
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: netTooltip },
            scales: {
                y: { ticks: { callback: peso }, grid: { color: '#e4ddd0' } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
            }
        }
    });
}
});
</script>
@endpush
