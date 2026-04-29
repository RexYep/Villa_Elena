<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Booking {{ $booking->booking_ref }} — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--text-muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}
        .breadcrumb-row .sep{color:#cbd5e1;}
        .breadcrumb-row .current{color:var(--text-main);font-weight:500;}

        /* Layout */
        .detail-grid{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}

        /* Cards */
        .card-panel{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .card-header-custom{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .card-header-custom h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .card-body-custom{padding:20px 24px;}

        /* Booking Header Card */
        .booking-header{background:var(--navy);border-radius:14px;padding:24px 28px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
        .booking-ref{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:28px;font-weight:700;}
        .booking-source{color:rgba(255,255,255,0.5);font-size:12px;margin-top:3px;text-transform:uppercase;letter-spacing:1px;}
        .booking-dates{color:#fff;font-size:14px;}
        .booking-dates strong{display:block;font-size:20px;font-family:'Cormorant Garamond',serif;}

        /* Info Grid */
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .info-item .label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:4px;}
        .info-item .value{font-size:14px;font-weight:500;color:var(--text-main);}

        /* Status Badges */
        .status-badge{padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
        .s-pending   {background:#fef9c3;color:#a16207;}
        .s-confirmed {background:#dcfce7;color:#15803d;}
        .s-checked_in{background:#dbeafe;color:#1d4ed8;}
        .s-checked_out{background:#f1f5f9;color:#475569;}
        .s-cancelled {background:#fee2e2;color:#dc2626;}
        .p-unpaid    {background:#fee2e2;color:#dc2626;}
        .p-partial   {background:#fef9c3;color:#a16207;}
        .p-paid      {background:#dcfce7;color:#15803d;}
        .p-refunded  {background:#e0f2fe;color:#0369a1;}

        /* Action Buttons */
        .status-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
        .btn-status{border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s;display:flex;align-items:center;gap:6px;}
        .btn-status:hover{opacity:.85;}
        .btn-confirm    {background:#16a34a;color:#fff;}
        .btn-checkin    {background:#1d4ed8;color:#fff;}
        .btn-checkout   {background:#475569;color:#fff;}
        .btn-cancel     {background:#ef4444;color:#fff;}
        .btn-noshow     {background:#1e293b;color:#fff;}

        /* Payment Summary */
        .pay-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px;}
        .pay-row:last-child{border-bottom:none;}
        .pay-row.total{font-weight:700;font-size:16px;border-top:2px solid var(--border);margin-top:4px;padding-top:12px;}
        .pay-row.balance{color:#dc2626;font-weight:600;}

        /* Payment History Table */
        .pay-table{width:100%;border-collapse:collapse;font-size:13px;}
        .pay-table th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);padding:8px 0;border-bottom:1px solid var(--border);}
        .pay-table td{padding:10px 0;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        .pay-table tr:last-child td{border-bottom:none;}

        /* Form inside card */
        .form-label-sm{font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;display:block;}
        .form-control-sm-custom{border:1.5px solid var(--border);border-radius:7px;padding:8px 12px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;}
        .form-control-sm-custom:focus{outline:none;border-color:var(--navy-mid);}
        .btn-record{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:600;width:100%;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:4px;}

        /* Alert */
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}
        .alert-danger{background:#fee2e2;color:#dc2626;}

        /* Guest card */
        .guest-avatar{width:48px;height:48px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Booking Detail</h2>
        <p>{{ $booking->booking_ref }} · {{ $booking->property->property_name ?? '' }}</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="{{ route('admin.bookings.index') }}" style="display:flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:13px;border:1px solid var(--border);padding:7px 14px;border-radius:9px;background:#fff;">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</header>

<main class="main-content">

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.bookings.index') }}">Bookings</a>
        <span class="sep">›</span>
        <span class="current">{{ $booking->booking_ref }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Booking Header --}}
    <div class="booking-header">
        <div>
            <div class="booking-ref">{{ $booking->booking_ref }}</div>
            <div class="booking-source">Source: {{ strtoupper(str_replace('_',' ',$booking->source)) }}</div>
        </div>
        <div style="text-align:center;">
            <div class="booking-dates">
                <strong>{{ $booking->check_in_date->format('M d, Y') }}</strong>
                Check-in
            </div>
        </div>
        <div style="color:rgba(255,255,255,0.4);font-size:24px;">→</div>
        <div style="text-align:center;">
            <div class="booking-dates">
                <strong>{{ $booking->check_out_date->format('M d, Y') }}</strong>
                Check-out
            </div>
        </div>
        <div style="text-align:center;">
            <div style="color:rgba(255,255,255,0.6);font-size:12px;margin-bottom:4px;">Duration</div>
            <div style="color:#fff;font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;">{{ $booking->num_nights }}</div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;">night{{ $booking->num_nights != 1 ? 's' : '' }}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <span class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span>
            <span class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span>
        </div>
    </div>

    <div class="detail-grid">

        {{-- LEFT --}}
        <div>

            {{-- Status Actions --}}
            @if(!in_array($booking->status, ['checked_out','cancelled','no_show']))
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Update Status</h3>
                </div>
                <div class="card-body-custom">
                    <div class="status-actions">
                        @if($booking->status === 'pending')
                        <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="confirmed">
                            <button type="submit" class="btn-status btn-confirm">
                                <i class="bi bi-check-circle"></i> Confirm Booking
                            </button>
                        </form>
                        @endif

                        @if($booking->status === 'confirmed')
                        <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="checked_in">
                            <button type="submit" class="btn-status btn-checkin">
                                <i class="bi bi-box-arrow-in-right"></i> Check In
                            </button>
                        </form>
                        @endif

                        @if($booking->status === 'checked_in')
                        <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="checked_out">
                            <button type="submit" class="btn-status btn-checkout">
                                <i class="bi bi-box-arrow-right"></i> Check Out
                            </button>
                        </form>
                        @endif

                        @if(in_array($booking->status, ['pending','confirmed']))
                        <button type="button" class="btn-status btn-cancel" onclick="showCancelModal()">
                            <i class="bi bi-x-circle"></i> Cancel Booking
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Booking Info --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Booking Information</h3>
                    <a href="{{ route('admin.bookings.edit', $booking) }}"
                       style="font-size:12px;color:#2e5fa3;text-decoration:none;font-weight:500;">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                </div>
                <div class="card-body-custom">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Property</div>
                            <div class="value">{{ $booking->property->property_name ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Number of Guests</div>
                            <div class="value">{{ $booking->num_guests }} guest{{ $booking->num_guests != 1 ? 's' : '' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Check-in Date</div>
                            <div class="value">{{ $booking->check_in_date->format('l, F j, Y') }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Check-out Date</div>
                            <div class="value">{{ $booking->check_out_date->format('l, F j, Y') }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Booking Source</div>
                            <div class="value">{{ ucfirst(str_replace('_',' ',$booking->source)) }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Created</div>
                            <div class="value">{{ $booking->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                    </div>
                    @if($booking->special_requests)
                    <div style="margin-top:16px;padding:12px 14px;background:#f8fafc;border-radius:8px;font-size:13px;color:var(--text-muted);">
                        <strong style="color:var(--text-main);display:block;margin-bottom:4px;">Special Requests:</strong>
                        {{ $booking->special_requests }}
                    </div>
                    @endif
                    @if($booking->cancellation_reason)
                    <div style="margin-top:16px;padding:12px 14px;background:#fef2f2;border-radius:8px;font-size:13px;color:#dc2626;">
                        <strong style="display:block;margin-bottom:4px;">Cancellation Reason:</strong>
                        {{ $booking->cancellation_reason }}
                        <span style="color:#94a3b8;font-size:11px;margin-left:8px;">
                            {{ $booking->cancelled_at?->format('M d, Y h:i A') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Guest Info --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Guest Information</h3>
                </div>
                <div class="card-body-custom">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                        <div class="guest-avatar">
                            {{ strtoupper(substr($booking->user->full_name ?? 'G', 0, 1)) }}
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:16px;">{{ $booking->user->full_name ?? 'N/A' }}</div>
                            <div style="font-size:12px;color:var(--text-muted);">{{ $booking->user->email ?? '' }}</div>
                        </div>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Phone</div>
                            <div class="value">{{ $booking->user->phone ?? 'N/A' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">ID Type</div>
                            <div class="value">{{ $booking->user->id_type ?? 'Not provided' }}</div>
                        </div>
                        @if($booking->user->id_number)
                        <div class="info-item">
                            <div class="label">ID Number</div>
                            <div class="value">{{ $booking->user->id_number }}</div>
                        </div>
                        @endif
                        @if($booking->user->address)
                        <div class="info-item" style="grid-column:1/-1;">
                            <div class="label">Address</div>
                            <div class="value">{{ $booking->user->address }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Payment History</h3>
                    <span class="status-badge p-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span>
                </div>
                <div class="card-body-custom">
                    @if($booking->payments->isEmpty())
                        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px 0;">No payments recorded yet.</p>
                    @else
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th style="text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($booking->payments as $payment)
                                <tr>
                                    <td style="color:var(--text-muted);">{{ $payment->payment_date?->format('M d, Y') }}</td>
                                    <td>{{ $payment->method_label }}</td>
                                    <td><span style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:10px;">{{ ucfirst($payment->payment_type) }}</span></td>
                                    <td style="text-align:right;font-weight:600;{{ $payment->payment_type === 'refund' ? 'color:#ef4444;' : 'color:#15803d;' }}">
                                        {{ $payment->payment_type === 'refund' ? '-' : '+' }}₱{{ number_format($payment->amount, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

        </div>

        {{-- RIGHT --}}
        <div>

            {{-- Price Summary --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Price Summary</h3>
                </div>
                <div class="card-body-custom">
                    <div class="pay-row">
                        <span style="color:var(--text-muted);">Base Amount ({{ $booking->num_nights }} nights)</span>
                        <span>₱{{ number_format($booking->base_amount, 2) }}</span>
                    </div>
                    @if($booking->extras_amount > 0)
                    <div class="pay-row">
                        <span style="color:var(--text-muted);">Add-ons / Extras</span>
                        <span>₱{{ number_format($booking->extras_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($booking->discount_amount > 0)
                    <div class="pay-row">
                        <span style="color:#15803d;">Discount</span>
                        <span style="color:#15803d;">-₱{{ number_format($booking->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="pay-row total">
                        <span>Total</span>
                        <span>₱{{ number_format($booking->total_amount, 2) }}</span>
                    </div>
                    <div class="pay-row" style="color:#15803d;">
                        <span>Amount Paid</span>
                        <span>₱{{ number_format($booking->amount_paid, 2) }}</span>
                    </div>
                    @if($booking->balance_due > 0)
                    <div class="pay-row balance">
                        <span>Balance Due</span>
                        <span>₱{{ number_format($booking->balance_due, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Record Payment --}}
            @if(!in_array($booking->payment_status, ['paid','refunded']))
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Record Payment</h3>
                </div>
                <div class="card-body-custom">
                    <form method="POST" action="{{ route('admin.bookings.payment', $booking) }}">
                        @csrf
                        <div style="margin-bottom:12px;">
                            <label class="form-label-sm">Amount (₱) *</label>
                            <input type="number" name="amount" class="form-control-sm-custom"
                                placeholder="0.00" min="1" step="0.01"
                                value="{{ $booking->balance_due }}" required>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label-sm">Payment Method *</label>
                            <select name="payment_method" class="form-control-sm-custom" required>
                                <option value="cash">💵 Cash</option>
                                <option value="gcash">📱 GCash</option>
                                <option value="paymaya">📱 PayMaya</option>
                                <option value="card">💳 Credit/Debit Card</option>
                                <option value="bank_transfer">🏦 Bank Transfer</option>
                            </select>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label-sm">Payment Type *</label>
                            <select name="payment_type" class="form-control-sm-custom" required>
                                <option value="deposit">Deposit</option>
                                <option value="partial">Partial Payment</option>
                                <option value="full_payment">Full Payment</option>
                                <option value="refund">Refund</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label class="form-label-sm">Notes (optional)</label>
                            <input type="text" name="notes" class="form-control-sm-custom" placeholder="e.g. GCash ref #123456">
                        </div>
                        <button type="submit" class="btn-record">
                            <i class="bi bi-check-circle me-1"></i> Record Payment
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Property Info --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Property</h3>
                    <a href="{{ route('admin.properties.show', $booking->property_id) }}"
                       style="font-size:12px;color:#2e5fa3;text-decoration:none;">View →</a>
                </div>
                <div class="card-body-custom">
                    @if($booking->property->primaryImage)
                        <img src="{{ asset('storage/'.$booking->property->primaryImage->image_path) }}"
                             style="width:100%;height:140px;object-fit:cover;border-radius:8px;margin-bottom:12px;" alt="">
                    @endif
                    <div style="font-weight:600;font-size:15px;">{{ $booking->property->property_name }}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
                        {{ ucfirst($booking->property->type) }} ·
                        Max {{ $booking->property->max_capacity }} guests ·
                        ₱{{ number_format($booking->property->base_price, 2) }}/night
                    </div>
                </div>
            </div>

        </div>
    </div>

</main>

{{-- Cancel Modal --}}
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-body p-4">
                <h5 style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:6px;">Cancel Booking?</h5>
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
                    This will cancel booking <strong>{{ $booking->booking_ref }}</strong> and free up the property.
                </p>
                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="cancelled">
                    <div style="margin-bottom:14px;">
                        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Reason for cancellation *</label>
                        <textarea name="cancellation_reason" rows="3"
                            style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:10px;font-size:13px;font-family:'DM Sans',sans-serif;resize:none;"
                            placeholder="Enter reason..." required></textarea>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Keep Booking</button>
                        <button type="submit" class="btn btn-danger w-50">Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showCancelModal() {
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
</script>
 @include('admin.partials.realtime') 
</body>
</html>