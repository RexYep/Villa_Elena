@extends('layouts.admin')

@section('title', 'Sign-in History — Villa Elena Admin')
@section('page-title', 'Sign-in History')
@section('page-subtitle', 'Successful logins — who, from where, and on what device')

@push('styles')
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
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

        .btn-toggle {
            display: inline-block;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 7px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
        }

        .btn-toggle.on {
            background: var(--terracotta);
            border-color: var(--terracotta);
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

        .audit-table tr.privileged td {
            background: #fffdf5;
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

        .actor .sub {
            display: block;
            font-weight: 400;
            font-size: 12px;
            color: var(--muted);
        }

        .role-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
            background: #f1f5f9;
            color: #475569;
        }

        .role-tag.admin {
            background: #fef3c7;
            color: #b45309;
        }

        .role-tag.staff {
            background: #e0e7ff;
            color: #4338ca;
        }

        .otp-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .mono {
            font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
            font-size: 12px;
            color: var(--muted);
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

    <div class="audit-tabs">
        <a href="{{ route('admin.audit.index') }}" class="audit-tab">Action Log</a>
        <a href="{{ route('admin.audit.signins') }}" class="audit-tab active">Sign-in History</a>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f1f5f9;color:#475569;"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="stat-val">{{ number_format($stats['total']) }}</div>
            <div class="stat-lbl">Sign-ins Recorded</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-val">{{ number_format($stats['today']) }}</div>
            <div class="stat-lbl">Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-phone"></i></div>
            <div class="stat-val">{{ number_format($stats['new_device']) }}</div>
            <div class="stat-lbl">New Devices (7d)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#b45309;"><i class="bi bi-shield-lock"></i></div>
            <div class="stat-val">{{ number_format($stats['privileged']) }}</div>
            <div class="stat-lbl">Admin / Staff (7d)</div>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET" action="{{ route('admin.audit.signins') }}">
            <div class="row g-2 align-items-end">
                <div class="col-auto" style="min-width:210px;">
                    <label class="form-label-sm">Account</label>
                    <select name="user_id" class="form-select-sm">
                        <option value="">Anyone</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected(request('user_id') == $actor->id)>
                                {{ $actor->full_name }} ({{ $actor->role }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto" style="min-width:150px;">
                    <label class="form-label-sm">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control-sm">
                </div>

                <div class="col-auto" style="min-width:150px;">
                    <label class="form-label-sm">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control-sm">
                </div>

                @if (request('otp'))<input type="hidden" name="otp" value="1">@endif
                @if (request('staff'))<input type="hidden" name="staff" value="1">@endif

                <div class="col-auto">
                    <button type="submit" class="btn-filter">Filter</button>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.audit.signins') }}" class="btn-clear">Clear</a>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.audit.signins', array_merge(collect(request()->query())->except('otp', 'page')->all(), request('otp') ? [] : ['otp' => 1])) }}"
                       class="btn-toggle {{ request('otp') ? 'on' : '' }}">
                        <i class="bi bi-phone"></i> New device only
                    </a>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.audit.signins', array_merge(collect(request()->query())->except('staff', 'page')->all(), request('staff') ? [] : ['staff' => 1])) }}"
                       class="btn-toggle {{ request('staff') ? 'on' : '' }}">
                        <i class="bi bi-shield-lock"></i> Admin / staff only
                    </a>
                </div>
            </div>
        </form>
    </div>

    @if ($signIns->isEmpty())
        <div class="empty-state">
            <i class="bi bi-box-arrow-in-right"></i>
            <p style="font-size:15px;font-weight:500;margin-bottom:4px;">No sign-ins match those filters</p>
            <p style="font-size:13px;">Try widening the date range, or clear the filters to see everything.</p>
        </div>
    @else
        <table class="audit-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Account</th>
                    <th>Role</th>
                    <th>Device</th>
                    <th>IP address</th>
                    <th>Verification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($signIns as $signIn)
                    <tr class="{{ in_array($signIn->user?->role, ['admin', 'staff'], true) ? 'privileged' : '' }}">
                        <td class="when">
                            <strong>{{ $signIn->created_at?->format('M d, Y') ?? '—' }}</strong>
                            {{ $signIn->created_at?->format('g:i:s A') }}
                        </td>
                        <td>
                            <span class="actor">
                                {{ $signIn->user?->full_name ?? 'Deleted account' }}
                                <span class="sub">{{ $signIn->user?->email }}</span>
                            </span>
                        </td>
                        <td>
                            <span class="role-tag {{ $signIn->user?->role }}">{{ $signIn->user?->role ?? '—' }}</span>
                        </td>
                        <td>{{ $signIn->device_label ?: 'Unknown device' }}</td>
                        <td class="mono">{{ $signIn->ip_address ?: 'no ip' }}</td>
                        <td>
                            @if ($signIn->via_new_device_otp)
                                {{-- This login had to pass an emailed code,
                                     which means the account had not been used
                                     from this device before. --}}
                                <span class="otp-tag">Email code</span>
                            @else
                                <span class="mono">known device</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:20px;">{{ $signIns->links() }}</div>
    @endif

    <p class="readonly-note">
        <i class="bi bi-info-circle"></i>
        Successful sign-ins only. Failed attempts, lockouts and two-factor failures are on the
        <a href="{{ route('admin.audit.index', ['security' => 1]) }}">Action Log</a>.
    </p>

@endsection
