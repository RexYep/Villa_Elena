@extends('layouts.admin')

@section('title', 'Audit Log — Villa Elena Admin')
@section('page-title', 'Audit Log')
@section('page-subtitle', 'Who did what, to which record, and when')

@push('styles')
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 16px 18px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 10px;
            background: var(--gold-dim);
            color: var(--gold);
        }

        .stat-val {
            font-size: 24px;
            font-weight: 700;
            font-family: 'Cormorant Garamond', serif;
            color: var(--stone);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .audit-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .audit-tab {
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }

        .audit-tab.active {
            color: var(--terracotta);
            border-bottom-color: var(--terracotta);
        }

        .filter-card {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 14px 18px;
            margin-bottom: 20px;
        }

        .form-label-sm {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .form-select-sm,
        .form-control-sm {
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 7px 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: var(--text-main);
            width: 100%;
        }

        .form-select-sm:focus,
        .form-control-sm:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        .btn-filter {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-clear {
            display: inline-block;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 7px 16px;
            font-size: 14px;
            color: var(--muted);
            text-decoration: none;
        }

        /* A one-click jump to the rows that matter after an incident. */
        .btn-security {
            display: inline-block;
            border: 1.5px solid #fca5a5;
            background: #fef2f2;
            color: #b91c1c;
            border-radius: 7px;
            padding: 7px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-security.on {
            background: #b91c1c;
            border-color: #b91c1c;
            color: #fff;
        }

        .audit-table {
            width: 100%;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }

        .audit-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            font-weight: 600;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            background: #fff;
            white-space: nowrap;
        }

        .audit-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: top;
        }

        .audit-table tr:last-child td {
            border-bottom: none;
        }

        .audit-table tr.security td {
            background: #fffbfb;
        }

        .when {
            white-space: nowrap;
            color: var(--muted);
            font-size: 13px;
        }

        .when strong {
            display: block;
            color: var(--text-main);
            font-weight: 600;
        }

        .actor {
            font-weight: 600;
            white-space: nowrap;
        }

        .actor .role {
            display: block;
            font-weight: 400;
            font-size: 12px;
            color: var(--muted);
            text-transform: capitalize;
        }

        .actor.system {
            font-weight: 400;
            color: var(--muted);
            font-style: italic;
        }

        .act-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
            white-space: nowrap;
        }

        .act-badge.sec {
            background: #fee2e2;
            color: #b91c1c;
        }

        .target-link {
            font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
            font-size: 12px;
            color: var(--terracotta);
            text-decoration: none;
            white-space: nowrap;
        }

        .target-link:hover {
            text-decoration: underline;
        }

        .target-none {
            color: var(--muted);
            font-size: 12px;
        }

        .desc {
            color: var(--text-main);
            line-height: 1.45;
            min-width: 260px;
        }

        .meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
        }

        .diff {
            margin-top: 7px;
            border-left: 2px solid var(--border);
            padding-left: 9px;
        }

        .diff-row {
            font-size: 12px;
            line-height: 1.5;
        }

        .diff-key {
            font-weight: 600;
            color: var(--text-main);
        }

        .diff-old {
            color: #b91c1c;
            text-decoration: line-through;
        }

        .diff-new {
            color: #15803d;
        }

        .empty-state {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 34px;
            display: block;
            margin-bottom: 10px;
            opacity: .5;
        }

        .readonly-note {
            font-size: 12px;
            color: var(--muted);
            margin: 14px 2px 0;
        }

        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
@endpush

@section('content')

    {{-- Successful sign-ins live in `login_activities`, not `staff_logs` —
         a different table rather than a different filter, hence a tab
         rather than a dropdown option. See F11. --}}
    <div class="audit-tabs">
        <a href="{{ route('admin.audit.index') }}" class="audit-tab active">Action Log</a>
        <a href="{{ route('admin.audit.signins') }}" class="audit-tab">Sign-in History</a>
    </div>

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f1f5f9;color:#475569;"><i class="bi bi-list-ul"></i></div>
            <div class="stat-val">{{ number_format($stats['total']) }}</div>
            <div class="stat-lbl">Entries Recorded</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-val">{{ number_format($stats['today']) }}</div>
            <div class="stat-lbl">Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#b91c1c;"><i class="bi bi-shield-exclamation"></i></div>
            <div class="stat-val">{{ number_format($stats['failed_logins']) }}</div>
            <div class="stat-lbl">Failed Logins (24h)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3;color:#b45309;"><i class="bi bi-key"></i></div>
            <div class="stat-val">{{ number_format($stats['security_24h']) }}</div>
            <div class="stat-lbl">Security Events (24h)</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.audit.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-auto" style="min-width:210px;">
                    <label class="form-label-sm">Action</label>
                    <select name="action" class="form-select-sm">
                        <option value="">All actions</option>
                        @foreach ($actions as $act)
                            <option value="{{ $act }}" @selected(request('action') === $act)>
                                {{ ucfirst(str_replace('_', ' ', $act)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto" style="min-width:190px;">
                    <label class="form-label-sm">Performed by</label>
                    <select name="user_id" class="form-select-sm">
                        <option value="">Anyone</option>
                        {{-- `0` is the sentinel for a NULL user_id; an empty
                             string already means "no filter". --}}
                        <option value="0" @selected(request('user_id') === '0')>System / not signed in</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected(request('user_id') == $actor->id)>
                                {{ $actor->full_name }} ({{ $actor->role }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto" style="min-width:150px;">
                    <label class="form-label-sm">Record type</label>
                    <select name="target_table" class="form-select-sm">
                        <option value="">Any record</option>
                        @foreach ($tables as $t)
                            <option value="{{ $t }}" @selected(request('target_table') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto" style="min-width:110px;">
                    <label class="form-label-sm">Record ID</label>
                    <input type="number" name="target_id" value="{{ request('target_id') }}"
                           class="form-control-sm" placeholder="e.g. 42">
                </div>

                <div class="col-auto" style="min-width:150px;">
                    <label class="form-label-sm">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control-sm">
                </div>

                <div class="col-auto" style="min-width:150px;">
                    <label class="form-label-sm">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control-sm">
                </div>

                <div class="col-auto" style="min-width:200px;">
                    <label class="form-label-sm">Search description</label>
                    <input type="text" name="q" value="{{ request('q') }}"
                           class="form-control-sm" placeholder="booking ref, email…">
                </div>

                @if (request('security'))
                    <input type="hidden" name="security" value="1">
                @endif

                <div class="col-auto">
                    <button type="submit" class="btn-filter">Filter</button>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.audit.index') }}" class="btn-clear">Clear</a>
                </div>
                <div class="col-auto">
                    <a href="{{ request('security')
                            ? route('admin.audit.index', collect(request()->query())->except('security', 'page')->all())
                            : route('admin.audit.index', array_merge(collect(request()->query())->except('page')->all(), ['security' => 1])) }}"
                       class="btn-security {{ request('security') ? 'on' : '' }}">
                        <i class="bi bi-shield-check"></i>
                        Security only
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Entries --}}
    @if ($logs->isEmpty())
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <p style="font-size:15px;font-weight:500;margin-bottom:4px;">No entries match those filters</p>
            <p style="font-size:13px;">Try widening the date range, or clear the filters to see everything.</p>
        </div>
    @else
        <table class="audit-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Record</th>
                    <th>What happened</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="{{ $log->is_security_event ? 'security' : '' }}">
                        <td class="when">
                            <strong>{{ $log->created_at?->format('M d, Y') ?? '—' }}</strong>
                            {{ $log->created_at?->format('g:i:s A') }}
                        </td>

                        <td>
                            @if ($log->user)
                                <span class="actor">
                                    {{ $log->user->full_name }}
                                    <span class="role">{{ $log->user->role }}</span>
                                </span>
                            @else
                                {{-- Never just "System": a failed login is not
                                     the scheduler. See StaffLog::actorLabel(). --}}
                                <span class="actor system">{{ $log->actor_label }}</span>
                            @endif
                        </td>

                        <td>
                            <span class="act-badge {{ $log->is_security_event ? 'sec' : '' }}">
                                {{ $log->action_label }}
                            </span>
                        </td>

                        <td>
                            @if ($log->target_table && $log->target_id)
                                {{-- Clicking pivots the whole log to that one
                                     record — "everything that happened to
                                     booking 42" is the question this page is
                                     most often opened to answer. --}}
                                <a class="target-link"
                                   href="{{ route('admin.audit.index', ['target_table' => $log->target_table, 'target_id' => $log->target_id]) }}">
                                    {{ $log->target_table }}#{{ $log->target_id }}
                                </a>
                            @elseif ($log->target_table)
                                <span class="target-none">{{ $log->target_table }} <em>(no id recorded)</em></span>
                            @else
                                <span class="target-none">—</span>
                            @endif
                        </td>

                        <td class="desc">
                            {{ $log->description ?: '—' }}

                            @php $changes = $log->changedValues(); @endphp
                            @if ($changes)
                                <div class="diff">
                                    @foreach ($changes as $field => [$before, $after])
                                        <div class="diff-row">
                                            <span class="diff-key">{{ $field }}</span>:
                                            <span class="diff-old">{{ is_scalar($before) || $before === null ? (($before ?? '') === '' ? 'empty' : $before) : json_encode($before) }}</span>
                                            →
                                            <span class="diff-new">{{ is_scalar($after) || $after === null ? (($after ?? '') === '' ? 'empty' : $after) : json_encode($after) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="meta">
                                {{ $log->ip_address ?: 'no ip' }}
                                @if ($log->user_agent)
                                    · {{ Str::limit($log->user_agent, 64) }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:20px;">{{ $logs->links() }}</div>
    @endif

    <p class="readonly-note">
        <i class="bi bi-lock"></i>
        This log is read-only — entries cannot be edited or deleted from the admin panel.
        Nothing is pruned automatically, so older entries stay available.
        Successful logins are under <a href="{{ route('admin.audit.signins') }}">Sign-in History</a>.
    </p>

@endsection
