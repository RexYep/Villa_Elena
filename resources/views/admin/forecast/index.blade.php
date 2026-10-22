@extends('layouts.admin')

@section('title', 'Forecasting — Villa Elena Resort')
@section('page-title', 'Forecasting')
@section('page-subtitle', 'Slots and stay value, this month and the next three — ' . now()->format('F d, Y'))

@section('topbar-right')
    {{-- POST, hindi <a href> — tingnan ang paliwanag sa insights/index.
         Ang mga numero ay laging live; ang paliwanag lang ng AI ang
         isinusulat muli ng button na ito. --}}
    <form method="POST" action="{{ route('admin.forecast.refresh') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn-refresh">
            <i class="bi bi-arrow-clockwise"></i> Refresh Explanation
        </button>
    </form>
@endsection

@push('styles')
    @vite(['resources/js/admin-charts.js'])
    <style>
        .panel,
        .chart-panel {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            overflow: hidden;
        }

        .forecast-table {
            margin-bottom: 24px;
        }

        .forecast-table td.num,
        .forecast-table th.num {
            text-align: right;
            white-space: nowrap;
        }

        .forecast-table .of {
            color: var(--muted);
            font-size: 12px;
            display: block;
        }

        .month-state {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            vertical-align: 1px;
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
            font-family: var(--font-display);
            font-size: 16px;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .chart-panel p {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        /* Legend in HTML, not Chart.js's, so each key shows what its bars look
           like: solid for booked, outlined pale for expected — identity is
           never the shade alone. */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
            font-size: 13px;
            color: var(--text-main);
            margin-bottom: 12px;
        }

        .chart-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .chart-legend i {
            display: inline-block;
            width: 14px;
            height: 12px;
            border-radius: 3px;
        }

        .key-booked {
            background: #b55a31;
        }

        .key-expected {
            background: rgba(181, 90, 49, .18);
            border: 2px solid #b55a31;
        }

        .method-list {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--text-main);
        }

        .method-list li {
            margin-bottom: 4px;
        }

        .panel-header {
            background: var(--terracotta);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .panel-header .title {
            font-family: var(--font-display);
            color: #fff;
            font-size: 17px;
            font-weight: 600;
        }

        .panel-badge {
            margin-left: auto;
            background: rgba(255, 255, 255, .18);
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

        .method-panel {
            margin-bottom: 24px;
        }

        .method-panel .panel-body {
            padding: 20px 28px;
            background: var(--cream);
        }

        .method-panel h3 {
            font-family: var(--font-display);
            font-size: 16px;
            color: var(--stone);
            margin-bottom: 10px;
        }

        .stale-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            border: 1px solid var(--state-amber-line);
            background: var(--state-amber-soft);
            color: var(--tag-amber-fg);
            font-size: 14px;
            line-height: 1.6;
        }

        .stale-note i {
            font-size: 16px;
            line-height: 1.5;
        }

        .ai-unavailable {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .ai-unavailable i {
            font-size: 22px;
            line-height: 1.4;
            color: var(--muted);
            flex-shrink: 0;
        }

        .ai-unavailable strong {
            color: var(--text-main);
            font-weight: 600;
        }

        .ai-unavailable p {
            margin: 4px 0 0;
        }

        .forecast-text {
            font-size: 15px;
            line-height: 1.8;
            color: var(--stone);
            /* Model output — an unbroken token would otherwise set the
               min-content width inside an overflow:hidden panel. */
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
            font-family: var(--font-display);
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

        .btn-refresh {
            background: var(--btn-primary);
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
            background: var(--btn-primary-hover);
            color: #fff;
        }

        .page-foot {
            padding: 14px 24px;
            border-top: 1px solid var(--border);
            font-size: 14px;
        }

        /* Fixed-height box + maintainAspectRatio:false keeps the plot readable
           at every width (an aspect-ratio canvas got shorter as it got wider). */
        .chart-box {
            position: relative;
            height: 260px;
        }

        /* Two-up only once each chart is wide enough for nine month labels to
           sit flat. */
        @media (max-width: 1199px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .panel-body {
                padding: 20px;
            }

            .method-panel .panel-body {
                padding: 18px 20px;
            }

            .panel-header {
                flex-wrap: wrap;
            }

            .panel-badge {
                margin-left: 0;
            }
        }

        /* Icon-only below 560px so the button fits the 68px topbar. */
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

        @media (hover: none) and (pointer: coarse) {
            .btn-refresh {
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
    @php
        $months = $outlook['months'];
        $forecastMonths = array_values(array_filter($months, fn ($m) => $m['state'] !== 'past'));
        $peso = fn ($v) => \App\Models\Payment::formatPesos((float) $v);
        $pct = fn ($v) => $v === null ? '—' : number_format($v, 0) . '%';
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- The forecast itself: worked out by rule, not by the AI. --}}
    <div class="table-card forecast-table">
        <div class="table-header">
            <h3>This Month and the Next Three</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="num">Booked so far</th>
                    <th class="num">Usual fill</th>
                    <th class="num">Expected slots</th>
                    <th class="num">Value booked</th>
                    <th class="num">Expected value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($forecastMonths as $m)
                    <tr>
                        <td>
                            <strong>{{ $m['name'] }}</strong>
                            @if ($m['state'] === 'current')
                                <span class="month-state tag-amber">{{ $m['days_left'] }} {{ \Illuminate\Support\Str::plural('day', $m['days_left']) }} left</span>
                            @endif
                            @if ($m['holidays'] !== [])
                                <span class="of">
                                    <i class="bi bi-calendar-event me-1"></i>{{ collect($m['holidays'])->map(fn ($h) => $h['date']->format('M j') . ' ' . $h['name'])->implode(' · ') }}
                                </span>
                            @endif
                        </td>
                        <td class="num">
                            {{ $m['booked'] }}
                            <span class="of">of {{ $m['offered'] }} for sale ({{ $pct($m['booked_pct']) }})</span>
                        </td>
                        <td class="num">{{ $pct($m['usual_pct']) }}</td>
                        <td class="num">
                            <strong>{{ $m['expected'] }}</strong>
                            <span class="of">{{ $m['extra'] > 0 ? '+' . $m['extra'] . ' more expected' : 'none more expected' }}</span>
                        </td>
                        <td class="num">{{ $peso($m['value_booked']) }}</td>
                        <td class="num">
                            <strong>{{ $peso($m['value_expected']) }}</strong>
                            @if ($m['extra'] > 0 && $m['open_price'] !== null)
                                <span class="of">+{{ $m['extra'] }} × {{ $peso($m['open_price']) }} avg. open rate</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Charts: six months of what happened, then the forecast months. --}}
    <div class="chart-grid">
        <div class="chart-panel">
            <h3>Slots per Month</h3>
            <p>Slots booked, with the expected remainder for months not yet over.</p>
            <div class="chart-legend" aria-hidden="true">
                <span><i class="key-booked"></i>Booked</span>
                <span><i class="key-expected"></i>Expected more</span>
            </div>
            <div class="chart-box">
                <canvas id="slotsChart" role="img"
                    aria-label="Slots booked and expected per month. The table above lists the figures for this month and the next three."></canvas>
            </div>
        </div>
        <div class="chart-panel">
            <h3>Value of Stays per Month</h3>
            <p>By the month of the stay — not money received.</p>
            <div class="chart-legend" aria-hidden="true">
                <span><i class="key-booked"></i>Booked</span>
                <span><i class="key-expected"></i>Expected more</span>
            </div>
            <div class="chart-box">
                <canvas id="valueChart" role="img"
                    aria-label="Value of stays booked and expected per month. The table above lists the figures for this month and the next three."></canvas>
            </div>
        </div>
    </div>

    {{-- How the numbers are made — the answer to "where does this come from?" --}}
    <div class="panel method-panel">
        <div class="panel-body">
            <h3><i class="bi bi-calculator me-1"></i> How these numbers are worked out</h3>
            <ul class="method-list">
                <li>A <strong>slot</strong> is one stay the villa can sell: Day or Night on an ordinary date, or the
                    22-hour stay on a date set aside for it. Closed dates are not counted as for sale.</li>
                <li><strong>Usual fill</strong> is how full the same weekdays were over the last
                    {{ $outlook['history_days'] }} days ({{ $pct($outlook['usual_pct']) }} across all weekdays).</li>
                <li><strong>Expected slots</strong>: for the dates still ahead, the larger of what is already booked
                    and the usual fill. Dates already past keep what they got. This is the same rule the
                    <a href="{{ route('admin.prescriptive.index') }}">Recommendations</a> page uses.</li>
                <li><strong>Expected value</strong> = value already booked + the extra expected slots × the average
                    rate the month's open slots are listed at today (promos included).</li>
                <li><strong>Value</strong> is the price of the stays, counted in the month of the stay. The Dashboard and
                    Reports count money by the date it was paid, so their totals differ.</li>
            </ul>
        </div>
    </div>

    {{-- The AI's explanation of the figures above. It adds no numbers. --}}
    <div class="panel">
        <div class="panel-header">
            <i class="bi bi-chat-square-text fs-5 text-gold"></i>
            <span class="title">What the Figures Say</span>
            <span class="panel-badge">AI-written</span>
        </div>
        <div class="panel-body">
            @if ($forecastHtml === null)
                <div class="ai-unavailable">
                    <i class="bi bi-cloud-slash"></i>
                    <div>
                        <strong>No explanation has been written yet.</strong>
                        <p>The AI service didn't respond the last time it was asked. The forecast figures above are
                            worked out without it and are complete — press <em>Refresh Explanation</em> to try again.</p>
                    </div>
                </div>
            @else
                @if ($reportState === 'months_moved')
                    <div class="stale-note">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>This explanation was written in {{ $generatedAt->format('F') }} and covers different months
                            from the table above. Press <em>Refresh Explanation</em> to rewrite it.</div>
                    </div>
                @elseif ($reportState === 'figures_changed')
                    <div class="stale-note">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>Bookings have changed since this was written, so some figures it quotes are out of date.
                            The table above is current. Press <em>Refresh Explanation</em> to rewrite it.</div>
                    </div>
                @endif
                <div class="forecast-text">{!! $forecastHtml !!}</div>
            @endif
        </div>
        <div class="text-muted-theme page-foot">
            <i class="bi bi-info-circle me-1"></i>
            The figures are recalculated on every visit; the AI only explains them and is told not to add numbers of its own.
            @if ($generatedAt)
                {{-- Mahalagang makita ang edad: hindi muling isinusulat ang
                     paliwanag kada bisita. --}}
                Explanation written <strong>{{ $generatedAt->diffForHumans() }}</strong>
                ({{ $generatedAt->format('M d, Y g:i A') }}).
            @endif
            This page is the <strong>outlook</strong> — for what to do about it, see
            <a href="{{ route('admin.prescriptive.index') }}">Recommendations</a>.
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const months = @json($months);

            const labels = months.map(m => m.state === 'past' ? m.label : m.label + (m.state === 'current' ? ' (now)' : ''));
            const booked = months.map(m => m.booked);
            const extra = months.map(m => m.extra);
            const valueBooked = months.map(m => m.value_booked);
            const valueExtra = months.map(m => Math.max(0, m.value_expected - m.value_booked));

            // Chart.js draws on a canvas, which cannot read CSS variables:
            // these are --terracotta and a pale tint of it.
            const solid = '#b55a31';
            const pale = 'rgba(181,90,49,0.18)';
            const peso = v => '₱' + Math.round(v).toLocaleString();

            const datasets = (a, b) => [{
                    label: 'Booked',
                    data: a,
                    backgroundColor: solid,
                    borderColor: '#fdfbf7',
                    borderWidth: {
                        top: 2
                    },
                    borderSkipped: false,
                    borderRadius: 0,
                    stack: 's',
                },
                {
                    label: 'Expected more',
                    data: b,
                    backgroundColor: pale,
                    borderColor: solid,
                    borderWidth: 2,
                    borderRadius: {
                        topLeft: 4,
                        topRight: 4
                    },
                    borderSkipped: 'bottom',
                    stack: 's',
                },
            ];

            const options = (tick, footer) => ({
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.dataset.label + ': ' + tick(ctx.parsed.y),
                            footer: items => footer(months[items[0].dataIndex]),
                        }
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: {
                            color: '#e4ddd0'
                        },
                        ticks: {
                            callback: tick,
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        stacked: true,
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
            });

            new Chart(document.getElementById('slotsChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: datasets(booked, extra)
                },
                options: options(v => Number(v).toLocaleString(), m =>
                    (m.state === 'past' ? '' : 'Expected: ' + m.expected + '\n') + m.offered + ' slots for sale'),
            });

            new Chart(document.getElementById('valueChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: datasets(valueBooked, valueExtra)
                },
                options: options(peso, m => m.state === 'past' ? '' : 'Expected: ' + peso(m.value_expected)),
            });
        });
    </script>
@endpush
