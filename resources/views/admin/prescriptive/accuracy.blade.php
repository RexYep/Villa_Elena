@extends('layouts.admin')

@section('title', 'Recommendation Accuracy — Villa Elena Admin')
@section('page-title', 'Accuracy')
@section('page-subtitle', 'What the engine promised, against what actually happened')

@section('topbar-right')
    <a href="{{ route('admin.prescriptive.index') }}" class="btn-refresh">
        <i class="bi bi-arrow-left"></i> Back to Recommendations
    </a>
@endsection

@push('styles')
    <style>
        .method-note {
            background: var(--cream);
            border: 1px solid var(--border);
            border-left: 3px solid var(--gold);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 22px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.75;
        }

        .method-note strong { color: var(--text-main); }

        .method-note code {
            background: var(--sand);
            padding: 1px 6px;
            border-radius: 5px;
            font-size: 12.5px;
        }

        .caveat {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 22px;
            font-size: 13px;
            color: #9a3412;
            line-height: 1.75;
        }

        .caveat strong { color: #7c2d12; }

        .section-head {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            margin: 30px 0 6px;
        }

        .section-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 16px;
            line-height: 1.7;
            max-width: 760px;
        }

        .score-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 8px;
        }

        .score-tile {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .score-tile .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .score-tile .value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1.1;
        }

        .score-tile .sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .score-tile.headline .value { color: var(--terracotta); }
        .value.up { color: #15803d !important; }
        .value.down { color: #b91c1c !important; }

        .sample-warning {
            font-size: 12.5px;
            color: #92400e;
            background: #fef3c7;
            border-radius: 9px;
            padding: 10px 14px;
            margin-top: 12px;
            line-height: 1.6;
        }

        td.num, th.num { text-align: right; white-space: nowrap; }
        .table-scroll { overflow-x: auto; }

        .status-pill {
            font-size: 11.5px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 20px;
        }

        .status-pill.applied { background: #dcfce7; color: #15803d; }
        .status-pill.dismissed { background: #e2e8f0; color: #475569; }
        .status-pill.expired { background: #fef3c7; color: #92400e; }
    </style>
@endpush

@section('content')

    <div class="method-note">
        <strong>How this is measured.</strong> When a recommendation's window closes, the engine records what actually
        came in for those dates and slots — <code>base_amount − discount_amount</code> on bookings that went ahead,
        excluding extras, because extras were never part of the forecast. That is compared against the
        <strong>baseline projection frozen on the day the recommendation was made</strong>: the revenue expected if
        nothing were done.
        <br><br>
        <code>realized impact = actual revenue − baseline projection</code>
    </div>

    <div class="caveat">
        <strong>Read this before quoting any number on this page.</strong>
        This is <strong>not a controlled experiment.</strong> There is no control group — we cannot observe the same
        September without the promo — so every figure is measured against the model's <em>own</em> baseline. That means
        a result mixes two things together: whether the action worked, and whether the baseline was right to begin with.
        A single window proves nothing either: expected revenue is a probability spread over a handful of slots, and one
        booking can flip the sign. Only the totals across many settled recommendations carry any weight.
    </div>

    @if ($measured->isEmpty())
        <div class="table-card">
            <div class="empty-state">
                <i class="bi bi-hourglass-split"></i>
                <p>
                    Nothing has been measured yet. A recommendation is only scored after its target window has fully
                    passed — {{ $pending }} {{ $pending === 1 ? 'is' : 'are' }} still waiting.
                    @if ($unmeasurable > 0)
                        A further {{ $unmeasurable }} closed without a score (maintenance windows, and anything made
                        before outcome tracking existed).
                    @endif
                </p>
            </div>
        </div>
    @else
        @php
            $calibration = $baselineProjected > 0 ? ($baselineActual / $baselineProjected) : null;
            $gainRatio = $promisedGain > 0 ? ($realizedGain / $promisedGain) : null;
        @endphp

        <div class="section-head">Is the model honest?</div>
        <div class="section-sub">
            Measured only on recommendations that were <strong>dismissed or expired</strong> — nothing was done, so the
            gap between forecast and reality is pure model error, with no intervention muddying it. This is the
            cleanest test of <code>DemandModel</code> the system can run on itself.
        </div>

        @if ($noAction->isEmpty())
            <div class="table-card">
                <div class="empty-state">
                    <i class="bi bi-question-circle"></i>
                    <p>No un-actioned recommendations have settled yet, so the model has not been tested cleanly.</p>
                </div>
            </div>
        @else
            <div class="score-strip">
                <div class="score-tile">
                    <div class="label">Forecast (no action)</div>
                    <div class="value">₱{{ number_format($baselineProjected, 0) }}</div>
                    <div class="sub">across {{ $noAction->count() }} settled window{{ $noAction->count() === 1 ? '' : 's' }}</div>
                </div>
                <div class="score-tile">
                    <div class="label">Actually Earned</div>
                    <div class="value">₱{{ number_format($baselineActual, 0) }}</div>
                    <div class="sub">same dates and slots</div>
                </div>
                <div class="score-tile headline">
                    <div class="label">Calibration</div>
                    <div class="value">
                        {{ $calibration !== null ? number_format($calibration * 100, 0) . '%' : '—' }}
                    </div>
                    <div class="sub">
                        @if ($calibration === null)
                            nothing to compare against
                        @elseif ($calibration > 1.15)
                            reality beat the forecast — the model is too pessimistic
                        @elseif ($calibration < 0.85)
                            reality fell short — the model is too optimistic
                        @else
                            forecast and reality broadly agree
                        @endif
                    </div>
                </div>
            </div>

            @if ($noAction->count() < 10)
                <div class="sample-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Only {{ $noAction->count() }} settled window{{ $noAction->count() === 1 ? '' : 's' }} so far.
                    That is far too few to call this a measured accuracy — treat it as an early signal, not a result.
                </div>
            @endif
        @endif

        <div class="section-head">Did the actions pay off?</div>
        <div class="section-sub">
            Measured on recommendations that were <strong>applied</strong>. The promised figure is the gain the engine
            projected; the realised figure is what the dates actually earned above the no-action baseline. Because the
            baseline is itself a forecast, a gap here can mean the action underperformed <em>or</em> that the baseline
            was wrong — the section above is what tells the two apart.
        </div>

        @if ($applied->isEmpty())
            <div class="table-card">
                <div class="empty-state">
                    <i class="bi bi-clipboard-check"></i>
                    <p>No applied recommendation has reached the end of its window yet.</p>
                </div>
            </div>
        @else
            <div class="score-strip">
                <div class="score-tile">
                    <div class="label">Gain Promised</div>
                    <div class="value">₱{{ number_format($promisedGain, 0) }}</div>
                    <div class="sub">across {{ $applied->count() }} applied window{{ $applied->count() === 1 ? '' : 's' }}</div>
                </div>
                <div class="score-tile">
                    <div class="label">Gain Realised</div>
                    <div class="value {{ $realizedGain > 0 ? 'up' : ($realizedGain < 0 ? 'down' : '') }}">
                        {{ $realizedGain >= 0 ? '+' : '−' }}₱{{ number_format(abs($realizedGain), 0) }}
                    </div>
                    <div class="sub">above the no-action baseline</div>
                </div>
                <div class="score-tile headline">
                    <div class="label">Delivered</div>
                    <div class="value">
                        {{ $gainRatio !== null ? number_format($gainRatio * 100, 0) . '%' : '—' }}
                    </div>
                    <div class="sub">of what was promised</div>
                </div>
            </div>

            @if ($applied->count() < 10)
                <div class="sample-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Based on {{ $applied->count() }} applied window{{ $applied->count() === 1 ? '' : 's' }}. One
                    booking landing or not landing moves this figure substantially.
                </div>
            @endif
        @endif

        <div class="table-card" style="margin-top:26px">
            <div class="table-header">
                <h3>Settled Recommendations</h3>
                <span style="font-size:12px;color:var(--muted)">
                    {{ $measured->count() }} measured
                    @if ($unmeasurable > 0)
                        · {{ $unmeasurable }} closed without a score
                    @endif
                    @if ($pending > 0)
                        · {{ $pending }} still open
                    @endif
                </span>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Recommendation</th>
                            <th>Window</th>
                            <th>Outcome</th>
                            <th class="num">Baseline</th>
                            <th class="num">Actual</th>
                            <th class="num">Realised</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($measured as $rec)
                            <tr>
                                <td>{{ $rec->title }}</td>
                                <td>{{ $rec->window_label }}</td>
                                <td><span class="status-pill {{ $rec->status }}">{{ ucfirst($rec->status) }}</span></td>
                                <td class="num">₱{{ number_format((float) $rec->baseline_projection, 2) }}</td>
                                <td class="num">₱{{ number_format((float) $rec->actual_revenue, 2) }}</td>
                                <td class="num"
                                    style="color:{{ (float) $rec->realized_impact >= 0 ? '#15803d' : '#b91c1c' }}">
                                    {{ $rec->realized_label }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection
