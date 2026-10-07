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

    @if ($history->isNotEmpty())
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
