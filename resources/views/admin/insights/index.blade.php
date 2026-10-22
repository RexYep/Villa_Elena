@extends('layouts.admin')

@section('title', 'Insights — Villa Elena Resort')
@section('page-title', 'Insights')
@section('page-subtitle', 'This month so far, against the same days last month — ' . now()->format('F d, Y'))

@section('topbar-right')
    {{-- POST, hindi <a href>. Ang GET ay bumabasa lang ng naitabing teksto;
         ito lang ang tumatawag sa AI. Ang mga numero ay laging live —
         ang limang pangungusap lang ang isinusulat muli ng button na ito. --}}
    <form method="POST" action="{{ route('admin.insights.refresh') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn-refresh">
            <i class="bi bi-arrow-clockwise"></i> Refresh Insights
        </button>
    </form>
@endsection

@push('styles')
    <style>
        /* minmax(0, 1fr), not 1fr: a bare 1fr lets each card's own content set
           its floor, and the four came out four different widths. */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: var(--cream);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
        }

        .kpi-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1.1;
        }

        .kpi-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .insights-table {
            margin-bottom: 24px;
        }

        .insights-table td.num,
        .insights-table th.num {
            text-align: right;
            white-space: nowrap;
        }

        .insights-table .basis {
            color: var(--muted);
            font-size: 12px;
            display: block;
            font-weight: 400;
        }

        .insights-table .group-cell {
            background: var(--sand);
            font-weight: 600;
            color: var(--stone);
            font-size: 13px;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .insights-table .sub-row td:first-child {
            padding-left: 40px;
            color: var(--muted);
        }

        .insights-table .of {
            color: var(--muted);
            font-size: 12px;
            display: block;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .panel {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            overflow: hidden;
        }

        .breakdown {
            padding: 20px 24px;
        }

        .breakdown h3,
        .method-panel h3 {
            font-family: var(--font-display);
            font-size: 16px;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .breakdown p {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .breakdown ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .breakdown li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text-main);
        }

        .breakdown li:last-child {
            border-bottom: none;
        }

        .method-panel {
            margin-bottom: 24px;
            padding: 20px 28px;
        }

        .method-list {
            margin: 10px 0 0;
            padding-left: 20px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--text-main);
        }

        .method-list li {
            margin-bottom: 4px;
        }

        .panel-header {
            background: var(--stone);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .panel-header .title {
            font-family: var(--font-display);
            color: var(--cream);
            font-size: 17px;
            font-weight: 600;
        }

        .panel-badge {
            margin-left: auto;
            background: var(--gold);
            color: var(--stone);
            font-size: 13px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .panel-body {
            padding: 28px 32px;
            background: var(--sand);
        }

        .insight-lines {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .insight-line {
            background: var(--cream);
            border: 1px solid var(--border);
            border-left: 4px solid var(--gold);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 15px;
            color: var(--text-main);
            line-height: 1.5;
            /* Model output — an unbroken token would otherwise push the card
               past the viewport. */
            overflow-wrap: anywhere;
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
            flex-shrink: 0;
        }

        .ai-unavailable strong {
            color: var(--text-main);
            font-weight: 600;
        }

        .ai-unavailable p {
            margin: 4px 0 0;
        }

        .page-foot {
            padding: 14px 24px;
            border-top: 1px solid var(--border);
            font-size: 14px;
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

        /* Four cards need ~960px of content, which with the sidebar is a
           1300px window; below that two keep every figure on one line. */
        @media (max-width: 1300px) {
            .kpi-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 900px) {
            .breakdown-grid {
                grid-template-columns: 1fr;
            }

            .panel-body {
                padding: 20px;
            }
        }

        /* Stacked on a phone, a group heading is a full-width label, not a
           centred empty-state line (admin.css's rule for colspan cells). */
        @media (max-width: 640px) {
            .main-content .insights-table table.is-stacked td[colspan].group-cell {
                text-align: left;
            }

            .main-content .insights-table table.is-stacked tr.sub-row td:first-child {
                padding-left: 20px;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .method-panel {
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
        $now = $insights['current'];
        $was = $insights['previous'];
        $peso = fn ($v) => \App\Models\Payment::formatPesos((float) $v);
        $pct = fn ($v) => $v === null ? '—' : number_format($v, 0) . '%';

        // "+3", "−₱1,200", "same" — a difference only, never a judgment:
        // more cancellations and more bookings would need opposite colours.
        $diff = function ($a, $b, $money = false) use ($peso) {
            $d = round((float) $a - (float) $b, 2);
            if ($d == 0) {
                return 'same';
            }
            $text = $money ? $peso(abs($d)) : number_format(abs($d));
            return ($d > 0 ? '+' : '−') . $text;
        };

        $pointDiff = function ($a, $b) {
            if ($a === null || $b === null) {
                return '—';
            }
            $d = (int) round($a) - (int) round($b);
            return $d === 0 ? 'same' : ($d > 0 ? '+' : '−') . abs($d) . ' pts';
        };

        // Only the reasons that occurred in either period, so the table does
        // not list four rows of zeros.
        $reasons = array_filter(
            \App\Services\Insights\MonthToDate::CANCELLED_BY,
            fn ($label, $key) => $now['cancelled_by'][$key] + $was['cancelled_by'][$key] > 0,
            ARRAY_FILTER_USE_BOTH
        );
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- At a glance. Each card names the period it compares against. --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Bookings Made</div>
            <div class="kpi-value">{{ number_format($now['made']) }}</div>
            <div class="kpi-sub">{{ $was['label'] }}: {{ number_format($was['made']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Slots Booked</div>
            <div class="kpi-value">{{ $pct($now['slots_pct']) }}</div>
            <div class="kpi-sub">{{ $now['slots_booked'] }} of {{ $now['slots_offered'] }} · {{ $was['label'] }}: {{ $pct($was['slots_pct']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Net Money Received</div>
            <div class="kpi-value">{{ $peso($now['net']) }}</div>
            <div class="kpi-sub">{{ $was['label'] }}: {{ $peso($was['net']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">The Villa Now</div>
            <div class="kpi-value">{{ $insights['now']['checked_in'] ? 'Occupied' : 'Available' }}</div>
            <div class="kpi-sub">
                {{ $insights['now']['awaiting_payment'] }}
                {{ \Illuminate\Support\Str::plural('booking', $insights['now']['awaiting_payment']) }} waiting for payment
            </div>
        </div>
    </div>

    {{-- Every figure the AI is given, worked out by rule. --}}
    <div class="table-card insights-table">
        <div class="table-header">
            <h3>This Month So Far</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Figure</th>
                    <th class="num">{{ $now['label'] }}</th>
                    <th class="num">{{ $was['label'] }}</th>
                    <th class="num">Change</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4" class="group-cell">Bookings <span class="basis">by the date the booking was made</span></td>
                </tr>
                <tr>
                    <td>Bookings made</td>
                    <td class="num">{{ number_format($now['made']) }}</td>
                    <td class="num">{{ number_format($was['made']) }}</td>
                    <td class="num">{{ $diff($now['made'], $was['made']) }}</td>
                </tr>
                <tr>
                    <td>Still standing</td>
                    <td class="num">{{ number_format($now['standing']) }}</td>
                    <td class="num">{{ number_format($was['standing']) }}</td>
                    <td class="num">{{ $diff($now['standing'], $was['standing']) }}</td>
                </tr>
                <tr>
                    <td>Cancelled</td>
                    <td class="num">{{ number_format($now['cancelled']) }}</td>
                    <td class="num">{{ number_format($was['cancelled']) }}</td>
                    <td class="num">{{ $diff($now['cancelled'], $was['cancelled']) }}</td>
                </tr>
                @foreach ($reasons as $key => $label)
                    <tr class="sub-row">
                        <td>{{ $label }}</td>
                        <td class="num">{{ $now['cancelled_by'][$key] }}</td>
                        <td class="num">{{ $was['cancelled_by'][$key] }}</td>
                        <td class="num">{{ $diff($now['cancelled_by'][$key], $was['cancelled_by'][$key]) }}</td>
                    </tr>
                @endforeach

                <tr>
                    <td colspan="4" class="group-cell">Slots <span class="basis">by the date of the stay</span></td>
                </tr>
                <tr>
                    <td>Slots booked</td>
                    <td class="num">
                        {{ $pct($now['slots_pct']) }}
                        <span class="of">{{ $now['slots_booked'] }} of {{ $now['slots_offered'] }} for sale</span>
                    </td>
                    <td class="num">
                        {{ $pct($was['slots_pct']) }}
                        <span class="of">{{ $was['slots_booked'] }} of {{ $was['slots_offered'] }} for sale</span>
                    </td>
                    <td class="num">{{ $pointDiff($now['slots_pct'], $was['slots_pct']) }}</td>
                </tr>

                <tr>
                    <td colspan="4" class="group-cell">Money <span class="basis">by the date it was paid</span></td>
                </tr>
                <tr>
                    <td>Collected</td>
                    <td class="num">{{ $peso($now['collected']) }}</td>
                    <td class="num">{{ $peso($was['collected']) }}</td>
                    <td class="num">{{ $diff($now['collected'], $was['collected'], true) }}</td>
                </tr>
                <tr>
                    <td>Refunded</td>
                    <td class="num">{{ $peso($now['refunds']) }}</td>
                    <td class="num">{{ $peso($was['refunds']) }}</td>
                    <td class="num">{{ $diff($now['refunds'], $was['refunds'], true) }}</td>
                </tr>
                <tr>
                    <td><strong>Net received</strong></td>
                    <td class="num"><strong>{{ $peso($now['net']) }}</strong></td>
                    <td class="num"><strong>{{ $peso($was['net']) }}</strong></td>
                    <td class="num">{{ $diff($now['net'], $was['net'], true) }}</td>
                </tr>

                <tr>
                    <td colspan="4" class="group-cell">Guests <span class="basis">stays with a check-in date in the period</span></td>
                </tr>
                <tr>
                    <td>Stays</td>
                    <td class="num">{{ $now['stays']['total'] }}</td>
                    <td class="num">{{ $was['stays']['total'] }}</td>
                    <td class="num">{{ $diff($now['stays']['total'], $was['stays']['total']) }}</td>
                </tr>
                <tr class="sub-row">
                    <td>By guests who had stayed before</td>
                    <td class="num">{{ $now['stays']['returning'] }}</td>
                    <td class="num">{{ $was['stays']['returning'] }}</td>
                    <td class="num">{{ $diff($now['stays']['returning'], $was['stays']['returning']) }}</td>
                </tr>
                <tr class="sub-row">
                    <td>By first-time guests</td>
                    <td class="num">{{ $now['stays']['first'] }}</td>
                    <td class="num">{{ $was['stays']['first'] }}</td>
                    <td class="num">{{ $diff($now['stays']['first'], $was['stays']['first']) }}</td>
                </tr>
                <tr>
                    <td>New guest accounts</td>
                    <td class="num">{{ $now['new_accounts'] }}</td>
                    <td class="num">{{ $was['new_accounts'] }}</td>
                    <td class="num">{{ $diff($now['new_accounts'], $was['new_accounts']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- What this month's standing bookings were made as, and through which channel. --}}
    <div class="breakdown-grid">
        <div class="panel breakdown">
            <h3>Bookings Made, by Slot</h3>
            <p>Standing bookings made {{ $now['label'] }}.</p>
            @if ($now['by_slot'] === [])
                <p class="mb-0">None yet.</p>
            @else
                <ul>
                    @foreach ($now['by_slot'] as $row)
                        <li><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="panel breakdown">
            <h3>Bookings Made, by Source</h3>
            <p>Every booking made {{ $now['label'] }}, cancelled ones included.</p>
            @if ($now['by_source'] === [])
                <p class="mb-0">None yet.</p>
            @else
                <ul>
                    @foreach ($now['by_source'] as $row)
                        <li><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- The answer to "where does this number come from?" --}}
    <div class="panel method-panel">
        <h3><i class="bi bi-calculator me-1"></i> How these figures are counted</h3>
        <ul class="method-list">
            <li>This month so far ({{ $now['label'] }}) is compared with <strong>the same days</strong> of last
                month ({{ $was['label'] }}), not with the whole of it — a month that is not over yet would always
                look smaller.</li>
            <li><strong>Bookings</strong> are counted by the date they were made. <strong>Still standing</strong> means
                not cancelled and not a no-show.</li>
            <li><strong>Slots booked</strong> are counted by the date of the stay: slots taken ÷ slots for sale
                (Day or Night on an ordinary date, the 22-hour stay on a date set aside for it; closed dates are
                not for sale). It is the same count as Reports and Recommendations.</li>
            <li><strong>Money</strong> is counted by the date it was paid, with refunds subtracted — the same rule as
                the Dashboard and Reports.</li>
            <li>A guest <strong>had stayed before</strong> when they have a checked-out stay with an earlier
                check-in date — the same rule the returning-guest promos use.</li>
        </ul>
    </div>

    {{-- The AI's five sentences about the figures above. It adds no numbers. --}}
    <div class="panel">
        <div class="panel-header">
            <i class="bi bi-chat-square-text fs-5 text-gold"></i>
            <span class="title">What the Figures Say</span>
            <span class="panel-badge">AI-written</span>
        </div>
        <div class="panel-body">
            @if ($lines === null)
                <div class="ai-unavailable">
                    <i class="bi bi-cloud-slash"></i>
                    <div>
                        <strong>No summary has been written yet.</strong>
                        <p>The AI service didn't answer the last time it was asked. The figures above are worked out
                            without it and are complete — press <em>Refresh Insights</em> to try again.</p>
                    </div>
                </div>
            @else
                @if ($reportState === 'month_moved')
                    <div class="stale-note">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>This summary was written in {{ $generatedAt->format('F') }} and describes that month, not
                            this one. Press <em>Refresh Insights</em> to rewrite it.</div>
                    </div>
                @elseif ($reportState === 'days_moved')
                    <div class="stale-note">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>This summary was written on {{ $generatedAt->format('M j') }} for
                            {{ $writtenFor ?? 'an earlier period' }}. The table above now covers {{ $now['label'] }}.
                            Press <em>Refresh Insights</em> to rewrite it.</div>
                    </div>
                @elseif ($reportState === 'figures_changed')
                    <div class="stale-note">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>Bookings or payments have changed since this was written, so some figures it quotes are out
                            of date. The table above is current. Press <em>Refresh Insights</em> to rewrite it.</div>
                    </div>
                @endif
                <div class="insight-lines">
                    @foreach ($lines as $line)
                        <div class="insight-line">{{ $line }}</div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="text-muted-theme page-foot">
            <i class="bi bi-info-circle me-1"></i>
            The figures are recalculated on every visit; the AI only puts them into words and is told not to add
            numbers of its own.
            @if ($generatedAt)
                {{-- Mahalagang makita ang edad: hindi muling isinusulat ang
                     buod kada bisita. --}}
                Summary written <strong>{{ $generatedAt->diffForHumans() }}</strong>
                ({{ $generatedAt->format('M d, Y g:i A') }}).
            @endif
            This page is <strong>what happened</strong> — for what is likely next, see
            <a href="{{ route('admin.forecast.index') }}">Forecast</a>; for what to do, see
            <a href="{{ route('admin.prescriptive.index') }}">Recommendations</a>.
        </div>
    </div>
@endsection
