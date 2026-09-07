@extends('layouts.customer')

@section('title', 'My Payments — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 820px;
        }

        .page-title {
            font-weight: 700;
        }

        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .pay-table th {
            color: var(--muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            background: #faf7f2;
        }

        .pay-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f4efe6;
        }

        .pay-table tr:last-child td {
            border-bottom: none;
        }

        .booking-link {
            color: var(--stone);
            font-weight: 600;
            text-decoration: none;
        }

        .booking-link:hover {
            color: var(--gold);
        }

        .type-pill {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .status-pill {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .s-success {
            background: #dcfce7;
            color: #15803d;
        }

        .s-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .s-failed {
            background: #fee2e2;
            color: #dc2626;
        }

        .s-refunded {
            background: #e0e7ff;
            color: #4338ca;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .pay-date,
        .pay-amount {
            white-space: nowrap;
        }

        /* ── Telepono ──
           Anim na hanay ang tabla; sa 390px ay hindi ito kasya, kaya ang
           buong pahina ay iniiscroll pahalang sa loob ng `overflow-x:auto`
           na wrapper — at ang HALAGA, ang tanging dahilan kung bakit binuksan
           ito ng bisita, ang huling hanay: nasa labas ito ng screen hanggang
           mag-swipe siya. Ang bawat bayad ay nagiging maliit na card:
           reference at halaga sa unang linya, pangalan ng property sa
           ikalawa, at ang natitirang detalye sa ikatlo. */
        @media (max-width:600px) {
            .pay-table {
                border-radius: 14px;
            }

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
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 4px 10px;
                padding: 14px 16px;
                border-bottom: 1px solid #f4efe6;
            }

            .pay-table tr:last-child {
                border-bottom: none;
            }

            .pay-table td {
                padding: 0;
                border: none;
            }

            /* Booking (reference + property) */
            .pay-table td:nth-child(2) {
                flex: 1 1 auto;
                min-width: 0;
                order: 1;
            }

            /* Halaga — katabi ng reference, hindi nakatago sa dulo */
            .pay-table td:nth-child(6) {
                order: 2;
                margin-left: auto;
                font-size: 14px;
            }

            /* Petsa, paraan, uri, katayuan — iisang linya sa ilalim */
            .pay-table td:nth-child(1) {
                order: 3;
                flex: 1 0 100%;
                color: var(--muted);
            }

            .pay-table td:nth-child(3) {
                order: 4;
                color: var(--muted);
            }

            .pay-table td:nth-child(4) {
                order: 5;
            }

            .pay-table td:nth-child(5) {
                order: 6;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-title">My Payments</div>
    <div class="page-sub" style="margin-bottom:20px;">Payment history across all your bookings</div>

    @if ($payments->isEmpty())
        <div class="empty-state">
            <i class="bi bi-receipt" style="font-size:40px;opacity:.3;"></i>
            <p style="margin-top:12px;">No payments recorded yet.</p>
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Booking</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="text-muted-theme pay-date">{{ $payment->payment_date?->format('M d, Y') }}</td>
                            <td>
                                @if ($payment->booking)
                                    <a href="{{ route('customer.bookings.show', $payment->booking) }}" class="booking-link">
                                        {{ $payment->booking->booking_ref }}
                                    </a>
                                    <div class="text-muted-theme" style="font-size: 13px;">
                                        {{ $payment->booking->property->property_name ?? '' }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $payment->method_label }}</td>
                            <td><span class="type-pill">{{ $payment->type_label }}</span></td>
                            <td><span class="status-pill s-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span>
                            </td>
                            <td class="pay-amount"
                                style="text-align:right;font-weight:600;color:{{ $payment->payment_type === 'refund' ? '#dc2626' : '#15803d' }};">
                                {{ $payment->payment_type === 'refund' ? '-' : '+' }}₱{{ number_format($payment->amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">{{ $payments->links() }}</div>
    @endif
@endsection

