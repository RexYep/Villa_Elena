@extends('layouts.admin')

@section('title', 'Recommendations — Villa Elena Admin')
@section('page-title', 'Recommendations')
@section('page-subtitle', 'Prescriptive analytics — what to do next, and what it is worth')

@section('topbar-right')
    <a href="{{ route('admin.prescriptive.accuracy') }}" class="btn-refresh">
        <i class="bi bi-bullseye"></i> Accuracy
    </a>
    <a href="{{ route('admin.prescriptive.simulate') }}" class="btn-refresh">
        <i class="bi bi-sliders"></i> What-If Simulator
    </a>
    <form method="POST" action="{{ route('admin.prescriptive.regenerate') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn-refresh">
            <i class="bi bi-arrow-clockwise"></i> Recalculate
        </button>
    </form>
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

        .briefing {
            background: var(--sand);
            border: 1px solid var(--border);
            border-left: 3px solid var(--terracotta);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .briefing-label {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .briefing p {
            font-size: 14.5px;
            line-height: 1.75;
            color: var(--stone);
            margin: 0;
        }

        .briefing-foot {
            font-size: 12px;
            color: var(--muted);
            margin-top: 10px;
            line-height: 1.6;
        }

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .summary-tile {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .summary-tile .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .summary-tile .value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--terracotta);
            line-height: 1.1;
        }

        .summary-tile .sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .rec-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            margin-bottom: 18px;
            overflow: hidden;
        }

        .rec-head {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 24px 0;
        }

        .rec-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 21px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.25;
            margin-bottom: 8px;
        }

        .rec-impact {
            margin-left: auto;
            text-align: right;
            flex-shrink: 0;
        }

        .rec-impact .amount {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--terracotta);
            line-height: 1;
        }

        .rec-impact .caption {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-top: 4px;
        }

        .rec-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .tag {
            font-size: 11.5px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: #e2e8f0;
            color: #475569;
        }

        .tag.promo { background: #fef3c7; color: #92400e; }
        .tag.rate  { background: #dcfce7; color: #15803d; }
        .tag.block { background: #dbeafe; color: #1d4ed8; }
        .tag.conf-high { background: #dcfce7; color: #15803d; }
        .tag.conf-medium { background: #fef3c7; color: #92400e; }
        .tag.conf-low { background: #fee2e2; color: #b91c1c; }

        .rec-summary {
            padding: 0 24px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--stone);
        }

        .rec-why {
            margin: 16px 24px 0;
            background: var(--sand);
            border-radius: 10px;
            padding: 14px 18px;
        }

        .rec-why h4 {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .rec-why ul {
            margin: 0;
            padding-left: 18px;
        }

        .rec-why li {
            font-size: 13px;
            line-height: 1.75;
            color: var(--stone);
        }

        .rec-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 24px 20px;
        }

        .btn-apply {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-apply:hover { background: var(--gold); }

        .btn-dismiss {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-dismiss:hover { color: var(--text-main); border-color: var(--muted); }

        .rec-note {
            margin-left: auto;
            font-size: 12px;
            color: var(--muted);
        }

        .guest-warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 9px;
            padding: 12px 14px;
            font-size: 13px;
            color: #9a3412;
            line-height: 1.6;
            margin-top: 14px;
        }

        .effect-list {
            margin: 0;
            padding-left: 18px;
            font-size: 14px;
            line-height: 1.8;
            color: var(--stone);
        }

        .history-note {
            font-size: 12px;
            color: var(--muted);
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

    <div class="intro-note">
        Every card below is <strong>computed from this resort's own booking history</strong> — the fill rate of each
        day-and-slot combination, the live villa rate, and the assumptions under
        <strong>Settings → Prescriptive Engine</strong>. Nothing here is applied automatically:
        until you press <strong>Apply</strong>, no promo exists, no dates are blocked, and
        <strong>guests see nothing at all</strong>. Recalculated nightly.
    </div>

    @if (!empty($briefing))
        {{-- Ang AI ay nagsusulat lang dito. Bawat numerong nababanggit ay
             kinompyut na bago pa marating ang modelo — walang ipinapasyang
             bago rito. Kapag bumagsak ang Groq, nawawala lang ang kahong
             ito at buo pa rin ang page. --}}
        <div class="briefing">
            <div class="briefing-label"><i class="bi bi-chat-quote"></i> Morning briefing</div>
            <p>{{ $briefing }}</p>
            <div class="briefing-foot">
                Written by AI from the computed figures above — it summarises and prioritises the recommendations,
                and cannot add to them.
            </div>
        </div>
    @endif

    <div class="summary-strip">
        <div class="summary-tile">
            <div class="label">Open Recommendations</div>
            <div class="value">{{ $open->count() }}</div>
            <div class="sub">awaiting your decision</div>
        </div>
        <div class="summary-tile">
            <div class="label">Projected Value</div>
            <div class="value">₱{{ number_format($totalImpact, 0) }}</div>
            <div class="sub">if all were applied, over their windows</div>
        </div>
        <div class="summary-tile">
            <div class="label">Last Recalculated</div>
            <div class="value" style="font-size:20px">
                {{ $lastGenerated ? \Carbon\Carbon::parse($lastGenerated)->diffForHumans() : 'Never' }}
            </div>
            <div class="sub">
                {{ $lastGenerated ? \Carbon\Carbon::parse($lastGenerated)->format('M j, Y g:i A') : 'Press Recalculate to run it now' }}
            </div>
        </div>
    </div>

    @forelse ($open as $rec)
        <div class="rec-card">
            <div class="rec-head">
                <div style="flex:1">
                    <div class="rec-tags">
                        <span class="tag {{ $rec->action_tag_class }}">{{ $rec->action_label }}</span>
                        <span class="tag">{{ $rec->window_label }}</span>
                        @if ($rec->slot)
                            <span class="tag">{{ $rec->slot_label }}</span>
                        @endif
                        <span class="tag conf-{{ strtolower($rec->confidence_label) }}">
                            {{ $rec->confidence_label }} confidence · {{ $rec->sample_size }} past days
                        </span>
                    </div>
                    <div class="rec-title">{{ $rec->title }}</div>
                </div>
                <div class="rec-impact">
                    <div class="amount">{{ $rec->impact_label }}</div>
                    <div class="caption">{{ $rec->impact_caption }}</div>
                </div>
            </div>

            <div class="rec-summary">{{ $rec->summary }}</div>

            @if (!empty($rec->evidence))
                <div class="rec-why">
                    <h4>Why this?</h4>
                    <ul>
                        @foreach ($rec->evidence as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rec-actions">
                <button type="button" class="btn-apply" data-bs-toggle="modal" data-bs-target="#applyModal{{ $rec->id }}">
                    <i class="bi bi-check2"></i> Apply
                </button>
                <button type="button" class="btn-dismiss" data-bs-toggle="modal" data-bs-target="#dismissModal{{ $rec->id }}">
                    Dismiss
                </button>
                <span class="rec-note">Nothing changes until you confirm.</span>
            </div>
        </div>

        {{-- Confirmation: sinasabi ang EKSAKTONG mangyayari, hindi "Are you sure?".
             Ang isang aksyong nagbabago ng presyong nakikita ng guest ay dapat
             nababasa nang buo bago ito pindutin. --}}
        <div class="modal fade" id="applyModal{{ $rec->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Apply this recommendation?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @php $p = $rec->action_payload; @endphp

                        @if ($rec->action_type === 'create_promo')
                            <ul class="effect-list">
                                <li>Creates the promo <strong>{{ $p['label'] }}</strong>.</li>
                                <li><strong>{{ rtrim(rtrim(number_format((float) $p['value'], 2), '0'), '.') }}% off</strong>
                                    the villa base rate.</li>
                                <li>Applies to check-ins from
                                    <strong>{{ \Carbon\Carbon::parse($p['start_date'])->format('M j, Y') }}</strong> to
                                    <strong>{{ \Carbon\Carbon::parse($p['expiry_date'])->format('M j, Y') }}</strong>.</li>
                                <li>{{ $rec->slot_label }} only.</li>
                            </ul>
                            <div class="guest-warning">
                                <i class="bi bi-eye"></i> <strong>Guests will see this immediately.</strong>
                                The promo appears on the landing page banner and lowers the price shown during booking.
                                You can switch it off any time under Promotions — bookings already made keep their rate.
                            </div>
                        @elseif ($rec->action_type === 'create_pricing_rule')
                            <ul class="effect-list">
                                <li>Creates the pricing rule <strong>{{ $p['label'] }}</strong>.</li>
                                <li>Rate goes from <strong>₱{{ number_format((float) $p['current_price'], 0) }}</strong>
                                    to <strong>₱{{ number_format((float) $p['price'], 0) }}</strong>
                                    (+{{ rtrim(rtrim(number_format((float) $p['increase_pct'], 2), '0'), '.') }}%).</li>
                                <li>Applies to check-ins from
                                    <strong>{{ \Carbon\Carbon::parse($p['start_date'])->format('M j, Y') }}</strong> to
                                    <strong>{{ \Carbon\Carbon::parse($p['end_date'])->format('M j, Y') }}</strong>.</li>
                                <li>Set as a <strong>fixed amount</strong>, not a percentage — a percentage rule is
                                    calculated off the base rate and would <em>lower</em> a weekend price.</li>
                            </ul>
                            <div class="guest-warning">
                                <i class="bi bi-cash-coin"></i> <strong>Guests will pay more from now on.</strong>
                                The higher rate shows immediately during booking, and it overrides both the regular
                                and weekend rate for those dates. Bookings already made keep their original price.
                                Edit or deactivate it under Properties → Pricing Rules.
                            </div>
                        @else
                            <ul class="effect-list">
                                <li>Blocks the villa from
                                    <strong>{{ \Carbon\Carbon::parse($p['start_date'])->format('M j, Y') }}</strong> to
                                    <strong>{{ \Carbon\Carbon::parse($p['end_date'])->format('M j, Y') }}</strong>.</li>
                                <li>Reason recorded as <strong>{{ ucfirst($p['reason']) }}</strong>.</li>
                            </ul>
                            <div class="guest-warning">
                                <i class="bi bi-calendar-x"></i> <strong>Those dates stop being bookable.</strong>
                                They disappear from the public calendar right away. Remove the block under
                                Properties → Block Dates if plans change.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('admin.prescriptive.apply', $rec) }}">
                            @csrf
                            <button type="submit" class="btn-apply">Yes, apply it</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="dismissModal{{ $rec->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.prescriptive.dismiss', $rec) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Dismiss this recommendation?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p style="font-size:14px;color:var(--stone);line-height:1.7">
                                It will not be suggested again. Nothing is created or changed.
                            </p>
                            <label class="form-label">Why? <span class="text-muted">(optional)</span></label>
                            <input type="text" name="dismiss_reason" class="form-control" maxlength="255"
                                placeholder="e.g. already have a private event those dates">
                            <span class="history-note">Recorded so the reasoning survives past this week.</span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-dismiss">Dismiss</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="table-card">
            <div class="empty-state">
                <i class="bi bi-lightbulb"></i>
                <p>
                    No open recommendations. That is a valid result — it means no upcoming date is weak enough for a
                    discount to pay for itself, and no maintenance window needs choosing.
                </p>
            </div>
        </div>
    @endforelse

    @if ($history->isNotEmpty())
        <div class="table-card" style="margin-top:26px">
            <div class="table-header">
                <h3>Decision History</h3>
                <span class="history-note">The last {{ $history->count() }} recommendations acted on</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Recommendation</th>
                        <th>Window</th>
                        <th>Projected</th>
                        <th>Realised</th>
                        <th>Outcome</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $rec)
                        <tr>
                            <td>{{ $rec->title }}</td>
                            <td>{{ $rec->window_label }}</td>
                            <td>{{ $rec->impact_label }}</td>
                            <td>
                                @if ($rec->isMeasured())
                                    <span
                                        style="color:{{ (float) $rec->realized_impact >= 0 ? '#15803d' : '#b91c1c' }};font-weight:600">
                                        {{ $rec->realized_label }}
                                    </span>
                                @elseif ($rec->settled_at)
                                    <span class="history-note">not measurable</span>
                                @else
                                    <span class="history-note">window still open</span>
                                @endif
                            </td>
                            <td>
                                @if ($rec->status === 'applied')
                                    <span class="badge bg-success">Applied</span>
                                    @if ($rec->appliedBy)
                                        <span class="history-note">by {{ $rec->appliedBy->full_name }}</span>
                                    @endif
                                @elseif ($rec->status === 'dismissed')
                                    <span class="badge bg-secondary">Dismissed</span>
                                    @if ($rec->dismiss_reason)
                                        <div class="history-note">{{ $rec->dismiss_reason }}</div>
                                    @endif
                                @else
                                    <span class="badge bg-warning">Expired</span>
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
