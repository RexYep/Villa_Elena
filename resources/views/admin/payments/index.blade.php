{{-- SAVE AS: resources/views/admin/payments/index.blade.php --}}

@php
    $pageTitle = 'Payments';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payments — Villa Elena Admin</title>

    {{-- SAME FONTS as dashboard --}}
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* ── SAME ROOT VARIABLES as dashboard ── */
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
            --navy-light: #243b55;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dim:   rgba(201,168,76,0.15);
            --white:      #ffffff;
            --off-white:  #f4f6f9;
            --text-main:  #1a2f45;
            --text-muted: #6b7a8d;
            --border:     #e2e8f0;
            --sidebar-w:  260px;   /* <-- FIXED: was --sidebar: 260px */
            --topbar-h:   68px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--off-white);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* ── SIDEBAR — same as dashboard ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
            transition: transform .3s ease;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-brand h1 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--gold-light);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }
        .sidebar-brand p {
            color: rgba(255,255,255,0.35);
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .sidebar-section { padding: 20px 16px 8px; }
        .sidebar-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            padding: 0 8px;
            margin-bottom: 6px;
        }
        .nav-item-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all .2s;
            margin-bottom: 2px;
        }
        .nav-item-custom:hover {
            background: rgba(255,255,255,0.07);
            color: var(--white);
        }
        .nav-item-custom.active {
            background: var(--gold-dim);
            color: var(--gold-light);
            font-weight: 500;
        }
        .nav-item-custom .nav-icon {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            background: rgba(255,255,255,0.05);
        }
        .nav-item-custom.active .nav-icon {
            background: var(--gold-dim);
            color: var(--gold);
        }
        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .user-card {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
        }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--gold-dim);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 600;
            flex-shrink: 0;
        }
        .user-info .name   { color: var(--white); font-size: 13px; font-weight: 500; }
        .user-info .role-badge {
            font-size: 10px; color: var(--gold);
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        /* ── TOPBAR — same as dashboard ── */
        .topbar {
            position: fixed;
            top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px;
            z-index: 900;
        }
        .topbar-left h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px; font-weight: 600; color: var(--text-main);
        }
        .topbar-left p { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .logout-btn {
            display: flex; align-items: center; gap: 7px;
            background: #fef2f2; color: #ef4444;
            border: 1px solid #fecaca; border-radius: 9px;
            padding: 7px 14px; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: all .2s; text-decoration: none;
        }
        .logout-btn:hover { background: #ef4444; color: white; border-color: #ef4444; }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 32px;
            min-height: calc(100vh - var(--topbar-h));
        }

        /* ── STAT CARDS ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 20px 22px;
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.07); }
        .stat-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; margin-bottom: 12px;
        }
        .stat-val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px; font-weight: 700;
            color: var(--navy); line-height: 1;
        }
        .stat-lbl { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        /* ── FILTER CARD ── */
        .filter-card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 22px;
            margin-bottom: 20px;
        }
        .form-label-sm {
            font-size: 11px; font-weight: 600; color: #374151;
            display: block; margin-bottom: 5px;
            text-transform: uppercase; letter-spacing: .3px;
        }

        /* ── TABLE CARD ── */
        .table-card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .table-card-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .table-card-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px; font-weight: 600; color: var(--navy);
        }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        thead th {
            padding: 11px 16px;
            font-size: 11px; font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid #f8fafc; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafbfc; }
        tbody td { padding: 13px 16px; font-size: 13px; vertical-align: middle; }

        /* ── BADGES ── */
        .badge {
            padding: 3px 10px; border-radius: 20px;
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .3px;
            white-space: nowrap;
        }
        .b-cash        { background:#dcfce7; color:#15803d; }
        .b-gcash       { background:#dbeafe; color:#1d4ed8; }
        .b-bank_transfer { background:#f3e8ff; color:#7c3aed; }
        .b-credit_card { background:#fef9c3; color:#a16207; }
        .b-online      { background:#e0f2fe; color:#0369a1; }
        .b-paymaya     { background:#dcfce7; color:#15803d; }
        .b-grab_pay    { background:#dcfce7; color:#15803d; }
        .b-deposit     { background:#f0fdf4; color:#16a34a; }
        .b-full_payment { background:#dcfce7; color:#15803d; }
        .b-partial     { background:#fef9c3; color:#a16207; }
        .b-balance     { background:#dbeafe; color:#1d4ed8; }
        .b-refund      { background:#fee2e2; color:#dc2626; }

        /* ── BUTTONS ── */
        .btn-navy {
            background: var(--navy); color: #fff;
            border: none; border-radius: 9px;
            padding: 9px 18px; font-size: 13px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all .2s; text-decoration: none;
        }
        .btn-navy:hover { background: var(--gold); color: var(--navy); }
        .view-link { color: var(--gold); text-decoration: none; font-weight: 600; font-size: 12px; }
        .view-link:hover { color: var(--navy); }
        .refund-link { color: #dc2626; text-decoration: none; font-weight: 600; font-size: 12px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .modal-box {
            background: #fff; border-radius: 18px;
            width: 480px; max-width: calc(100vw - 32px);
            overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,.2);
        }
        .modal-head {
            background: var(--navy); padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-title { font-family: 'Cormorant Garamond', serif; color: #fff; font-size: 18px; font-weight: 600; }
        .modal-close {
            background: rgba(255,255,255,.1); border: none; color: #fff;
            width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 15px;
        }
        .modal-body { padding: 22px; }
        .form-label { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
        .form-control, .form-select {
            border: 1.5px solid var(--border); border-radius: 8px;
            padding: 10px 14px; font-size: 13px;
            font-family: 'DM Sans', sans-serif; width: 100%;
            background: #fff; transition: border-color .2s;
        }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--navy); }
        .btn-submit {
            background: var(--navy); color: #fff; border: none;
            border-radius: 9px; padding: 12px; width: 100%;
            font-size: 14px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; margin-top: 8px; transition: all .2s;
        }
        .btn-submit:hover { background: var(--gold); color: var(--navy); }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .mb-12 { margin-bottom: 12px; }

        .alert { border-radius: 10px; font-size: 13px; padding: 12px 16px; border: none; margin-bottom: 18px; }
        .alert-success { background: #dcfce7; color: #15803d; }
        .alert-danger  { background: #fee2e2; color: #dc2626; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
        .empty-state i { font-size: 44px; display: block; margin-bottom: 12px; opacity: .35; }
        .pagination .page-link { border-radius: 7px; font-size: 13px; color: var(--navy); border-color: var(--border); }
        .pagination .page-item.active .page-link { background: var(--navy); border-color: var(--navy); }
    </style>
</head>
<body>

{{-- SIDEBAR PARTIAL --}}
@include('admin.partials.sidebar')

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <h2>Payments</h2>
        <p>All transactions and payment records</p>
    </div>
    <div class="topbar-right">
        <button class="btn-navy" onclick="openRecordModal()">
            <i class="bi bi-plus-lg"></i> Record Payment
        </button>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </form>
    </div>
</div>

{{-- MAIN CONTENT --}}
<div class="main-content">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stat Cards --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-val">₱{{ number_format($totalRevenue, 2) }}</div>
            <div class="stat-lbl">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-val">₱{{ number_format($todayRevenue, 2) }}</div>
            <div class="stat-lbl">Today's Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-val">₱{{ number_format($monthRevenue, 2) }}</div>
            <div class="stat-lbl">This Month</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7; color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">₱{{ number_format($pendingBalance, 2) }}</div>
            <div class="stat-lbl">Pending Balance</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="bi bi-arrow-counterclockwise"></i></div>
            <div class="stat-val">₱{{ number_format($totalRefunds, 2) }}</div>
            <div class="stat-lbl">Total Refunds</div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.payments.index') }}">
            <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr auto auto; gap:12px; align-items:end;">
                <div>
                    <label class="form-label-sm">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Booking ref or guest name"
                        value="{{ request('search') }}">
                </div>
                <div>
                    <label class="form-label-sm">Method</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="cash"          {{ request('method')=='cash' ? 'selected' : '' }}>Cash</option>
                        <option value="gcash"         {{ request('method')=='gcash' ? 'selected' : '' }}>GCash</option>
                        <option value="bank_transfer" {{ request('method')=='bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="credit_card"   {{ request('method')=='credit_card' ? 'selected' : '' }}>Credit Card</option>
                        <option value="online"        {{ request('method')=='online' ? 'selected' : '' }}>Online</option>
                        <option value="paymaya"       {{ request('method')=='paymaya' ? 'selected' : '' }}>PayMaya</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="deposit"      {{ request('type')=='deposit' ? 'selected' : '' }}>Deposit</option>
                        <option value="full_payment" {{ request('type')=='full_payment' ? 'selected' : '' }}>Full Payment</option>
                        <option value="partial"      {{ request('type')=='partial' ? 'selected' : '' }}>Partial</option>
                        <option value="balance"      {{ request('type')=='balance' ? 'selected' : '' }}>Balance</option>
                        <option value="refund"       {{ request('type')=='refund' ? 'selected' : '' }}>Refund</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div>
                    <label class="form-label-sm">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div style="padding-top:18px;">
                    <button type="submit" class="btn-navy" style="padding:8px 16px; font-size:12px;">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div style="padding-top:18px;">
                    <a href="{{ route('admin.payments.index') }}"
                       style="background:#fff; color:var(--text-muted); border:1.5px solid var(--border);
                              border-radius:7px; padding:8px 14px; font-size:12px; text-decoration:none;
                              display:inline-block;">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Payment Records Table --}}
    <div class="table-card">
        <div class="table-card-header">
            <h3>Payment Records</h3>
            <span style="font-size:12px; color:var(--text-muted);">{{ $payments->total() }} records</span>
        </div>

        @if($payments->isEmpty())
            <div class="empty-state">
                <i class="bi bi-credit-card-2-back"></i>
                No payment records found.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Booking Ref</th>
                        <th>Guest</th>
                        <th>Property</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td style="color:var(--text-muted); font-size:12px;">
                            {{ $payment->created_at->format('M d, Y') }}
                        </td>
                        <td>
                            <strong style="font-size:13px;">{{ $payment->booking->booking_ref ?? 'N/A' }}</strong>
                        </td>
                        <td>{{ $payment->booking->user->full_name ?? 'N/A' }}</td>
                        <td>{{ $payment->booking->property->property_name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge b-{{ $payment->payment_method }}">
                                {{ strtoupper($payment->payment_method) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge b-{{ $payment->payment_type }}">
                                {{ strtoupper(str_replace('_', ' ', $payment->payment_type)) }}
                            </span>
                        </td>
                        <td>
                            <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                        </td>
                        <td style="color:var(--text-muted); font-size:12px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $payment->notes ?? '—' }}
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="{{ route('admin.bookings.show', $payment->booking_id) }}" class="view-link me-2">View</a>
                            @if($payment->payment_type !== 'refund')
                                <a href="#" class="refund-link"
                                   onclick="openRefundModal({{ $payment->id }}, {{ $payment->amount }}, '{{ $payment->booking->booking_ref ?? '' }}')">
                                   Refund
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($payments->hasPages())
                <div style="padding:16px 22px;">
                    {{ $payments->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

</div>{{-- end main-content --}}


{{-- Record Payment Modal --}}
<div class="modal-overlay" id="recordModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">Record Payment</div>
            <button class="modal-close" onclick="closeModal('recordModal')">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.store') }}" class="modal-body">
            @csrf
            <div class="mb-12">
                <label class="form-label">Booking Reference</label>
                <input type="text" name="booking_ref" class="form-control"
                    placeholder="e.g. VE-XXXXXXXX" autocomplete="off"
                    oninput="lookupBooking(this.value)">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                <div id="bookingInfo" style="margin-top:8px; font-size:12px; color:var(--text-muted); display:none; background:#f8fafc; border-radius:8px; padding:10px 12px;"></div>
            </div>
            <div class="two-col mb-12">
                <div>
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" required placeholder="0.00">
                </div>
                <div>
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>
            <div class="two-col mb-12">
                <div>
                    <label class="form-label">Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="online">Online</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Type</label>
                    <select name="payment_type" class="form-select" required>
                        <option value="deposit">Deposit</option>
                        <option value="full_payment">Full Payment</option>
                        <option value="partial">Partial</option>
                        <option value="balance">Balance</option>
                    </select>
                </div>
            </div>
            <div class="mb-12">
                <label class="form-label">Notes <span style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Cash received at frontdesk">
            </div>
            <button type="submit" class="btn-submit"><i class="bi bi-check-circle me-2"></i> Record Payment</button>
        </form>
    </div>
</div>

{{-- Refund Modal --}}
<div class="modal-overlay" id="refundModal">
    <div class="modal-box">
        <div class="modal-head" style="background:#dc2626;">
            <div class="modal-title">Issue Refund</div>
            <button class="modal-close" onclick="closeModal('refundModal')">✕</button>
        </div>
        <form method="POST" id="refundForm" class="modal-body">
            @csrf
            <div style="background:#fee2e2; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#dc2626;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Refund for booking <strong id="refundBookingRef"></strong>
            </div>
            <div class="mb-12">
                <label class="form-label">Refund Amount (₱)</label>
                <input type="number" name="refund_amount" id="refundAmountInput" class="form-control" min="1" step="0.01" required>
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Max: ₱<span id="refundMax"></span></div>
            </div>
            <div class="mb-12">
                <label class="form-label">Reason for Refund</label>
                <textarea name="refund_reason" class="form-control" rows="2" required minlength="5"
                    placeholder="e.g. Guest cancelled 48 hours before check-in"></textarea>
            </div>
            <button type="submit" style="background:#dc2626; color:#fff; border:none; border-radius:9px; padding:12px; width:100%; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
                <i class="bi bi-arrow-counterclockwise me-2"></i> Process Refund
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openRecordModal() {
    document.getElementById('recordModal').style.display = 'flex';
}
function openRefundModal(paymentId, amount, bookingRef) {
    document.getElementById('refundBookingRef').textContent = bookingRef;
    document.getElementById('refundMax').textContent        = parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('refundAmountInput').max        = amount;
    document.getElementById('refundAmountInput').value      = amount;
    document.getElementById('refundForm').action            = `/villa-elena/public/admin/payments/${paymentId}/refund`;
    document.getElementById('refundModal').style.display    = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
['recordModal','refundModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});

let lookupTimer;
function lookupBooking(ref) {
    clearTimeout(lookupTimer);
    if (ref.length < 6) { document.getElementById('bookingInfo').style.display = 'none'; return; }
    lookupTimer = setTimeout(() => {
        fetch(`/villa-elena/public/admin/bookings/lookup?ref=${ref}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.id) {
                document.getElementById('bookingIdInput').value = data.id;
                const info = document.getElementById('bookingInfo');
                info.style.display = 'block';
                info.innerHTML = `<strong>${data.guest}</strong> · ${data.property} · Balance: <strong style="color:#dc2626;">₱${parseFloat(data.balance).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>`;
            }
        }).catch(() => {});
    }, 400);
}

setTimeout(() => { document.querySelectorAll('.alert').forEach(a => a.style.display='none'); }, 5000);
</script>
@include('admin.partials.realtime') 
</body>
</html>