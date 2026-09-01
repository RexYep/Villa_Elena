@extends('layouts.admin')

@section('title', $user->full_name . ' — Villa Elena Admin')
@section('page-title', 'Guest Profile')
@section('page-subtitle', $user->full_name)

@section('topbar-right')
    <a href="{{ route('admin.users.index') }}" class="text-muted-theme"
        style="display:flex;align-items:center;gap:6px;text-decoration:none;font-size:13px;border:1px solid var(--border);padding:7px 14px;border-radius:9px;background:#fff;">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
@endsection

@push('styles')
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: start;
        }

        .card-panel {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header-custom {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header-custom h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-main);
        }

        .card-body-custom {
            padding: 20px 24px;
        }

        /* Profile Card — hero block uses terracotta, matching booking-header/price-preview convention */
        .profile-hero {
            background: var(--terracotta);
            padding: 28px 24px;
            text-align: center;
        }

        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            margin: 0 auto 12px;
            border: 3px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .profile-name {
            font-family: 'Cormorant Garamond', serif;
            color: #fff;
            font-size: 20px;
            font-weight: 700;
        }

        .profile-email {
            color: rgba(255, 255, 255, 0.65);
            font-size: 14px;
            margin-top: 3px;
        }

        /* Role badges — semantic, unchanged */
        .role-badge {
            margin-top: 8px;
        }

        .role-admin {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .role-staff {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .role-customer {
            background: var(--tag-purple-bg);
            color: var(--tag-purple-fg);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 16px;
        }

        .mini-stat {
            background: var(--sand);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .mini-stat .val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
        }

        .mini-stat .lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Info rows */
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row .info-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .info-row .info-label {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .info-row .info-value {
            font-weight: 500;
            color: var(--text-main);
        }

        /* Action Buttons */
        .profile-actions {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-top: 1px solid var(--border);
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 9px;
            padding: 9px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            border: none;
            width: 100%;
        }

        .btn-edit {
            background: var(--terracotta);
            color: #fff;
        }

        .btn-edit:hover {
            background: var(--gold);
            color: #fff;
        }

        .btn-toggle-active {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .btn-toggle-active:hover {
            background: #16a34a;
            color: #fff;
            border-color: #16a34a;
        }

        .btn-toggle-inactive {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-toggle-inactive:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }

        /* Status badges — semantic, unchanged */
        .s-pending {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .s-confirmed {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .s-checked_in {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .s-checked_out {
            background: var(--tag-slate-bg);
            color: var(--tag-slate-fg);
        }

        .s-cancelled {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .p-unpaid {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .p-partial {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .p-paid {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        @media (max-width: 900px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="profile-grid">

        {{-- LEFT: Profile Card --}}
        <div>
            <div class="card-panel">
                {{-- Hero --}}
                <div class="profile-hero">
                    @php
                        $colors = ['#1d4ed8', '#7c3aed', '#15803d', '#c9a84c', '#dc2626', '#0369a1'];
                        $color = $colors[ord(strtolower($user->full_name[0])) % count($colors)];
                    @endphp
                    <div class="profile-avatar" style="background:{{ $color }}33;color:{{ $color }};">
                        {{ strtoupper(substr($user->full_name, 0, 1)) }}
                    </div>
                    <div class="profile-name">{{ $user->full_name }}</div>
                    <div class="profile-email">{{ $user->email }}</div>
                    <span class="role-badge role-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                    @if (!$user->status)
                        <div style="margin-top:8px;">
                            <span class="tag-red"
                                style="padding:3px 10px;border-radius:20px;font-size: 13px;font-weight:600;">Inactive</span>
                        </div>
                    @endif
                </div>

                {{-- Mini Stats --}}
                <div class="stats-grid">
                    <div class="mini-stat">
                        <div class="val">{{ $stats['total_bookings'] }}</div>
                        <div class="lbl">Total Bookings</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val">{{ $stats['completed'] }}</div>
                        <div class="lbl">Completed</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val">{{ $stats['cancelled'] }}</div>
                        <div class="lbl">Cancelled</div>
                    </div>
                    <div class="mini-stat">
                        <div class="val" style="font-size:18px;">₱{{ number_format($stats['total_spent'], 0) }}</div>
                        <div class="lbl">Total Spent</div>
                    </div>
                </div>

                {{-- Info Rows --}}
                <div style="padding:16px 20px;">
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-telephone"></i></div>
                        <div>
                            <div class="info-label">Phone</div>
                            <div class="info-value">{{ $user->phone ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <div class="info-label">Address</div>
                            <div class="info-value">{{ $user->address ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-card-text"></i></div>
                        <div>
                            <div class="info-label">ID Type</div>
                            <div class="info-value">{{ $user->id_type ?? '—' }}</div>
                        </div>
                    </div>
                    @if ($user->id_number)
                        <div class="info-row">
                            <div class="info-icon"><i class="bi bi-upc"></i></div>
                            <div>
                                <div class="info-label">ID Number</div>
                                <div class="info-value">{{ $user->id_number }}</div>
                            </div>
                        </div>
                    @endif
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="info-label">Last Login</div>
                            <div class="info-value">
                                {{ $user->last_login ? $user->last_login->format('M d, Y h:i A') : 'Never' }}
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-calendar-plus"></i></div>
                        <div>
                            <div class="info-label">Registered</div>
                            <div class="info-value">{{ $user->created_at->format('M d, Y') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="profile-actions">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-action btn-edit">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </a>
                    @if ($user->id !== Auth::id())
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="m-0">
                            @csrf @method('PATCH')
                            <button type="submit"
                                class="btn-action {{ $user->status ? 'btn-toggle-inactive' : 'btn-toggle-active' }}">
                                <i class="bi bi-{{ $user->status ? 'person-dash' : 'person-check' }}"></i>
                                {{ $user->status ? 'Deactivate Account' : 'Activate Account' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT: Booking History --}}
        <div>
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Booking History</h3>
                    <a href="{{ route('admin.bookings.index', ['search' => $user->email]) }}"
                        style="font-size: 14px;color:#2e5fa3;text-decoration:none;font-weight:500;">
                        View all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body-custom" style="padding:0;">
                    @if ($bookings->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"
                                style="font-size:32px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No bookings yet
                        </div>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking Ref</th>
                                    <th>Property</th>
                                    <th>Check-in</th>
                                    <th>Nights</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bookings as $booking)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.bookings.show', $booking) }}"
                                                style="font-weight:600;color:var(--stone);text-decoration:none;">
                                                {{ $booking->booking_ref }}
                                            </a>
                                        </td>
                                        <td>{{ $booking->property->property_name ?? 'N/A' }}</td>
                                        <td class="text-muted-theme" style="font-size: 14px;">
                                            {{ $booking->check_in_date->format('M d, Y') }}
                                        </td>
                                        <td class="text-center">{{ $booking->num_nights }}</td>
                                        <td class="fw-medium">₱{{ number_format($booking->total_amount, 2) }}</td>
                                        <td><span
                                                class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                                        </td>
                                        <td><span
                                                class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

