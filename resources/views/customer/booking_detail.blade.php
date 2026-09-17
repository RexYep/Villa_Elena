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

        /* Ang dalawang petsa at ang bilang ng gabi ay iisang pangungusap:
           "mula rito hanggang dito, ganito karami". Magkasama silang
           gumagalaw kapag naghahanap ng puwang ang flex. */
        .hero-stay {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .hero-badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 7px;
        }

        /* Dating inline style + onmouseover ang mga ito. Bilang flex child
           ay hindi sumusunod ang `display:block` + `margin-top` na inaasahan
           ng dating estilo — kaya lumulutang ito sa gitna ng hilera imbes
           na maging sariling pindutan. */
        .hero-cta {
            border-radius: 10px;
            padding: 13px 18px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s, color .2s;
        }

        .hero-cta-review {
            background: #f59e0b;
            color: #fff;
        }

        .hero-cta-review:hover {
            background: #d97706;
            color: #fff;
        }

        .hero-cta-reviewed {
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 255, 255, .75);
            font-weight: 500;
            font-size: 13px;
        }

        .hero-cta-reviewed:hover {
            background: rgba(255, 255, 255, .14);
            color: #fff;
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
            gap: 16px;
            padding: 9px 0;
            border-bottom: 1px solid #f4efe6;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row .lbl {
            color: var(--muted);
            flex: none;
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

        /* Manage Booking */
        .btn-manage {
            display: block;
            background: #f1f5f9;
            color: #374151;
            border-radius: 10px;
            padding: 13px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-manage:hover {
            background: #e2e8f0;
            color: #374151;
        }

        .manage-note {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            margin-top: 8px;
            line-height: 1.5;
        }

        .manage-blocked {
            background: #f8fafc;
            border-radius: 10px;
            padding: 13px;
            text-align: center;
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
        }

        .cancel-toggle {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .cancel-toggle summary {
            list-style: none;
            cursor: pointer;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: var(--terracotta);
            padding: 4px 0;
        }

        .cancel-toggle summary::-webkit-details-marker {
            display: none;
        }

        .cancel-toggle summary::after {
            content: ' ›';
            display: inline-block;
            transition: transform .2s;
        }

        .cancel-toggle[open] summary::after {
            transform: rotate(90deg);
        }

        .cancel-toggle summary:hover {
            text-decoration: underline;
        }

        .cancel-panel {
            margin-top: 12px;
        }

        .refund-estimate {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--muted);
        }

        .refund-estimate strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .refund-full {
            background: #f0fdf4;
        }

        .refund-full strong {
            color: #15803d;
        }

        .refund-partial {
            background: #fffbeb;
        }

        .refund-partial strong {
            color: #b45309;
        }

        .refund-none {
            background: #fff8f6;
        }

        .refund-none strong {
            color: var(--terracotta);
        }

        .cancel-policy {
            font-size: 12px;
            color: var(--muted);
            margin: 8px 0 14px;
            line-height: 1.5;
        }

        .cancel-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .cancel-error {
            font-size: 12px;
            color: #dc2626;
            margin-top: 4px;
        }

        .cancel-final {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            margin-top: 6px;
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

        .btn-cancel-booking:disabled {
            opacity: .6;
            cursor: default;
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

        /* Report an issue (v7.11) — ang form ay nasa customer/partials/issue_report_modal */
        .issue-card {
            border-color: #fecaca;
        }

        .issue-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .issue-cta-title {
            font-weight: 600;
            font-size: 15px;
            color: var(--stone);
        }

        .issue-cta-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        @media (max-width:600px) {
            .issue-cta .btn-report-issue {
                width: 100%;
            }
        }

        .issue-list.with-cta {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .issue-list-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .issue-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .issue-item:last-child {
            border-bottom: none;
        }

        .issue-item > i {
            font-size: 18px;
            color: var(--muted);
            margin-top: 1px;
        }

        .issue-item-main {
            flex: 1;
            min-width: 0;
        }

        .issue-item-title {
            font-size: 14px;
            font-weight: 600;
        }

        .issue-item-desc {
            font-size: 13px;
            color: var(--stone);
            overflow-wrap: anywhere;
        }

        .issue-item-time {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .issue-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .ws-pending { background: #fef9c3; color: #a16207; }
        .ws-in-progress { background: #dbeafe; color: #1d4ed8; }
        .ws-completed { background: #dcfce7; color: #15803d; }
        .ws-cancelled { background: #f1f5f9; color: #475569; }

        @media (max-width:900px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width:600px) {
            /* `stretch`, hindi `center`: ang bawat pangkat ay kumukuha ng
               buong lapad at nagsasalansan nang maayos, imbes na lumutang
               ang bawat isa sa sarili nitong lapad. */
            .booking-hero {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 16px;
                padding: 22px 18px;
            }

            .hero-ref {
                font-size: 22px;
            }

            .hero-sub {
                font-size: 13px;
            }

            /* Nananatili ang arrow (dating `display:none` dito): ito ang
               tanging nagsasabing saklaw ang dalawang petsa at hindi
               dalawang magkahiwalay na araw. */
            /* Grid, hindi wrap: sa flex ay kung ano ang unang maubusan ng
               puwang ang bumababa, kaya sa 360px ay naiiwan ang arrow
               kasama ng check-in at napupunta ang check-out sa susunod na
               linya. Dito ay laging magkasama sa isang linya ang tatlo, at
               laging nasa ilalim ang bilang ng gabi. */
            .hero-stay {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                align-items: center;
                justify-items: center;
                gap: 8px 12px;
            }

            .hero-nights {
                grid-column: 1 / -1;
            }

            .hero-date-val {
                font-size: 16px;
            }

            .hero-nights-val {
                font-size: 24px;
            }

            .hero-badges {
                flex-direction: row;
                justify-content: center;
                align-items: center;
            }

            .hero-cta {
                width: 100%;
            }

            /* Dating `display:block; overflow-x:auto; white-space:nowrap` —
               isang tabla na iniiscroll pahalang sa loob ng card. Dalawang
               problema: nagdidikit ang mga header ("METHODTYPE") at ang
               min-content ng tabla ay 275px pa rin, kaya ang BUONG page ang
               nag-o-overflow (38px sa 360px). Ngayon ay isang maliit na
               bloke ang bawat bayad, may sariling label kada halaga. */
            .pay-table thead {
                display: none;
            }

            .pay-table,
            .pay-table tbody,
            .pay-table tr,
            .pay-table td {
                display: block;
                width: auto;
            }

            .pay-table tr {
                padding: 10px 0;
                border-bottom: 1px solid #f4efe6;
            }

            .pay-table tr:last-child {
                border-bottom: none;
            }

            .pay-table td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 3px 0;
                border: none;
                text-align: right;
            }

            .pay-table td::before {
                content: attr(data-label);
                flex: none;
                color: var(--muted);
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .7px;
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

    {{-- Hero — nakapangkat: ang anim na magkakapatid na kahon dati ay
         nagsasalansan nang paisa-isa sa telepono, kaya nahihiwalay ang
         "check-in → check-out" at napupunta ang "1" at "night" sa
         magkaibang linya. Ang bawat pangkat ay isang bagay na hindi
         dapat mahati. --}}
    <div class="booking-hero">
        <div class="hero-id">
            <div class="hero-ref">{{ $booking->booking_ref }}</div>
            <div class="hero-sub">{{ $booking->property->property_name ?? '' }} ·
                {{ ucfirst(str_replace('_', ' ', $booking->source)) }}</div>
        </div>
        <div class="hero-stay">
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
        </div>
        <div class="hero-badges">
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
                <a href="{{ route('customer.reviews.create', $booking) }}" class="hero-cta hero-cta-review">
                    ⭐ Write a Review
                </a>
            @else
                <a href="{{ route('customer.reviews.edit', $myReview) }}" class="hero-cta hero-cta-reviewed">
                    <i class="bi bi-check-circle me-1" style="color:#16a34a;"></i>
                    You already reviewed this stay — view or edit it
                </a>
            @endif
        @endif
    </div>

    <div class="detail-grid">

        {{-- Left --}}
        <div>
            {{-- Report an issue (v7.11) — button na nagbubukas ng popup habang
                 naka-check-in; ang listahan ng mga ulat ay nananatili pagkatapos. --}}
            @if ($booking->canReportIssues() || $booking->issueReports->isNotEmpty())
                <div class="card issue-card" id="report-issue">
                    <div class="card-body">
                        @if ($booking->canReportIssues())
                            <div class="issue-cta">
                                <div>
                                    <div class="issue-cta-title">Having a problem during your stay?</div>
                                    <div class="issue-cta-sub">Let our staff on duty know — no need to look for someone.</div>
                                </div>
                                <button type="button" class="btn-report-issue" data-bs-toggle="modal"
                                    data-bs-target="#issueReportModal">
                                    <i class="bi bi-exclamation-triangle"></i> Report an Issue
                                </button>
                            </div>
                        @endif

                        @if ($booking->issueReports->isNotEmpty())
                            <div class="issue-list {{ $booking->canReportIssues() ? 'with-cta' : '' }}">
                                <div class="issue-list-label">Your reports</div>
                                @foreach ($booking->issueReports as $report)
                                    <div class="issue-item">
                                        <i class="bi bi-{{ $report->category_icon }}" aria-hidden="true"></i>
                                        <div class="issue-item-main">
                                            <div class="issue-item-title">{{ $report->category_label }}</div>
                                            @if ($report->description)
                                                <div class="issue-item-desc">{{ $report->description }}</div>
                                            @endif
                                            <div class="issue-item-time">Sent {{ $report->created_at->format('M j, g:i A') }}</div>
                                        </div>
                                        <span class="issue-status {{ $report->status_class }}">{{ $report->guest_status_label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

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
                                        <td class="text-muted-theme" data-label="Date">
                                            {{ $payment->payment_date?->format('M d, Y') }}</td>
                                        <td data-label="Method">{{ $payment->method_label }}</td>
                                        <td data-label="Type"><span
                                                style="background:#f1f5f9;padding:2px 8px;border-radius:10px;font-size: 12px;">{{ $payment->type_label }}</span>
                                        </td>
                                        <td data-label="Amount"
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

            {{-- Manage Booking — reschedule at cancel sa iisang card. Ang
                 reschedule ay ang karaniwang kailangan ng guest, kaya ito
                 ang butones; ang cancel ay nakatiklop na link, at ang
                 pulang butones ay makikita lang kapag binuksan. --}}
            @if ($booking->isCancellable())
                <div class="card">
                    <div class="card-head">
                        <h3>Manage Booking</h3>
                    </div>
                    <div class="card-body">
                        @if ($booking->isReschedulable())
                            <a href="{{ route('customer.bookings.reschedule', $booking) }}" class="btn-manage">
                                <i class="bi bi-calendar-event me-1"></i> Reschedule Booking
                            </a>
                            <div class="manage-note">
                                {{ $booking->reschedulesRemaining() }} of {{ \App\Models\Booking::MAX_RESCHEDULES }}
                                reschedules left ·
                                available until {{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before check-in
                            </div>
                        @else
                            <div class="manage-blocked">
                                <i class="bi bi-calendar-x me-1"></i>
                                {{ $booking->rescheduleBlockReason() }}
                            </div>
                        @endif

                        {{-- Bukas na kung bumalik mula sa validation error,
                             para hindi mawala sa guest ang mensahe. --}}
                        <details class="cancel-toggle" @if ($errors->has('cancellation_reason')) open @endif>
                            <summary>Cancel this booking</summary>

                            <div class="cancel-panel">
                                {{-- Estimate ayon sa oras na ito — nagbabago
                                     habang lumalapit ang check-in. Ang
                                     parehong kalkulasyon ang ginagamit ng
                                     cancelBooking(). --}}
                                <div class="refund-estimate refund-{{ $refundPreviewPct === 100 ? 'full' : ($refundPreviewPct > 0 ? 'partial' : 'none') }}">
                                    @if ($booking->amount_paid <= 0)
                                        <strong>No payment to refund</strong>
                                        You haven't paid anything for this booking yet.
                                    @elseif ($refundPreviewAmount > 0)
                                        <strong>
                                            Estimated refund: ₱{{ number_format($refundPreviewAmount, 2) }}
                                        </strong>
                                        {{ $refundPreviewPct }}% of the ₱{{ number_format($booking->amount_paid, 2) }}
                                        you've paid, if you cancel now. We'll ask where to send it.
                                    @else
                                        <strong>No refund</strong>
                                        Check-in is less than 3 days away, so the
                                        ₱{{ number_format($booking->amount_paid, 2) }} you've paid is non-refundable.
                                    @endif
                                </div>

                                <div class="cancel-policy">
                                    Full refund within 24 hours of booking or 7+ days before check-in ·
                                    50% at 3–6 days · none under 3 days.
                                </div>

                                <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}"
                                    id="cancelForm">
                                    @csrf @method('PATCH')
                                    <label for="cancellation_reason" class="cancel-label">Reason for cancelling</label>
                                    <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="2"
                                        placeholder="Let us know why…" required minlength="5">{{ old('cancellation_reason') }}</textarea>
                                    @error('cancellation_reason')
                                        <div class="cancel-error">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" class="btn-cancel-booking">
                                        Confirm Cancellation
                                    </button>
                                    <div class="cancel-final">This can't be undone.</div>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            @endif
        </div>

    </div>

    @if ($booking->canReportIssues())
        @include('customer.partials.issue_report_modal', ['booking' => $booking])
    @endif
@endsection

@push('scripts')
    <script>
        // Hindi `onclick` + form.submit(): nilalampasan nito ang `required`
        // /`minlength` ng textarea. Dito ay tumatakbo na ang validation ng
        // browser bago dumating ang submit event, at pinipigilan ang
        // dobleng pag-click.
        document.getElementById('cancelForm')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            if (btn.disabled) {
                e.preventDefault();
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Cancelling…';
        });
    </script>
@endpush

