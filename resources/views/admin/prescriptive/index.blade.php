@extends('layouts.admin')

@section('title', 'Recommendations — Villa Elena Admin')
@section('page-title', 'Recommendations')
@section('page-subtitle', 'What to do next, and the reason for it')

@section('topbar-right')
    <form method="POST" action="{{ route('admin.prescriptive.regenerate') }}" class="d-inline">
        @csrf
        <button type="submit" class="rec-refresh" aria-label="Refresh recommendations">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
        </button>
    </form>
@endsection

@push('styles')
    @vite(['resources/js/admin-charts.js'])
    <style>
        .rec-refresh,
        .rec-apply {
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            cursor: pointer;
        }

        .rec-refresh:hover,
        .rec-apply:hover {
            background: var(--btn-primary-hover);
        }

        .rec-dismiss,
        .rec-undo {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border-strong);
            border-radius: 9px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }

        .rec-dismiss:hover,
        .rec-undo:hover {
            color: var(--stone);
            border-color: var(--stone);
        }

        .rec-undo {
            padding: 4px 10px;
            font-size: 12px;
            margin-top: 6px;
        }

        /* One sentence instead of a row of counters: there is one number worth
           knowing here, and it reads better as words. */
        .rec-status {
            font-size: 15px;
            line-height: 1.6;
            color: var(--stone);
            margin-bottom: 6px;
            max-width: 72ch;
        }

        .rec-status-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 18px;
            max-width: 72ch;
            line-height: 1.6;
        }

        .rec-briefing {
            border-left: 3px solid var(--terracotta);
            padding: 2px 0 2px 16px;
            margin: 0 0 22px;
            max-width: 72ch;
        }

        .rec-briefing p {
            font-size: 14.5px;
            line-height: 1.7;
            color: var(--stone);
            margin: 0;
        }

        .rec-briefing small {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }

        /* How this works — closed by default so the cards come first, open in
           one click when someone asks "why did this appear?". */
        .rec-how {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .rec-how summary {
            padding: 13px 18px;
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
            cursor: pointer;
        }

        .rec-how-body {
            padding: 0 18px 18px;
        }

        .rec-how-body p {
            font-size: 13.5px;
            line-height: 1.7;
            color: var(--stone);
            margin: 0 0 12px;
            max-width: 76ch;
        }

        .rec-how dl {
            margin: 0 0 16px;
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 6px 16px;
            font-size: 13.5px;
            line-height: 1.6;
        }

        .rec-how dt {
            font-weight: 600;
            color: var(--stone);
        }

        .rec-how dd {
            margin: 0;
            color: var(--muted);
        }

        .rec-how table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .rec-how th,
        .rec-how td {
            text-align: left;
            vertical-align: top;
            padding: 9px 12px 9px 0;
            border-top: 1px solid var(--border);
            line-height: 1.55;
        }

        .rec-how th {
            font-weight: 600;
            color: var(--muted);
            border-top: none;
        }

        .rec-how td:first-child {
            font-weight: 600;
            color: var(--stone);
            white-space: nowrap;
        }

        .rec-how-foot {
            font-size: 13px;
            color: var(--muted);
            margin: 14px 0 0;
        }

        .rec-card {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 0 16px;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 14px;
        }

        .rec-kind-icon {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .rec-kind {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .rec-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            line-height: 1.3;
            color: var(--stone);
            margin: 0 0 8px;
        }

        .rec-summary {
            font-size: 14.5px;
            line-height: 1.65;
            color: var(--stone);
            margin: 0 0 12px;
            max-width: 70ch;
        }

        .rec-facts {
            list-style: none;
            margin: 0 0 16px;
            padding: 0 0 0 14px;
            border-left: 2px solid var(--border);
        }

        .rec-facts li {
            font-size: 13.5px;
            line-height: 1.7;
            color: var(--muted);
        }

        .rec-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .rec-actions form {
            margin: 0;
        }

        .rec-empty {
            background: var(--cream);
            border: 1px dashed var(--border-strong);
            border-radius: 14px;
            padding: 28px 24px;
            font-size: 14.5px;
            line-height: 1.7;
            color: var(--stone);
            max-width: 72ch;
        }

        .rec-history {
            margin-top: 30px;
        }

        /* History filter: plain links, because each view is its own page. */
        .rec-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin: 0 0 18px;
        }

        .rec-filter-label {
            font-size: 13px;
            color: var(--muted);
            margin-right: 4px;
        }

        .rec-filter a {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 6px 14px;
            border: 1px solid var(--border-strong);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            color: var(--stone);
            text-decoration: none;
            background: var(--cream);
        }

        .rec-filter a:hover {
            border-color: var(--stone);
        }

        .rec-filter a[aria-current="page"] {
            background: var(--btn-primary);
            border-color: var(--btn-primary);
            color: #fff;
        }

        .rec-quarter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin: -6px 0 18px;
        }

        .rec-quarter-select {
            min-height: 38px;
            padding: 6px 34px 6px 12px;
            border: 1px solid var(--border-strong);
            border-radius: 9px;
            background-color: var(--cream);
            color: var(--stone);
            font-size: 14px;
            font-weight: 600;
            max-width: 100%;
        }

        .rec-quarter-go {
            min-height: 38px;
            padding: 6px 16px;
            border: 1px solid var(--border-strong);
            border-radius: 9px;
            background: transparent;
            color: var(--stone);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .rec-quarter-go:hover {
            border-color: var(--stone);
        }

        /* Charts. The colours here are the legend keys; the canvas reads the
           same values from the script, since a canvas cannot read CSS. */
        .rec-chart-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
        }

        .rec-chart-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--stone);
            margin: 0 0 4px;
        }

        .rec-chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
            margin: 10px 0 6px;
            font-size: 13px;
            color: var(--stone);
        }

        .rec-chart-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .rec-key {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .rec-key-1 {
            background: #2a78d6;
        }

        .rec-key-2 {
            background: #eb6834;
        }

        .rec-key-3 {
            background: #1baf7a;
        }

        .rec-key-line {
            height: 0;
            width: 16px;
            border-radius: 0;
            border-top: 2px dashed var(--muted);
        }

        .rec-chart-box {
            position: relative;
            height: 280px;
        }

        .rec-chart-table {
            margin-top: 10px;
        }

        .rec-chart-table summary,
        .rec-month summary {
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--stone);
            padding: 6px 0;
        }

        .rec-chart-table .table-card {
            margin-top: 8px;
        }

        .rec-month {
            border-top: 1px solid var(--border);
            padding: 6px 0;
        }

        .rec-month summary {
            font-size: 15px;
        }

        .rec-month[open] summary {
            margin-bottom: 10px;
        }

        .rec-preview-banner {
            background: var(--state-amber-soft);
            border: 1px solid var(--state-amber-line);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
            line-height: 1.6;
            color: var(--stone);
            margin-bottom: 18px;
            max-width: 80ch;
        }

        .rec-preview-banner a {
            color: var(--stone);
            font-weight: 600;
        }

        .rec-compare {
            margin-bottom: 22px;
        }

        .rec-compare td,
        .rec-compare th {
            white-space: nowrap;
        }

        .rec-compare .rec-history-note {
            white-space: normal;
        }

        .rec-up {
            color: var(--tag-green-fg);
            font-weight: 600;
        }

        .rec-down {
            color: var(--tag-red-fg);
            font-weight: 600;
        }

        .rec-section-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            color: var(--stone);
            margin: 26px 0 12px;
        }

        .rec-card .badge {
            margin-left: 6px;
            vertical-align: middle;
        }

        .rec-gone {
            margin: 0 0 14px;
            padding-left: 18px;
            font-size: 14px;
            line-height: 1.8;
            color: var(--stone);
        }

        .rec-history td {
            vertical-align: top;
        }

        .rec-history-note {
            font-size: 13px;
            color: var(--muted);
        }

        .rec-history form {
            margin: 0;
        }

        @media (max-width: 560px) {

            /* The icon tile moves above the text instead of taking a column the
               sentence needs. */
            .rec-card {
                grid-template-columns: minmax(0, 1fr);
                gap: 12px 0;
                padding: 18px 16px;
            }

            .rec-how dl {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
            }

            .rec-how dd {
                margin-bottom: 8px;
            }

            /* Three columns leave about 90px each here, so every rule becomes
               a small block: its name, then "When" and "Recommendation". */
            .rec-how thead {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip-path: inset(50%);
            }

            .rec-how table,
            .rec-how tbody,
            .rec-how tr,
            .rec-how td {
                display: block;
            }

            .rec-how tr {
                border-top: 1px solid var(--border);
                padding: 10px 0;
            }

            .rec-how td {
                border: none;
                padding: 0 0 4px;
            }

            .rec-how td:first-child {
                white-space: normal;
            }

            .rec-how td[data-label]::before {
                content: attr(data-label) ": ";
                color: var(--muted);
            }

            /* The topbar also holds search, the bell and Logout; the label
               would leave no room for the page title. */
            .topbar-right .rec-refresh {
                font-size: 0;
                gap: 0;
                padding: 9px 12px;
            }

            .topbar-right .rec-refresh i {
                font-size: 15px;
            }
        }

        /* Keyed on pointer type, not width: only a finger needs the larger
           target. These buttons change prices and send messages. */
        @media (hover: none) and (pointer: coarse) {

            .rec-refresh,
            .rec-apply,
            .rec-dismiss {
                min-height: 44px;
            }

            .rec-undo {
                min-height: 40px;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $count = $open->count();
        $checked = $lastGenerated ? \Carbon\Carbon::parse($lastGenerated) : null;
    @endphp

    <nav class="rec-filter" aria-label="Recommendation view">
        <span class="rec-filter-label">View:</span>
        <a href="{{ route('admin.prescriptive.index') }}" @if ($view === 'default') aria-current="page" @endif>
            Default ({{ $historyDays }} days)
        </a>
        @foreach (\App\Http\Controllers\Admin\PrescriptiveController::PREVIEW_VIEWS as $key => $label)
            <a href="{{ route('admin.prescriptive.index', ['view' => $key]) }}" @if ($view === $key) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($view !== 'default')
        @if (empty($years))
            <div class="rec-empty">
                There is not enough booking history yet. A what-if needs {{ $historyDays }} days of bookings before the
                month it looks at.
            </div>
        @else
            {{-- A plain GET form, so it works without JavaScript; the script at
                 the end only saves pressing Show. --}}
            <form method="GET" action="{{ route('admin.prescriptive.index') }}" class="rec-quarter-form" id="recPeriodForm">
                <input type="hidden" name="view" value="{{ $view }}">

                <label for="recYear" class="rec-filter-label">Year:</label>
                <select id="recYear" name="year" class="rec-quarter-select">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>

                @if ($view === 'month')
                    <label for="recMonth" class="rec-filter-label">Month:</label>
                    <select id="recMonth" name="month" class="rec-quarter-select">
                        @foreach (\App\Services\Prescriptive\WhatIfPreview::MONTHS as $number => $name)
                            <option value="{{ $number }}" @selected($number === $month)>{{ $name }}</option>
                        @endforeach
                    </select>
                @elseif ($view === 'quarter')
                    <label for="recQuarter" class="rec-filter-label">Quarter:</label>
                    <select id="recQuarter" name="quarter" class="rec-quarter-select">
                        @foreach ([1 => 'Jan–Mar', 2 => 'Apr–Jun', 3 => 'Jul–Sep', 4 => 'Oct–Dec'] as $number => $months)
                            <option value="{{ $number }}" @selected($number === $quarter)>Q{{ $number }} ({{ $months }})</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="rec-quarter-go">Show</button>
            </form>
        @endif
    @endif

    @if ($view === 'year' && $preview)
        @include('admin.prescriptive._year')
    @elseif ($view !== 'default' && $preview)
        @include('admin.prescriptive._preview')
    @elseif ($view === 'default')

    <p class="rec-status">
        @if ($count === 0)
            <strong>Nothing needs a decision right now.</strong>
        @else
            <strong>{{ $count }} {{ $count === 1 ? 'recommendation is' : 'recommendations are' }} waiting for your
                decision.</strong>
        @endif
        @if ($checked)
            Last checked {{ $checked->diffForHumans() }} ({{ $checked->format('M j, g:i A') }}).
        @else
            The rules have not run yet — press Refresh.
        @endif
    </p>
    <p class="rec-status-sub">
        Nothing happens on its own. A promo, a rate, a block, a task or a reminder exists only after you press the button
        on its card.
    </p>

    @if (!empty($briefing))
        {{-- Ang AI ay nagbubuod lang dito. Lahat ng nasa talata ay galing sa
             mga card sa ibaba; kapag bumagsak ang AI, nawawala lang ang
             kahong ito at buo pa rin ang page. --}}
        <blockquote class="rec-briefing">
            <p>{{ $briefing }}</p>
            <small>Summary written by AI from the cards below. It cannot add a recommendation of its own.</small>
        </blockquote>
    @endif

    <details class="rec-how">
        <summary>How these recommendations are worked out</summary>
        <div class="rec-how-body">
            <p>
                The system looks at upcoming dates one stretch at a time — each week's weekdays (Mon–Thu) and its weekend
                (Fri–Sun) — and reads three numbers for it:
            </p>
            <dl>
                <dt>Booked so far</dt>
                <dd>Slots already booked ÷ slots offered. A slot is one thing a guest can book: Day or Night, or a
                    22-hour stay on a date set aside for it.</dd>
                <dt>Usual</dt>
                <dd>How full the same weekdays were over the last {{ $historyDays }} days.</dd>
                <dt>Predicted occupancy</dt>
                <dd>Whichever of the two is larger. The dates will end at least as full as they already are, and
                    otherwise about as full as those weekdays usually get.</dd>
            </dl>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Decision</th>
                        <th scope="col">When</th>
                        <th scope="col">Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td>{{ $rule['decision'] }}</td>
                            <td data-label="When">{{ $rule['when'] }}</td>
                            <td data-label="Recommendation">{{ $rule['then'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="rec-how-foot">
                The percentages and day counts are yours to change under
                <a href="{{ route('admin.settings.index') }}">Settings → Booking Rules → Recommendation Rules</a>.
                The rules run every night, and whenever you press Refresh.
            </p>
        </div>
    </details>

    @forelse ($open as $rec)
        <article class="rec-card">
            <div class="rec-kind-icon {{ $rec->action_tag_class }}" aria-hidden="true">
                <i class="bi bi-{{ $rec->action_icon }}"></i>
            </div>
            <div>
                <div class="rec-kind">{{ $rec->action_label }}</div>
                <h2 class="rec-title">{{ $rec->title }}</h2>
                <p class="rec-summary">{{ $rec->summary }}</p>

                @if (!empty($rec->evidence))
                    <ul class="rec-facts">
                        @foreach ($rec->evidence as $fact)
                            <li>{{ $fact }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="rec-actions">
                    {{-- Ang tanong ay sinasabi ang EKSAKTONG mangyayari. Nasa
                         attribute ito, hindi sa inline handler, dahil may
                         pangalan ng guest ang ilan sa mga ito. --}}
                    <form method="POST" action="{{ route('admin.prescriptive.apply', $rec) }}"
                        data-confirm="{{ $rec->confirm_message }}" data-confirm-label="{{ $rec->apply_label }}"
                        data-confirm-tone="neutral">
                        @csrf
                        <button type="submit" class="rec-apply">
                            <i class="bi bi-check2" aria-hidden="true"></i> {{ $rec->apply_label }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.prescriptive.dismiss', $rec) }}"
                        data-confirm="Dismiss &quot;{{ $rec->title }}&quot;? It will not be suggested again. Nothing is created or changed."
                        data-confirm-label="Dismiss" data-confirm-tone="neutral">
                        @csrf
                        <button type="submit" class="rec-dismiss">Dismiss</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="rec-empty">
            No rule has anything to suggest. Upcoming dates are neither quiet enough for a promo nor busy enough for a
            higher rate, maintenance is already scheduled or has no free gap, no back-to-back bookings need a turnover
            clean, and no guest arriving soon owes a balance.
        </div>
    @endforelse

    @include('admin.prescriptive._stretch_chart', [
        'chartId' => 'recLiveChart',
        'chartTitle' => 'The next 30 days, week by week',
        'chartNote' => 'Worked out now, from the live calendar; the cards above were saved by the last run. Each pair of bars is one stretch: the weekdays (Mon–Thu) or the weekend (Fri–Sun) of a week. Predicted occupancy is the taller of the two bars.',
        'stretches' => $liveStretches,
    ])

    @endif

    @if ($view === 'default' && $history->isNotEmpty())
        <div class="table-card rec-history">
            <div class="table-header">
                <h3>Your decisions</h3>
                <span class="rec-history-note">The last {{ $history->count() }}, newest first</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Recommendation</th>
                        <th>Dates</th>
                        <th>Decision</th>
                        <th>What happened</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $rec)
                        @php $result = $results[$rec->id]; @endphp
                        <tr>
                            <td>
                                {{ $rec->title }}
                                <div class="rec-history-note">{{ $rec->action_label }}</div>
                            </td>
                            <td>{{ $rec->window_label }}</td>
                            <td>
                                @if ($rec->status === 'applied')
                                    <span class="badge tag-green">Applied</span>
                                    @if ($rec->appliedBy)
                                        <div class="rec-history-note">by {{ $rec->appliedBy->full_name }}</div>
                                    @endif
                                @else
                                    <span class="badge tag-neutral">Dismissed</span>
                                @endif
                            </td>
                            <td>
                                {{ $result['text'] }}
                                @if ($result['can_turn_off'])
                                    <form method="POST" action="{{ route('admin.prescriptive.turn-off-rate', $rec) }}"
                                        data-confirm="Turn off this rate? Check-ins {{ $rec->window_label }} go back to the normal price. Bookings already made keep what they were charged."
                                        data-confirm-label="Turn off rate">
                                        @csrf
                                        <button type="submit" class="rec-undo">Turn off rate</button>
                                    </form>
                                @endif
                            </td>
                            <td>{{ $rec->updated_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        // Choosing a year, month or quarter shows it straight away. Without
        // this script the Show button does the same.
        (function () {
            const form = document.getElementById('recPeriodForm');
            if (!form) return;

            form.querySelector('.rec-quarter-go').hidden = true;
            form.querySelectorAll('select').forEach(select =>
                select.addEventListener('change', () => form.requestSubmit()));
        })();

        // Every [data-rec-chart] on the page. The numbers come from the server
        // (WhatIfPreview); nothing is worked out here. Hex values because a
        // canvas cannot read CSS variables: series slots 1–3 of the checked
        // palette, the --muted ink and the --border grid.
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Chart) return;

            const BLUE = '#2a78d6', ORANGE = '#eb6834', AQUA = '#1baf7a';
            const INK = '#746a59', GRID = '#e4ddd0', SURFACE = '#fdfbf7';
            const QUIET = {{ (float) $thresholds['quiet'] }};
            const BUSY = {{ (float) $thresholds['busy'] }};

            Chart.defaults.font.family = "'DM Sans', sans-serif"; // keep in step with --font-body
            Chart.defaults.color = INK;

            // The quiet and busy marks, drawn as dashed reference lines: they
            // are thresholds, not gridlines. Their words are in the legend
            // above the chart; text on the plot collided with the bars.
            const marks = {
                id: 'recMarks',
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea: a, scales: { y } } = chart;
                    ctx.save();
                    ctx.strokeStyle = INK;
                    ctx.lineWidth = 1;
                    ctx.setLineDash([5, 4]);
                    [QUIET, BUSY].forEach(v => {
                        const py = y.getPixelForValue(v);
                        ctx.beginPath();
                        ctx.moveTo(a.left, py);
                        ctx.lineTo(a.right, py);
                        ctx.stroke();
                    });
                    ctx.restore();
                },
            };

            const pctAxis = {
                min: 0,
                max: 100,
                ticks: { stepSize: 25, callback: v => v + '%' },
                grid: { color: GRID },
                border: { display: false },
            };
            const xAxis = { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } };
            const bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 24 };

            document.querySelectorAll('[data-rec-chart]').forEach(canvas => {
                const rows = JSON.parse(canvas.dataset.chart);
                const kind = canvas.dataset.recChart;

                if (kind === 'stretch') {
                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: rows.map(r => r.label),
                            datasets: [
                                { label: 'Booked so far', data: rows.map(r => r.booked_pct), backgroundColor: BLUE, ...bar },
                                { label: 'Usual', data: rows.map(r => r.usual_pct), backgroundColor: ORANGE, ...bar },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            datasets: { bar: { categoryPercentage: 0.7, barPercentage: 0.9 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: items => rows[items[0].dataIndex].label.join(' · '),
                                        label: item => {
                                            const r = rows[item.dataIndex];
                                            return item.datasetIndex === 0
                                                ? ' Booked so far: ' + Math.round(r.booked_pct) + '% (' + r.booked + ' of ' + r.offered + ' slots)'
                                                : ' Usual: ' + Math.round(r.usual_pct) + '%';
                                        },
                                        footer: items => 'Predicted: ' + Math.round(rows[items[0].dataIndex].predicted) + '%',
                                    },
                                },
                            },
                            scales: { y: pctAxis, x: xAxis },
                        },
                        plugins: [marks],
                    });
                }

                if (kind === 'year-occupancy') {
                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: rows.map(r => r.label),
                            datasets: [{ label: 'Predicted occupancy', data: rows.map(r => r.predicted), backgroundColor: BLUE, ...bar }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: item => {
                                            const r = rows[item.dataIndex];
                                            return ' Predicted: ' + Math.round(r.predicted) + '% (' + r.booked + ' of ' + r.offered + ' slots booked so far)';
                                        },
                                    },
                                },
                            },
                            scales: { y: pctAxis, x: xAxis },
                        },
                        plugins: [marks],
                    });
                }

                if (kind === 'year-cards') {
                    // Stacked: a 2px surface-coloured edge on top of each
                    // segment is the gap between them.
                    const seg = (label, key, colour) => ({
                        label,
                        data: rows.map(r => r.enough ? r[key] : null),
                        backgroundColor: colour,
                        borderColor: SURFACE,
                        borderWidth: { top: 2 },
                        borderSkipped: 'start',
                        maxBarThickness: 24,
                    });

                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: rows.map(r => r.label),
                            datasets: [
                                seg('Promo', 'promo', BLUE),
                                seg('Raise rate', 'peak', ORANGE),
                                seg('Maintenance', 'maintenance', AQUA),
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    filter: item => item.raw > 0,
                                    callbacks: { label: item => ' ' + item.dataset.label + ': ' + item.raw },
                                },
                            },
                            scales: {
                                x: { ...xAxis, stacked: true },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { color: GRID },
                                    border: { display: false },
                                },
                            },
                        },
                    });
                }
            });
        });
    </script>
@endpush
