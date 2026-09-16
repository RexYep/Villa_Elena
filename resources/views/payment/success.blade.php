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
            {{-- Pinapalitan ng laman nito ang sarili kapag dumating ang
                 bayad habang bukas ang page — tingnan ang script sa ibaba. --}}
            <div id="payHead">
                @if ($confirmed)
                    <div class="confetti">🎉</div>
                    <div class="success-circle"><i class="bi bi-check-lg"></i></div>
                    <h1>Payment Successful!</h1>
                    <p>Your payment has been received and confirmed.</p>
                @else
                    <div class="confetti">📷</div>
                    <div class="pending-circle"><i class="bi bi-hourglass-split"></i></div>
                    <h1>Waiting for Payment</h1>
                    <p>We have not yet confirmed the payment for this booking.</p>
                @endif
            </div>
        </div>
        <div class="card-body">
            @php
                $booking->refresh();
                $paidNow = $amountPaid ?? 0;
            @endphp

            <div id="payAmount">
                @if ($confirmed)
                    <div class="amount-box">
                        <div class="amount-label">Amount Paid</div>
                        <div class="amount-val">₱{{ number_format($paidNow, 2) }}</div>
                    </div>
                @else
                    <div class="pending-note">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            {{-- Hindi na "usually within a few minutes" ang
                                 sinasabi nito: hindi na kailangang bumalik
                                 o mag-refresh ang guest, kaya ang totoong
                                 tagubilin ngayon ay MANATILI lang dito. --}}
                            <strong>If you have already paid</strong>, keep this page open — we are checking
                            for your payment right now, and this page will update by itself the moment it
                            arrives. We will also send you a notification and an email.
                            <div style="margin-top:8px;">
                                <strong>If not yet</strong>, you can retry the payment from your booking—you won't
                                be charged twice.
                            </div>
                        </div>
                    </div>

                    <div class="amount-box">
                        <div class="amount-label">Amount Due</div>
                        <div class="amount-val">₱{{ number_format($booking->balance_due, 2) }}</div>
                    </div>
                @endif
            </div>

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
                <span class="val" id="payBookingStatus">
                    @if ($booking->status === 'confirmed')
                        <span class="status-confirmed">Confirmed</span>
                    @else
                        <span>{{ ucfirst($booking->status) }}</span>
                    @endif
                </span>
            </div>
            <div class="detail-row">
                <span class="lbl">Payment Status</span>
                {{-- Galing sa Booking::paymentProgressDisplay() — iisa ang
                     pinagmulan nito at ng isinasagot ng payment.status sa
                     watcher, kaya hindi puwedeng mag-iba ang sinasabi ng
                     kusang nag-update na page sa bagong na-load na page. --}}
                @php($paymentDisplay = $booking->paymentProgressDisplay())
                <span class="val" id="payPaymentStatus">
                    <span class="{{ $paymentDisplay['class'] }}">{{ $paymentDisplay['text'] }}</span>
                </span>
            </div>

            <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-primary">
                <i class="bi bi-calendar-check me-2"></i> View My Booking
            </a>
            @if (!$confirmed && $booking->balance_due > 0)
                <a href="{{ route('payment.page', $booking) }}" class="btn-secondary" id="payRetryLink">
                    <i class="bi bi-arrow-clockwise"></i> Try the payment again.
                </a>
            @endif
            <a href="{{ route('customer.home') }}" class="btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    {{-- Ang mismong page na ito ang dahilan kung bakit umiiral ang
         watcher. Ang "Waiting for Payment" ay isang estadong WALANG
         katapusan kung walang nagmamasid: asynchronous ang QR Ph, kaya
         maaaring ang webhook na lang ang magtala ng bayad, minuto
         matapos makarating dito ang guest. Walang binabantayan kapag
         nakumpirma na ang bayad — wala nang hinihintay. --}}
    @if (!$confirmed)
        @include('payment._status_watcher')

        @push('scripts')
            <script>
                (function () {
                    const BASELINE_PAID = Number(@json((float) $booking->amount_paid));

                    function peso(n) {
                        return '₱' + Number(n).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }

                    // Bawat tanong, kahit hindi pa dumarating ang bayad.
                    // Puwedeng magbago ang balanse habang bukas ang page
                    // sa ibang dahilan (halimbawa, may naitalang cash sa
                    // front desk), at mas mabuti nang tumpak ito kaysa
                    // manatiling luma hanggang mag-refresh.
                    document.addEventListener('villa:payment-state', function (e) {
                        const d = e.detail;

                        const status = document.getElementById('payPaymentStatus');
                        if (status && d.payment_display) {
                            const span = document.createElement('span');
                            span.className = d.payment_display.class;
                            span.textContent = d.payment_display.text;
                            status.replaceChildren(span);
                        }

                        const booking = document.getElementById('payBookingStatus');
                        if (booking) {
                            const span = document.createElement('span');
                            if (d.booking_status === 'confirmed') span.className = 'status-confirmed';
                            span.textContent = d.booking_status_label;
                            booking.replaceChildren(span);
                        }
                    });

                    // Dumating na. Ito ang sandaling dating hindi
                    // kailanman naaabot ng page nang hindi nagre-refresh.
                    document.addEventListener('villa:payment-received', function (e) {
                        const d = e.detail;

                        // Ang IPINAKIKITANG "Amount Paid" ay ang bagong
                        // dating na bayad, hindi ang kabuuang naibayad na
                        // — ganito rin ang ibig sabihin nito kapag
                        // server-rendered ang confirmed na estado, kaya
                        // pareho ang basa ng guest anuman ang daanan.
                        const received = Math.max(0, Number(d.amount_paid) - BASELINE_PAID);

                        const head = document.getElementById('payHead');
                        if (head) {
                            head.innerHTML =
                                '<div class="confetti">🎉</div>' +
                                '<div class="success-circle"><i class="bi bi-check-lg"></i></div>' +
                                '<h1>Payment Successful!</h1>' +
                                '<p>Your payment has been received and confirmed.</p>';
                        }

                        const amount = document.getElementById('payAmount');
                        if (amount) {
                            amount.innerHTML =
                                '<div class="amount-box">' +
                                '<div class="amount-label">Amount Paid</div>' +
                                '<div class="amount-val"></div>' +
                                '</div>';
                            amount.querySelector('.amount-val').textContent = peso(received);
                        }

                        // Walang dapat subukang muli — bayad na. Kung
                        // maiiwan ito, ang pinakamalapit na aksiyon sa
                        // guest matapos magbayad ay ang magbayad ulit.
                        document.getElementById('payRetryLink')?.remove();

                        document.title = 'Payment Successful — Villa Elena Resort';
                    });
                })();
            </script>
        @endpush
    @endif
@endsection

