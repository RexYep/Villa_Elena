@extends('layouts.admin')

@section('title', 'What-If Simulator — Villa Elena Admin')
@section('page-title', 'What-If Simulator')
@section('page-subtitle', 'Try a price change against the same demand model the recommendations use')

@section('topbar-right')
    <a href="{{ route('admin.prescriptive.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-left"></i> Back to Recommendations
    </a>
@endsection

@push('styles')
    <style>
        .intro-note {
            background: var(--cream);
            border: 1px solid var(--border);
            border-left: 3px solid var(--gold);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.7;
        }

        .intro-note strong { color: var(--text-main); }

        .sim-form {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 22px;
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 16px;
            align-items: end;
        }

        .sim-form label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .btn-run {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 22px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            height: 38px;
        }

        .btn-run:hover { background: var(--gold); }

        .result-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .result-tile {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .result-tile .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .result-tile .value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1.1;
        }

        .result-tile .sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .result-tile.headline .value { color: var(--terracotta); }
        .value.up { color: #15803d !important; }
        .value.down { color: #b91c1c !important; }

        .verdict {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.7;
        }

        .verdict.good { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .verdict.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .verdict.flat { background: var(--sand); border: 1px solid var(--border); color: var(--stone); }

        .assumption {
            font-size: 12.5px;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.6;
        }

        .table-scroll { overflow-x: auto; }

        td.num, th.num { text-align: right; white-space: nowrap; }

        .holiday-tag {
            font-size: 11px;
            background: #fef3c7;
            color: #92400e;
            padding: 1px 7px;
            border-radius: 20px;
            margin-left: 6px;
        }
    </style>
@endpush

@section('content')

    <div class="intro-note">
        This runs the <strong>same demand model</strong> as the Recommendations page — historical fill rate per
        day-and-slot, live villa rate, and the elasticity assumption from Settings. Enter a
        <strong>negative</strong> number for a discount, a <strong>positive</strong> one for a price increase.
        <strong>Nothing is saved and nothing changes</strong> — this page only calculates.
    </div>

    <form method="GET" action="{{ route('admin.prescriptive.simulate') }}" class="sim-form">
        <div>
            <label>From</label>
            <input type="date" name="start" class="form-control" value="{{ $start->toDateString() }}">
        </div>
        <div>
            <label>To</label>
            <input type="date" name="end" class="form-control" value="{{ $end->toDateString() }}">
        </div>
        <div>
            <label>Slot</label>
            <select name="slot" class="form-control">
                <option value="both" @selected($slotChoice === 'both')>Both slots</option>
                <option value="day" @selected($slotChoice === 'day')>Day only</option>
                <option value="night" @selected($slotChoice === 'night')>Night only</option>
            </select>
        </div>
        <div>
            <label>Price change (%)</label>
            <input type="number" name="change" class="form-control" value="{{ $change }}" min="-50" max="50"
                step="1">
        </div>
        <button type="submit" class="btn-run">Simulate</button>
    </form>

    @if (empty($rows))
        <div class="table-card">
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>
                    Every date in that range is already booked or blocked, so there is nothing a price change could
                    affect.
                </p>
            </div>
        </div>
    @else
        @php
            $pct = $baseline > 0 ? ($delta / $baseline) * 100 : 0;
            $verdictClass = $delta > 0.005 ? 'good' : ($delta < -0.005 ? 'bad' : 'flat');
        @endphp

        <div class="verdict {{ $verdictClass }}">
            @if ($delta > 0.005)
                <strong>Worth doing, on these assumptions.</strong>
                A {{ abs($change) }}% {{ $change < 0 ? 'discount' : 'increase' }} across
                {{ count($rows) }} open slot{{ count($rows) === 1 ? '' : 's' }} is projected to
                <strong>add ₱{{ number_format($delta, 0) }}</strong> ({{ number_format($pct, 1) }}%) in expected
                revenue.
            @elseif ($delta < -0.005)
                <strong>Not worth doing, on these assumptions.</strong>
                A {{ abs($change) }}% {{ $change < 0 ? 'discount' : 'increase' }} across
                {{ count($rows) }} open slot{{ count($rows) === 1 ? '' : 's' }} is projected to
                <strong>cost ₱{{ number_format(abs($delta), 0) }}</strong> ({{ number_format($pct, 1) }}%) in expected
                revenue — the {{ $change < 0 ? 'extra bookings do not make up for the lower rate' : 'lost bookings outweigh the higher rate' }}.
            @else
                <strong>Roughly a wash.</strong>
                The projected gain and loss cancel out across {{ count($rows) }} open
                slot{{ count($rows) === 1 ? '' : 's' }}.
            @endif

            <div class="assumption">
                Using {{ $change < 0 ? 'the off-peak' : 'the peak-date' }} elasticity of
                <strong>{{ rtrim(rtrim(number_format($elasticity, 2), '0'), '.') }}</strong> — a 1% price
                {{ $change < 0 ? 'cut' : 'rise' }} is assumed to
                {{ $change < 0 ? 'raise' : 'lower' }} booking probability by
                {{ rtrim(rtrim(number_format($elasticity, 2), '0'), '.') }}%. This is an
                <strong>assumption, not a measurement</strong>; change it under Settings → Prescriptive Engine and run
                this again to see how sensitive the answer is.
                @if ($skipped > 0)
                    {{ $skipped }} slot{{ $skipped === 1 ? '' : 's' }} in this range
                    {{ $skipped === 1 ? 'was' : 'were' }} skipped as already booked or blocked.
                @endif
            </div>
        </div>

        <div class="result-strip">
            <div class="result-tile">
                <div class="label">Expected Revenue Now</div>
                <div class="value">₱{{ number_format($baseline, 0) }}</div>
                <div class="sub">{{ $baselineBookings }} bookings expected</div>
            </div>
            <div class="result-tile">
                <div class="label">Expected If Applied</div>
                <div class="value">₱{{ number_format($projected, 0) }}</div>
                <div class="sub">{{ $projectedBookings }} bookings expected</div>
            </div>
            <div class="result-tile headline">
                <div class="label">Difference</div>
                <div class="value {{ $delta > 0 ? 'up' : ($delta < 0 ? 'down' : '') }}">
                    {{ $delta >= 0 ? '+' : '−' }}₱{{ number_format(abs($delta), 0) }}
                </div>
                <div class="sub">{{ number_format($pct, 1) }}% vs doing nothing</div>
            </div>
            <div class="result-tile">
                <div class="label">Open Slots Priced</div>
                <div class="value">{{ count($rows) }}</div>
                <div class="sub">{{ $start->format('M j') }} – {{ $end->format('M j, Y') }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3>Slot by Slot</h3>
                <span style="font-size:12px;color:var(--muted)">
                    Fill % is the historical rate for that day-of-week and slot
                </span>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Slot</th>
                            <th class="num">Fill now</th>
                            <th class="num">Fill after</th>
                            <th class="num">Rate now</th>
                            <th class="num">Rate after</th>
                            <th class="num">Expected now</th>
                            <th class="num">Expected after</th>
                            <th class="num">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $rowDelta = $row['projected'] - $row['baseline']; @endphp
                            <tr>
                                <td>
                                    {{ $row['date']->format('D, M j') }}
                                    @if ($row['holiday'])
                                        <span class="holiday-tag">{{ $row['holiday'] }}</span>
                                    @endif
                                </td>
                                <td>{{ ucfirst($row['slot']) }}</td>
                                <td class="num">{{ round($row['p'] * 100) }}%</td>
                                <td class="num">{{ round($row['p_new'] * 100) }}%</td>
                                <td class="num">₱{{ number_format($row['price'], 0) }}</td>
                                <td class="num">₱{{ number_format($row['new_price'], 0) }}</td>
                                <td class="num">₱{{ number_format($row['baseline'], 2) }}</td>
                                <td class="num">₱{{ number_format($row['projected'], 2) }}</td>
                                <td class="num" style="color:{{ $rowDelta >= 0 ? '#15803d' : '#b91c1c' }}">
                                    {{ $rowDelta >= 0 ? '+' : '−' }}₱{{ number_format(abs($rowDelta), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection
