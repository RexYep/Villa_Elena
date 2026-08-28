@extends('layouts.payment')

@php
    // Puwedeng marating ang page na ito nang HINDI PA bayad — normal ito
    // sa QR Ph, kung saan ini-scan ng guest ang QR sa ibang device at
    // maaaring hindi pa nagse-settle ang bayad pagbalik niya rito.
    // Kailangan itong sabihin nang tapat sa halip na magpakita ng
    // berdeng tsek na "Payment Successful" na wala namang pinatutunayan.
    $confirmed = $paymentConfirmed ?? true;
@endphp

@section('title', ($confirmed ? 'Payment Successful' : 'Waiting for Payment Confirmation') . ' — Villa Elena Resort')

@push('styles')
    <style>
        .card {
            max-width: 440px;
        }

        .card-top {
            background: var(--stone);
            padding: 36px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-top::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184, 148, 63, .2) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .success-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(74, 222, 128, .15);
            border: 2px solid #4ade80;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            position: relative;
            z-index: 1;
        }

        .success-circle i {
            font-size: 30px;
            color: #4ade80;
        }

        .card-top h1 {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 26px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .card-top p {
            color: rgba(255, 255, 255, .45);
            font-size: 13px;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        .amount-box {
            background: var(--sand);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            margin-bottom: 22px;
        }

        .amount-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .amount-val {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--stone);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 9px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row .lbl {
            color: var(--muted);
        }

        .detail-row .val {
            font-weight: 500;
        }

        .status-confirmed {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-partial {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .btn-primary {
            display: block;
            background: var(--stone);
            color: #fff;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 22px;
            transition: all .2s;
        }

        .btn-primary:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-secondary {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            color: var(--stone);
        }

        .confetti {
            font-size: 24px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        /* Estado kung hindi pa nakukumpirma ang bayad (karaniwan sa QR Ph) */
        .pending-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(184, 148, 63, .15);
            border: 2px solid var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            position: relative;
            z-index: 1;
        }

        .pending-circle i {
            font-size: 28px;
            color: var(--gold);
        }

        .pending-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            background: var(--sand);
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 14px;
            font-size: 12.5px;
            line-height: 1.55;
            color: var(--stone);
            margin-bottom: 20px;
        }

        .pending-note i {
            font-size: 15px;
            color: var(--gold);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .status-unpaid {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
    <div class="card">
        <div class="card-top">
            @if ($confirmed)
                <div class="confetti">🎉</div>
                <div class="success-circle"><i class="bi bi-check-lg"></i></div>
                <h1>Payment Successful!</h1>
                <p>Your payment has been received and confirmed.</p>
            @else
                <div class="confetti">📷</div>
                <div class="pending-circle"><i class="bi bi-hourglass-split"></i></div>
                <h1>Waiting for Payment</h1>
                <p>Hindi pa namin nakukumpirma ang bayad para sa booking na ito.</p>
            @endif
        </div>
        <div class="card-body">
            @php
                $booking->refresh();
                $paidNow = $amountPaid ?? 0;
            @endphp

            @if ($confirmed)
                <div class="amount-box">
                    <div class="amount-label">Amount Paid</div>
                    <div class="amount-val">₱{{ number_format($paidNow, 2) }}</div>
                </div>
            @else
                <div class="pending-note">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <strong>Kung nakabayad ka na</strong>, huwag kang mag-alala — awtomatiko naming natatanggap ang
                        kumpirmasyon mula sa bangko o e-wallet mo, kadalasan sa loob ng ilang minuto. Padadalhan ka namin ng
                        notification at email pagdating nito.
                        <div style="margin-top:8px;">
                            <strong>Kung hindi pa</strong>, puwede mong ulitin ang bayad mula sa booking mo — hindi ka
                            masisingil nang doble.
                        </div>
                    </div>
                </div>

                <div class="amount-box">
                    <div class="amount-label">Amount Due</div>
                    <div class="amount-val">₱{{ number_format($booking->balance_due, 2) }}</div>
                </div>
            @endif

            <div class="detail-row"><span class="lbl">Booking Ref</span><span
                    class="val">{{ $booking->booking_ref }}</span></div>
            <div class="detail-row"><span class="lbl">Property</span><span
                    class="val">{{ $booking->property->property_name }}</span></div>
            <div class="detail-row"><span class="lbl">Check-in</span><span
                    class="val">{{ $booking->check_in_date->format('M d, Y') }}</span></div>
            <div class="detail-row"><span class="lbl">Check-out</span><span
                    class="val">{{ $booking->check_out_date->format('M d, Y') }}</span></div>
            <div class="detail-row">
                <span class="lbl">Booking Status</span>
                <span class="val">
                    @if ($booking->status === 'confirmed')
                        <span class="status-confirmed">Confirmed</span>
                    @else
                        <span>{{ ucfirst($booking->status) }}</span>
                    @endif
                </span>
            </div>
            <div class="detail-row">
                <span class="lbl">Payment Status</span>
                <span class="val">
                    @if ($booking->payment_status === 'paid')
                        <span class="status-confirmed">Fully Paid ✓</span>
                    @elseif($booking->payment_status === 'partial')
                        <span class="status-partial">Partial — ₱{{ number_format($booking->balance_due, 2) }}
                            remaining</span>
                    @else
                        {{-- Dating blangko ang cell na ito kapag 'unpaid' —
                         mas nakakalito iyon kaysa sa pagsasabi mismo. --}}
                        <span class="status-unpaid">Not yet received</span>
                    @endif
                </span>
            </div>

            <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-primary">
                <i class="bi bi-calendar-check me-2"></i> View My Booking
            </a>
            @if (!$confirmed && $booking->balance_due > 0)
                <a href="{{ route('payment.page', $booking) }}" class="btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Subukan ulit ang bayad
                </a>
            @endif
            <a href="{{ route('customer.home') }}" class="btn-secondary">← Back to Dashboard</a>
        </div>
    </div>
@endsection

