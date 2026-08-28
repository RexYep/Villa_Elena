@extends('layouts.admin')

@section('title', 'Booking ' . $booking->booking_ref . ' — Villa Elena Admin')
@section('page-title', 'Booking Detail')
@section('page-subtitle', $booking->booking_ref . ' · ' . ($booking->property->property_name ?? ''))

@section('topbar-right')
    <a href="{{ route('admin.bookings.index') }}" class="text-muted-theme"
        style="display:flex;align-items:center;gap:6px;text-decoration:none;font-size:13px;border:1px solid var(--border);padding:7px 14px;border-radius:9px;background:#fff;">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
@endsection

@push('styles')
    <style>
        /* Layout */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        /* Cards */
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

        /* Booking Header Card */
        .booking-header {
            background: var(--terracotta);
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .booking-ref {
            font-family: 'Cormorant Garamond', serif;
            color: var(--gold-light);
            font-size: 28px;
            font-weight: 700;
        }

        .booking-source {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .booking-dates {
            color: #fff;
            font-size: 14px;
        }

        .booking-dates strong {
            display: block;
            font-size: 20px;
            font-family: 'Cormorant Garamond', serif;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .info-item .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .info-item .value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        /* Status Badges — semantic, unchanged */
        .status-badge {
            padding: 4px 12px;
            font-size: 12px;
        }

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

        .p-refunded {
            background: var(--tag-cyan-bg);
            color: var(--tag-cyan-fg);
        }

        /* Ang refund ay inaprubahan o nasa daan pa — hindi pa tapos.
           Dating "Refunded" agad ang ipinapakita rito, kahit walang
           perang gumagalaw. */
        .p-refund-progress {
            background: #fef3c7;
            color: #92400e;
        }

        /* Sinubukan pero hindi natuloy — may kailangang ayusin. */
        .p-refund-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons — semantic (status transitions), unchanged */
        .status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .btn-status {
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: opacity .2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-status:hover {
            opacity: .85;
        }

        .btn-confirm {
            background: #16a34a;
            color: #fff;
        }

        .btn-checkin {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-checkout {
            background: #475569;
            color: #fff;
        }

        .btn-cancel {
            background: #ef4444;
            color: #fff;
        }

        .btn-noshow {
            background: var(--stone);
            color: #fff;
        }

        /* Payment Summary */
        .pay-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .pay-row:last-child {
            border-bottom: none;
        }

        .pay-row.total {
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid var(--border);
            margin-top: 4px;
            padding-top: 12px;
        }

        .pay-row.balance {
            color: #dc2626;
            font-weight: 600;
        }

        /* Payment History Table */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .pay-table th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--muted);
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .pay-table td {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
        }

        .pay-table tr:last-child td {
            border-bottom: none;
        }

        /* Form inside card */
        .form-label-sm {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 5px;
            display: block;
        }

        .form-control-sm-custom {
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            width: 100%;
            background: #fff;
            color: var(--text-main);
        }

        .form-control-sm-custom:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        .btn-record {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            margin-top: 4px;
            transition: background .2s;
        }

        .btn-record:hover {
            background: var(--gold);
        }

        /* Guest card */
        .guest-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gold-dim);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            flex-shrink: 0;
        }

        @media (max-width: 900px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .booking-header {
                justify-content: center;
                text-align: center;
            }
        }
    </style>
@endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.bookings.index') }}">Bookings</a>
        <span class="sep">›</span>
        <span class="current">{{ $booking->booking_ref }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Booking Header --}}
    <div class="booking-header">
        <div>
            <div class="booking-ref">{{ $booking->booking_ref }}</div>
            <div class="booking-source">Source: {{ strtoupper(str_replace('_', ' ', $booking->source)) }}</div>
        </div>
        <div class="text-center">
            <div class="booking-dates">
                <strong>{{ $booking->check_in_date->format('M d, Y') }}</strong>
                Check-in @if ($booking->check_in_time)
                    · {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}
                @endif
            </div>
        </div>
        <div style="color:rgba(255,255,255,0.4);font-size:24px;">→</div>
        <div class="text-center">
            <div class="booking-dates">
                <strong>{{ $booking->check_out_date->format('M d, Y') }}</strong>
                Check-out @if ($booking->check_out_time)
                    · {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                @endif
            </div>
        </div>
        <div class="text-center">
            <div style="color:rgba(255,255,255,0.6);font-size:12px;margin-bottom:4px;">Duration</div>
            <div style="color:#fff;font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;">
                {{ $booking->num_nights }}</div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;">night{{ $booking->num_nights != 1 ? 's' : '' }}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <span
                class="status-badge s-{{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
            <span class="status-badge {{ $booking->payment_status_class }}">{{ $booking->payment_status_label }}</span>
        </div>
    </div>

    <div class="detail-grid">

        {{-- LEFT --}}
        <div>

            {{-- Status Actions --}}
            @if (!in_array($booking->status, ['checked_out', 'cancelled', 'no_show']))
                <div class="card-panel">
                    <div class="card-header-custom">
                        <h3>Update Status</h3>
                    </div>
                    <div class="card-body-custom">
                        {{-- Note: Wala nang manual "Confirm Booking" na button
                         dito — awtomatiko nang "confirmed" ang booking sa
                         sandaling matanggap ang successful na PayMongo
                         payment (tingnan: PaymentController::success()).
                         Kung nananatiling "pending" ang isang booking (ibig
                         sabihin, hindi pa nakapagbayad ang guest), ang
                         tanging manual na action na pwedeng gawin dito ay
                         i-cancel ito. --}}
                        <div class="status-actions">

                            @if ($booking->status === 'confirmed')
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}"
                                    id="checkinForm"
                                    onsubmit="return handleAdminCheckInSubmit(event, {{ $booking->balance_due ?? 0 }})">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="checked_in">
                                    <input type="hidden" name="balance_arrangement" id="checkinBalanceArrangement"
                                        value="">
                                    <button type="submit" class="btn-status btn-checkin">
                                        <i class="bi bi-box-arrow-in-right"></i> Check In
                                    </button>
                                </form>
                            @endif

                            @if ($booking->status === 'checked_in')
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="checked_out">
                                    <button type="submit" class="btn-status btn-checkout">
                                        <i class="bi bi-box-arrow-right"></i> Check Out
                                    </button>
                                </form>
                            @endif

                            @if (in_array($booking->status, ['pending', 'confirmed']))
                                <button type="button" class="btn-status btn-cancel" onclick="showCancelModal()">
                                    <i class="bi bi-x-circle"></i> Cancel Booking
                                </button>
                            @endif
                        </div>

                        @if ($booking->status === 'pending')
                            <div class="text-muted-theme"
                                style="font-size:12px;background:#fef9c3;border-radius:8px;padding:10px 12px;">
                                <i class="bi bi-hourglass-split me-1"></i>
                                Awaiting payment from the guest — it will automatically become "Confirmed" once the payment
                                is successful. No action is required unless you wish to cancel.
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Extend Stay (checked-in guests only) ── hindi ito bagong
                 booking, dine-diretso nitong itinutulak ang check-out ng
                 EXISTING stay. Hindi ito sasailalim sa fixed-slot na
                 policy — free-choice pa rin ang bagong check-out time. --}}
            @if ($booking->status === 'checked_in')
                <div class="card-panel">
                    <div class="card-header-custom">
                        <h3>Extend Stay</h3>
                    </div>
                    <div class="card-body-custom">
                        <p class="text-muted-theme mb-12" style="font-size:12px;">
                            Current check-out: <strong>{{ $booking->check_out_date->format('M d, Y') }}
                                @if ($booking->check_out_time)
                                    — {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                                @endif
                            </strong>
                            .
                            When approving an extension request, the system will first check whether a subsequent guest is
                            already scheduled before allowing it.
                            <strong>Manually add the extension fee under "Extra Charges" below</strong> if applicable.
                        </p>
                        <form method="POST" action="{{ route('admin.bookings.extend', $booking) }}">
                            @csrf @method('PATCH')
                            <div class="mb-12" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div>
                                    <label class="form-label-sm">New Check-out Date *</label>
                                    <input type="date" name="new_check_out_date" class="form-control-sm-custom"
                                        min="{{ $booking->check_out_date->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label class="form-label-sm">New Check-out Time *</label>
                                    <input type="time" name="new_check_out_time" class="form-control-sm-custom"
                                        value="{{ $booking->check_out_time ? \Carbon\Carbon::parse($booking->check_out_time)->format('H:i') : '' }}"
                                        required>
                                </div>
                            </div>
                            <button type="submit" class="btn-status"
                                style="background:var(--terracotta);color:#fff;width:100%;justify-content:center;">
                                <i class="bi bi-clock-history me-1"></i> Extend Stay
                            </button>
                        </form>
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
                            <div class="value">{{ $booking->num_guests }}
                                guest{{ $booking->num_guests != 1 ? 's' : '' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Check-in Date</div>
                            <div class="value">{{ $booking->check_in_date->format('l, F j, Y') }} @if ($booking->check_in_time)
                                    &nbsp;— {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}
                                @endif
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Check-out Date</div>
                            <div class="value">{{ $booking->check_out_date->format('l, F j, Y') }} @if ($booking->check_out_time)
                                    &nbsp;— {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                                @endif
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Booking Source</div>
                            <div class="value">{{ ucfirst(str_replace('_', ' ', $booking->source)) }}</div>
                        </div>
                        <div class="info-item">
                            <div class="label">Created</div>
                            <div class="value">{{ $booking->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                    </div>
                    @if ($booking->special_requests)
                        <div class="text-muted-theme"
                            style="margin-top:16px;padding:12px 14px;background:#f8fafc;border-radius:8px;font-size:13px;">
                            <strong style="color:var(--text-main);display:block;margin-bottom:4px;">Special
                                Requests:</strong>
                            {{ $booking->special_requests }}
                        </div>
                    @endif
                    @if ($booking->cancellation_reason)
                        <div
                            style="margin-top:16px;padding:12px 14px;background:#fef2f2;border-radius:8px;font-size:13px;color:#dc2626;">
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
                            <div class="text-muted-theme" style="font-size:12px;">{{ $booking->user->email ?? '' }}</div>
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
                        @if ($booking->user->id_number)
                            <div class="info-item">
                                <div class="label">ID Number</div>
                                <div class="value">{{ $booking->user->id_number }}</div>
                            </div>
                        @endif
                        @if ($booking->user->address)
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
                    <span
                        class="status-badge {{ $booking->payment_status_class }}">{{ $booking->payment_status_label }}</span>
                </div>
                <div class="card-body-custom">
                    @if ($booking->payments->isEmpty())
                        <p class="text-muted-theme" style="font-size:13px;text-align:center;padding:20px 0;">No payments
                            recorded yet.</p>
                    @else
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($booking->payments->sortByDesc(fn($p) => [$p->payment_date, $p->id]) as $payment)
                                    <tr>
                                        <td class="text-muted-theme">{{ $payment->payment_date?->format('M d, Y') }}</td>
                                        <td>{{ $payment->method_label }}</td>
                                        <td><span
                                                style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:10px;">{{ $payment->type_label }}</span>
                                        </td>
                                        <td
                                            style="text-align:right;font-weight:600;{{ $payment->payment_type === 'refund' ? 'color:#ef4444;' : 'color:#15803d;' }}">
                                            {{ $payment->payment_type === 'refund' ? '-' : '+' }}₱{{ number_format($payment->amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Extra Charges / Amenity Add-ons --}}
            <div class="card-panel">
                <div class="card-header-custom">
                    <h3>Extra Charges</h3>
                </div>
                <div class="card-body-custom">
                    @if ($booking->extras->isEmpty())
                        <p class="text-muted-theme" style="font-size:13px;text-align:center;padding:10px 0;">No extra
                            charges recorded.</p>
                    @else
                        <table class="pay-table mb-3">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($booking->extras as $extra)
                                    <tr>
                                        <td>
                                            {{ $extra->item_name }}
                                            @if ($extra->description)
                                                <div class="text-muted-theme" style="font-size:11px;">
                                                    {{ $extra->description }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $extra->quantity }}</td>
                                        <td class="text-end">₱{{ number_format($extra->unit_price, 2) }}</td>
                                        <td style="text-align:right;font-weight:600;">
                                            ₱{{ number_format($extra->total, 2) }}</td>
                                        <td class="text-end">
                                            <form method="POST"
                                                action="{{ route('admin.bookings.extras.destroy', [$booking, $extra]) }}"
                                                onsubmit="return confirm('Remove this extra charge?');"
                                                style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;"
                                                    title="Remove">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <form method="POST" action="{{ route('admin.bookings.extras.store', $booking) }}">
                        @csrf
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                            <div>
                                <label class="form-label-sm">Item / Amenity Name *</label>
                                <input type="text" name="item_name" class="form-control-sm-custom"
                                    placeholder="hal. Extra Grill Set, Karaoke Rental" required>
                            </div>
                            <div>
                                <label class="form-label-sm">Description (optional)</label>
                                <input type="text" name="description" class="form-control-sm-custom"
                                    placeholder="Detalye (opsyonal)">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                            <div>
                                <label class="form-label-sm">Quantity *</label>
                                <input type="number" name="quantity" class="form-control-sm-custom" value="1"
                                    min="1" required>
                            </div>
                            <div>
                                <label class="form-label-sm">Unit Price (₱) *</label>
                                <input type="number" name="unit_price" class="form-control-sm-custom" step="0.01"
                                    min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-status"
                            style="background:var(--terracotta);color:#fff;width:100%;justify-content:center;">
                            <i class="bi bi-plus-circle me-1"></i> Add Extra Charge
                        </button>
                    </form>
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
                        <span class="text-muted-theme">Base Amount ({{ $booking->num_nights }} nights)</span>
                        <span>₱{{ number_format($booking->base_amount, 2) }}</span>
                    </div>
                    @if ($booking->extras_amount > 0)
                        <div class="pay-row">
                            <span class="text-muted-theme">Add-ons / Extras</span>
                            <span>₱{{ number_format($booking->extras_amount, 2) }}</span>
                        </div>
                    @endif
                    @if ($booking->discount_amount > 0)
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
                    @if ($booking->balance_due > 0)
                        <div class="pay-row balance">
                            <span>Balance Due</span>
                            <span>₱{{ number_format($booking->balance_due, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Record Payment --}}
            @if (!in_array($booking->payment_status, ['paid', 'refunded']))
                <div class="card-panel" id="card-record-payment">
                    <div class="card-header-custom">
                        <h3>Record Payment</h3>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="{{ route('admin.bookings.payment', $booking) }}">
                            @csrf
                            <div class="mb-12">
                                <label class="form-label-sm">Amount (₱) *</label>
                                <input type="number" name="amount" class="form-control-sm-custom" placeholder="0.00"
                                    min="1" step="0.01" value="{{ $booking->balance_due }}" required>
                            </div>
                            <div class="mb-12">
                                <label class="form-label-sm">Payment Method *</label>
                                <select name="payment_method" class="form-control-sm-custom" required>
                                    <option value="cash">💵 Cash</option>
                                    <option value="qrph">📱 QR Ph (GCash / Maya / bank app)</option>
                                </select>
                            </div>
                            <div class="mb-12">
                                <label class="form-label-sm">Payment Type *</label>
                                <select name="payment_type" class="form-control-sm-custom" required>
                                    <option value="partial">Partial Payment</option>
                                    <option value="full_payment">Full Payment</option>
                                    <option value="refund">Refund</option>
                                </select>
                            </div>
                            <div style="margin-bottom:14px;">
                                <label class="form-label-sm">Notes (optional)</label>
                                <input type="text" name="notes" class="form-control-sm-custom"
                                    placeholder="e.g. QR Ph ref #123456">
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
                    @if ($booking->property->primaryImage)
                        <img src="{{ $booking->property->primaryImage->url }}" class="mb-12"
                            style="width:100%;height:140px;object-fit:cover;border-radius:8px;" alt="">
                    @endif
                    <div style="font-weight:600;font-size:15px;">{{ $booking->property->property_name }}</div>
                    <div class="text-muted-theme" style="font-size:12px;margin-top:3px;">
                        {{ ucfirst($booking->property->type) }} ·
                        Max {{ $booking->property->max_capacity }} guests ·
                        ₱{{ number_format($booking->property->base_price, 2) }}/night
                    </div>
                </div>
            </div>

        </div>
    </div>

@endsection

@section('modals')
    {{-- Cancel Modal --}}
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
            <div class="modal-content" style="border-radius:14px;border:none;">
                <div class="modal-body p-4">
                    <h5 style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:6px;">Cancel Booking?
                    </h5>
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
                        This will cancel booking <strong>{{ $booking->booking_ref }}</strong> and free up the property.
                    </p>
                    <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <div style="margin-bottom:14px;">
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Reason for
                                cancellation *</label>
                            <textarea name="cancellation_reason" rows="3"
                                style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:10px;font-size:13px;font-family:'DM Sans',sans-serif;resize:none;"
                                placeholder="Enter reason..." required></textarea>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Keep
                                Booking</button>
                            <button type="submit" class="btn btn-danger w-50">Cancel Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Confirm Modal (may Natitirang Balance) --}}
    <div class="modal fade" id="balanceConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content" style="border-radius:14px;border:none;">
                <div class="modal-body p-4">
                    <h5 style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:6px;">Outstanding
                        Balance</h5>
                    <p style="font-size:13px;color:#64748b;margin-bottom:10px;">
                        This booking has a balance of
                        <strong style="color:#b45309;">₱{{ number_format($booking->balance_due ?? 0, 2) }}</strong>.
                        Would you like to record the payment now, or check in with deferred payment (to be paid upon
                        check-out)?
                    </p>
                    <div style="display:flex;gap:8px;margin-bottom:14px;">
                        <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal"
                            onclick="document.querySelector('#card-record-payment')?.scrollIntoView({behavior:'smooth'})">
                            Pay Now
                        </button>
                    </div>
                    <div style="border-top:1px solid var(--border);padding-top:14px;">
                        <label
                            style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:#64748b;cursor:pointer;">
                            <input type="checkbox" id="adminDeferCheckbox" style="margin-top:2px;">
                            I confirm that I will defer the payment of this balance until check-out. I take full
                            responsibility for this decision.
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="adminConfirmDeferredBtn" class="btn btn-danger w-50" disabled
                            style="opacity:.5;" onclick="confirmAdminDeferredCheckIn()">
                            Confirm and Check In
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function showCancelModal() {
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        // ── Admin Check-in Balance Confirmation ──────────────────────────
        function handleAdminCheckInSubmit(event, balanceDue) {
            if (balanceDue <= 0) {
                return true; // walang balance, tuloy-tuloy
            }
            event.preventDefault();
            new bootstrap.Modal(document.getElementById('balanceConfirmModal')).show();
            return false;
        }

        document.getElementById('adminDeferCheckbox')?.addEventListener('change', function() {
            const btn = document.getElementById('adminConfirmDeferredBtn');
            btn.disabled = !this.checked;
            btn.style.opacity = this.checked ? '1' : '.5';
        });

        function confirmAdminDeferredCheckIn() {
            document.getElementById('checkinBalanceArrangement').value = 'deferred';
            bootstrap.Modal.getInstance(document.getElementById('balanceConfirmModal'))?.hide();
            document.getElementById('checkinForm').submit();
        }
    </script>
@endpush

