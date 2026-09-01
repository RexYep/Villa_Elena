@extends('layouts.customer')

@section('title', $booking->booking_ref . ' — Villa Elena Resort')

@push('styles')
    <style>
        /* Hero */
        .booking-hero {
            background: var(--stone);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .hero-ref {
            font-family: 'Playfair Display', serif;
            color: var(--gold-light);
            font-size: 26px;
            font-weight: 600;
        }

        .hero-sub {
            color: rgba(255, 255, 255, .4);
            font-size: 14px;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-dates {
            text-align: center;
        }

        .hero-date-label {
            color: rgba(255, 255, 255, .4);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .hero-date-val {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 18px;
            font-weight: 600;
        }

        .hero-arrow {
            color: rgba(255, 255, 255, .3);
            font-size: 20px;
        }

        .hero-nights {
            color: #fff;
            text-align: center;
        }

        .hero-nights-val {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
        }

        .hero-nights-label {
            color: rgba(255, 255, 255, .4);
            font-size: 14px;
        }

        .badge {
            padding: 4px 12px;
            font-size: 13px;
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

        /* Wala pang estilo ang "Refunded" dito hanggang ngayon — walang
           `.p-refunded` na tumugma sa dating `p-` + payment_status na
           class, kaya blangko ang badge. */
        .p-refunded {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        /* Inaprubahan na o nasa daan pa ang refund — hindi pa tapos. */
        .p-refund-progress {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .p-refund-failed {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        /* Layout */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            align-items: start;
        }

        /* Cards */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f4efe6;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row .lbl {
            color: var(--muted);
        }

        .info-row .val {
            font-weight: 500;
            text-align: right;
        }

        /* Price */
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #f4efe6;
        }

        .price-row:last-child {
            border-bottom: none;
        }

        .price-row.total {
            font-weight: 700;
            font-size: 16px;
            font-family: 'Playfair Display', serif;
            border-top: 2px solid var(--border);
            padding-top: 12px;
            margin-top: 4px;
        }

        .price-row.paid {
            color: #15803d;
        }

        .price-row.due {
            color: var(--terracotta);
            font-weight: 600;
        }

        /* Payment table */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .pay-table th {
            color: var(--muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 7px 0;
            border-bottom: 1px solid var(--border);
        }

        .pay-table td {
            padding: 9px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .pay-table tr:last-child td {
            border-bottom: none;
        }

        /* Cancel form */
        .cancel-section {
            background: #fff8f6;
            border: 1px solid #fdd9cc;
            border-radius: 12px;
            padding: 18px;
        }

        .cancel-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--terracotta);
            margin-bottom: 8px;
        }

        .cancel-desc {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .btn-cancel-booking {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            width: 100%;
            margin-top: 10px;
            transition: opacity .2s;
        }

        .btn-cancel-booking:hover {
            opacity: .88;
        }

        /* Property image */
        .property-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .cancelled-banner {
            background: #fee2e2;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #dc2626;
        }

        .cancelled-banner strong {
            display: block;
            margin-bottom: 3px;
        }

        @media (max-width:900px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width:600px) {
            .booking-hero {
                justify-content: center;
                text-align: center;
            }

            .hero-arrow {
                display: none;
            }

            .pay-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('customer.home') }}">Dashboard</a>
        <span>›</span>
        <a href="{{ route('customer.bookings') }}">My Bookings</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $booking->booking_ref }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    @if ($booking->status === 'cancelled')
        <div class="cancelled-banner">
            <strong>This booking was cancelled</strong>
            {{ $booking->cancellation_reason }}
            @if ($booking->cancelled_at)
                · {{ $booking->cancelled_at->format('M d, Y') }}
            @endif
        </div>
    @endif

    {{-- Hero --}}
    <div class="booking-hero">
        <div>
            <div class="hero-ref">{{ $booking->booking_ref }}</div>
            <div class="hero-sub">{{ $booking->property->property_name ?? '' }} ·
                {{ ucfirst(str_replace('_', ' ', $booking->source)) }}</div>
        </div>
        <div class="hero-dates">
            <div class="hero-date-label">Check-in</div>
            <div class="hero-date-val">{{ $booking->check_in_date->format('M d, Y') }}</div>
        </div>
        <div class="hero-arrow">→</div>
        <div class="hero-dates">
            <div class="hero-date-label">Check-out</div>
            <div class="hero-date-val">{{ $booking->check_out_date->format('M d, Y') }}</div>
        </div>
        <div class="hero-nights">
            <div class="hero-nights-val">{{ $booking->num_nights }}</div>
            <div class="hero-nights-label">night{{ $booking->num_nights != 1 ? 's' : '' }}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:7px;align-items:flex-end;">
            <span class="badge b-{{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
            <span class="badge {{ $booking->payment_status_class }}">{{ $booking->payment_status_label }}</span>
        </div>
        @if ($booking->status === 'checked_out')
            @php
                $myReview = \App\Models\Review::where('booking_id', $booking->id)
                    ->where('user_id', Auth::id())
                    ->first();
            @endphp
            @if (!$myReview)
                <a href="{{ route('customer.reviews.create', $booking) }}"
                    style="display:block;background:#f59e0b;color:#fff;border-radius:10px;padding:13px;
               text-align:center;font-size:14px;font-weight:600;text-decoration:none;
               margin-top:14px;transition:all .2s;"
                    onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'">
                    ⭐ Write a Review
                </a>
            @else
                <a href="{{ route('customer.reviews.edit', $myReview) }}"
                    style="display:block;background:#f8fafc;border-radius:10px;padding:12px;text-align:center;
                 font-size:13px;color:#6B7A8D;margin-top:14px;text-decoration:none;">
                    <i class="bi bi-check-circle me-1" style="color:#16a34a;"></i>
                    You already submitted a review for this stay — click to view/edit.
                </a>
            @endif
        @endif
    </div>

    <div class="detail-grid">

        {{-- Left --}}
        <div>
            {{-- Booking Info --}}
            <div class="card">
                <div class="card-head">
                    <h3>Booking Details</h3>
                </div>
                <div class="card-body">
                    <div class="info-row"><span class="lbl">Property</span><span
                            class="val">{{ $booking->property->property_name ?? 'N/A' }}</span></div>
                    <div class="info-row"><span class="lbl">Check-in</span><span
                            class="val">{{ $booking->check_in_date->format('l, F j, Y') }}</span></div>
                    <div class="info-row"><span class="lbl">Check-out</span><span
                            class="val">{{ $booking->check_out_date->format('l, F j, Y') }}</span></div>
                    <div class="info-row"><span class="lbl">Duration</span><span
                            class="val">{{ $booking->num_nights }} night{{ $booking->num_nights != 1 ? 's' : '' }}</span>
                    </div>
                    <div class="info-row"><span class="lbl">Guests</span><span class="val">{{ $booking->num_guests }}
                            guest{{ $booking->num_guests != 1 ? 's' : '' }}</span></div>
                    <div class="info-row"><span class="lbl">Booking Source</span><span
                            class="val">{{ ucfirst(str_replace('_', ' ', $booking->source)) }}</span></div>
                    <div class="info-row"><span class="lbl">Booked On</span><span
                            class="val">{{ $booking->created_at->format('M d, Y h:i A') }}</span></div>
                    @if ($booking->special_requests)
                        <div class="text-muted-theme"
                            style="margin-top:12px;background:#f9f5ee;border-radius:8px;padding:12px;font-size:13px;">
                            <strong style="color:var(--stone);display:block;margin-bottom:4px;">Special Requests:</strong>
                            {{ $booking->special_requests }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Payment History --}}
            <div class="card">
                <div class="card-head">
                    <h3>Payment History</h3>
                </div>
                <div class="card-body">
                    @if ($booking->payments->isEmpty())
                        <p class="text-muted-theme" style="font-size:13px;text-align:center;padding:16px 0;">No payments
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
                                                style="background:#f1f5f9;padding:2px 8px;border-radius:10px;font-size: 12px;">{{ $payment->type_label }}</span>
                                        </td>
                                        <td
                                            style="text-align:right;font-weight:600;color:{{ $payment->payment_type === 'refund' ? '#dc2626' : '#15803d' }};">
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

        {{-- Right --}}
        <div>
            {{-- Property Image & Info --}}
            <div class="card">
                <div class="card-body">
                    @if ($booking->property?->primaryImage)
                        <img src="{{ $booking->property->primaryImage->url }}" class="property-img" alt="">
                    @endif
                    <div style="font-weight:600;font-size:15px;font-family:'Playfair Display',serif;">
                        {{ $booking->property->property_name ?? 'N/A' }}</div>
                    @if ($booking->property)
                        <div class="text-muted-theme" style="font-size: 14px;margin-top:4px;">
                            {{ ucfirst($booking->property->type) }} ·
                            Max {{ $booking->property->max_capacity }} guests
                        </div>
                    @endif
                </div>
            </div>

            {{-- Price Summary --}}
            <div class="card">
                <div class="card-head">
                    <h3>Price Summary</h3>
                </div>
                <div class="card-body">
                    <div class="price-row"><span class="text-muted-theme">Base
                            ({{ $booking->num_nights }}n)</span><span>₱{{ number_format($booking->base_amount, 2) }}</span>
                    </div>
                    @if ($booking->extras_amount > 0)
                        <div class="price-row"><span
                                class="text-muted-theme">Add-ons</span><span>₱{{ number_format($booking->extras_amount, 2) }}</span>
                        </div>
                    @endif
                    @if ($booking->discount_amount > 0)
                        <div class="price-row"><span style="color:#15803d;">Discount</span><span
                                style="color:#15803d;">-₱{{ number_format($booking->discount_amount, 2) }}</span></div>
                    @endif
                    <div class="price-row total">
                        <span>Total</span><span>₱{{ number_format($booking->total_amount, 2) }}</span></div>
                    <div class="price-row paid"><span>Paid</span><span>₱{{ number_format($booking->amount_paid, 2) }}</span>
                    </div>
                    @if ($booking->balance_due > 0)
                        <div class="price-row due"><span>Balance
                                Due</span><span>₱{{ number_format($booking->balance_due, 2) }}</span></div>
                    @endif

                    @if ($booking->balance_due > 0 && !in_array($booking->status, ['cancelled', 'checked_out']))
                        <a href="{{ route('payment.page', $booking) }}"
                            style="display:block;background:#2c2416;color:#fff;border-radius:10px;padding:13px;
                            text-align:center;font-size:14px;font-weight:600;text-decoration:none;
                            margin-top:14px;transition:all .2s;"
                            onmouseover="this.style.background='#b8943f';this.style.color='#2c2416'"
                            onmouseout="this.style.background='#2c2416';this.style.color='#fff'">
                            💳 Pay Now — ₱{{ number_format($booking->balance_due, 2) }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Reschedule --}}
            @if (in_array($booking->status, ['pending', 'confirmed']))
                <div class="card">
                    <div class="card-body">
                        @if ($booking->isReschedulable())
                            <a href="{{ route('customer.bookings.reschedule', $booking) }}"
                                style="display:block;background:#f1f5f9;color:#374151;border-radius:10px;padding:13px;
                                  text-align:center;font-size:14px;font-weight:600;text-decoration:none;">
                                <i class="bi bi-calendar-event me-1"></i> Reschedule Booking
                            </a>
                            <div
                                style="font-size: 13px;color:var(--muted);text-align:center;margin-top:8px;line-height:1.5;">
                                {{ $booking->reschedulesRemaining() }} of {{ \App\Models\Booking::MAX_RESCHEDULES }}
                                reschedules left ·
                                available until {{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before check-in
                            </div>
                        @else
                            <div
                                style="background:#f8fafc;border-radius:10px;padding:13px;text-align:center;
                                    font-size: 14px;color:var(--muted);line-height:1.6;">
                                <i class="bi bi-calendar-x me-1"></i>
                                {{ $booking->rescheduleBlockReason() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Cancel --}}
            @if (in_array($booking->status, ['pending', 'confirmed']))
                <div class="cancel-section">
                    <div class="cancel-title"><i class="bi bi-x-circle me-1"></i> Cancel Booking</div>
                    <div class="cancel-desc">
                        Need to cancel? Please provide a reason below. Cancellations may be subject to our
                        cancellation policy depending on how close to the check-in date.
                    </div>
                    <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}" id="cancelForm">
                        @csrf @method('PATCH')
                        <textarea name="cancellation_reason" class="form-control" rows="2" placeholder="Reason for cancellation..."
                            required minlength="5"></textarea>
                        <button type="button" class="btn-cancel-booking" onclick="confirmCancel()">
                            Cancel This Booking
                        </button>
                    </form>
                </div>
            @endif
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        function confirmCancel() {
            if (confirm('Are you sure you want to cancel booking {{ $booking->booking_ref }}? This cannot be undone.')) {
                document.getElementById('cancelForm').submit();
            }
        }
    </script>
@endpush

