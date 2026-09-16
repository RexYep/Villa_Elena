@extends('layouts.payment')

@section('title', 'Pay Now — ' . $booking->booking_ref)

@push('styles')
    <style>
        .card {
            max-width: 480px;
        }

        .card-top {
            background: var(--stone);
            padding: 28px 32px;
        }

        .brand {
            font-family: 'Playfair Display', serif;
            color: var(--gold-light);
            font-size: 18px;
            margin-bottom: 16px;
        }

        .booking-ref-label {
            font-size: 10px;
            color: rgba(255, 255, 255, .4);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 4px;
        }

        .booking-ref {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 22px;
            font-weight: 700;
        }

        .property-name {
            color: rgba(255, 255, 255, .5);
            font-size: 13px;
            margin-top: 4px;
        }

        /* Booking summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f4efe6;
            font-size: 13px;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row .lbl {
            color: var(--muted);
        }

        .summary-row .val {
            font-weight: 500;
        }

        .summary-row.total {
            font-weight: 700;
            font-size: 16px;
            font-family: 'Playfair Display', serif;
            border-top: 2px solid var(--border);
            padding-top: 12px;
            margin-top: 4px;
        }

        .summary-row.paid {
            color: #16a34a;
        }

        .summary-row.balance {
            color: var(--terracotta);
            font-weight: 700;
        }

        /* Payment options */
        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 20px 0;
        }

        .pay-option {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px 14px;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
            position: relative;
        }

        .pay-option:hover {
            border-color: var(--stone);
        }

        .pay-option.selected {
            border-color: var(--stone);
            background: rgba(44, 36, 22, .04);
        }

        .pay-option input[type=radio] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .pay-option-amount {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--stone);
        }

        .pay-option-label {
            font-size: 11px;
            color: var(--muted);
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .pay-option-badge {
            display: inline-block;
            background: rgba(184, 148, 63, .15);
            color: var(--gold);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 5px;
        }

        .check-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--stone);
            color: #fff;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .pay-option.selected .check-icon {
            display: flex;
        }

        /* Payment methods */
        .methods-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .methods-grid {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .method-badge {
            background: var(--sand);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            color: var(--stone);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qr-hint {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            background: var(--sand);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 12px;
            line-height: 1.5;
            color: var(--stone);
            margin-bottom: 20px;
        }

        .qr-hint i {
            font-size: 15px;
            color: var(--gold);
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Submit */
        .btn-pay {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px;
            width: 100%;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all .2s;
            margin-top: 4px;
        }

        .btn-pay:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        .btn-back:hover {
            color: var(--stone);
        }

        .secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
            margin-top: 14px;
        }

        .paymongo-badge {
            background: #1a1a2e;
            color: #fff;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
            padding: 12px 16px;
            border: none;
            margin-bottom: 16px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #15803d;
        }

        @media (max-width:400px) {
            .card-top {
                padding: 22px 20px;
            }

            .card-body {
                padding: 20px;
            }

            .payment-options {
                grid-template-columns: 1fr;
            }
        }

        /* Pumapalit sa payment form kapag dumating ang bayad habang
           nakabukas pa ang page na ito — karaniwan ito sa QR Ph, kung
           saan ini-scan ng guest ang QR sa telepono habang nakabukas pa
           ang checkout sa isa pang device o tab. */
        .ck-done {
            text-align: center;
            background: rgba(22, 163, 74, .06);
            border: 1px solid rgba(22, 163, 74, .35);
            border-radius: 12px;
            padding: 22px 18px;
            margin: 20px 0;
        }

        .ck-done-icon {
            font-size: 30px;
            color: #16a34a;
            line-height: 1;
        }

        .ck-done-title {
            font-family: 'Playfair Display', serif;
            font-size: 19px;
            font-weight: 700;
            margin: 8px 0 4px;
        }

        .ck-done-note {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.55;
        }

        .ck-done-amount {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            margin-top: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="card">
        <div class="card-top">
            <div class="brand">Villa Elena Resort</div>
            <div class="booking-ref-label">Booking Reference</div>
            <div class="booking-ref">{{ $booking->booking_ref }}</div>
            <div class="property-name">{{ $booking->property->property_name }}</div>
        </div>

        <div class="card-body">

            @if (session('error'))
                <div class="alert alert-error"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
            @endif

            {{-- Booking Summary --}}
            <div style="margin-bottom:20px;">
                <div class="summary-row"><span class="lbl">Check-in</span><span
                        class="val">{{ $booking->check_in_date->format('M d, Y') }}</span></div>
                <div class="summary-row"><span class="lbl">Check-out</span><span
                        class="val">{{ $booking->check_out_date->format('M d, Y') }}</span></div>
                <div class="summary-row"><span class="lbl">Duration</span><span class="val">{{ $booking->num_nights }}
                        night{{ $booking->num_nights != 1 ? 's' : '' }}</span></div>
                <div class="summary-row total">
                    <span>Total</span><span>₱{{ number_format($booking->total_amount, 2) }}</span>
                </div>
                {{-- Laging nire-render ang row na ito, nakatago kapag wala
                     pang naibabayad: kapag dumating ang bayad habang bukas
                     ang page, kailangan itong may pagsusuotan — walang
                     element na hindi umiiral sa DOM. --}}
                <div id="ckPaidRow" @if ($booking->amount_paid <= 0) style="display:none" @endif>
                    <div class="summary-row paid"><span>Already
                            Paid</span><span id="ckPaid">₱{{ number_format($booking->amount_paid, 2) }}</span></div>
                </div>
                <div class="summary-row balance"><span>Balance
                        Due</span><span id="ckBalance">₱{{ number_format($booking->balance_due, 2) }}</span></div>
            </div>

            {{-- Payment Options --}}
            <div id="ckForm">
            <form method="POST" action="{{ route('payment.checkout', $booking) }}" id="payForm">
                @csrf
                <input type="hidden" name="payment_type" id="selectedPaymentType" value="deposit">

                @if ($isDepositOnly)
                    <div class="section-label" style="font-size:12px;color:#374151;margin-bottom:10px;">Choose Payment
                        Option</div>

                    @if ($forceFullPayment)
                        {{-- Anti-abuse: naka-flag ang account na ito dahil sa
                 cancellation history, kaya full payment na lang ang
                 pinapayagan — walang deposit option. --}}
                        <div
                            style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#92400e;line-height:1.6;">
                            <i class="bi bi-info-circle me-1"></i>
                            Due to this account's cancellation history, only full payment is available for this booking.
                        </div>
                        <div
                            style="background:var(--sand);border-radius:12px;padding:16px;margin-bottom:20px;text-align:center;">
                            <div class="text-muted-theme"
                                style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Full
                                Payment Required</div>
                            <div style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;">
                                ₱{{ number_format($booking->total_amount, 2) }}</div>
                            <input type="hidden" name="payment_type" value="full_payment">
                        </div>
                    @else
                        <div class="payment-options">
                            <label class="pay-option selected" onclick="selectOption(this, 'deposit')">
                                <input type="radio" name="_pay_opt" value="deposit" checked>
                                <div class="check-icon"><i class="bi bi-check"></i></div>
                                <div class="pay-option-amount">₱{{ number_format($depositAmount, 2) }}</div>
                                <div class="pay-option-label">Deposit</div>
                                <div class="pay-option-badge">{{ $depositPct }}% of total</div>
                            </label>
                            <label class="pay-option" onclick="selectOption(this, 'full_payment')">
                                <input type="radio" name="_pay_opt" value="full_payment">
                                <div class="check-icon"><i class="bi bi-check"></i></div>
                                <div class="pay-option-amount">₱{{ number_format($booking->total_amount, 2) }}</div>
                                <div class="pay-option-label">Full Payment</div>
                                <div class="pay-option-badge">Pay in full</div>
                            </label>
                        </div>
                    @endif
                @else
                    {{-- Already paid deposit — pay remaining balance --}}
                    <div
                        style="background:var(--sand);border-radius:12px;padding:16px;margin-bottom:20px;text-align:center;">
                        <div class="text-muted-theme"
                            style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Paying
                            Remaining Balance</div>
                        <div style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;">
                            ₱{{ number_format($booking->balance_due, 2) }}</div>
                        <input type="hidden" name="payment_type" value="full_payment">
                    </div>
                @endif

                {{-- Accepted Methods --}}
                {{-- Ipinapakita ang mga wallet/bank name, hindi lang "QR Ph" —
                 hindi pamilyar sa karamihan ang pangalang QR Ph, at kung
                 "QR Ph" lang ang nakalagay, aakalain nilang wala nang
                 GCash gayong ito mismo ang gagamitin nila sa pag-scan. --}}
                <div class="methods-label">Accepted Payment Methods</div>
                <div class="methods-grid">
                    <span class="method-badge">📷 QR Ph</span>
                    <span class="method-badge">💙 GCash</span>
                    <span class="method-badge">💚 Maya</span>
                    <span class="method-badge">🏦 Bank apps</span>
                </div>
                <div class="qr-hint">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>We will display a <strong>QR code</strong> — simply scan it using GCash, Maya, or
                        any bank app that accepts QR Ph.</span>
                </div>

                <button type="submit" class="btn-pay" id="payBtn">
                    <i class="bi bi-lock-fill"></i>
                    Pay Now via PayMongo
                </button>
            </form>
            </div>

            <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-back">
                ← Back to booking details
            </a>

            <div class="secure-note">
                <i class="bi bi-shield-check" style="color:#16a34a;"></i>
                Secured by <span class="paymongo-badge">PayMongo</span>
            </div>
        </div>
    </div>

    {{-- Bakit nagbabantay ang page na ito gayong aalis naman ang guest
         patungong PayMongo: madalas HINDI ito nasasara. Ini-scan ang QR
         sa telepono habang nakabukas pa ang checkout sa laptop, o
         bumabalik sila rito gamit ang Back button. Kapag natanggap na
         ang bayad, ang naiwang page ay nagpapakita ng lumang balanse at
         ng buhay na "Pay Now" na button — at ang pinakamalapit na
         susunod na aksiyon sa isang bayad nang guest ay ang magbayad
         ulit. Iyon ang eksaktong dobleng singil na hindi kayang saluhin
         ng idempotency, dahil dalawang tunay na bayad iyon. --}}
    @include('payment._status_watcher')
@endsection

@push('scripts')
    <script>
        function selectOption(el, type) {
            document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedPaymentType').value = type;
        }

        // Isang pindot lang, isang singil.
        //
        // Sa mabagal na koneksiyon, mukhang walang nangyari matapos ang
        // unang pindot, kaya pinipindot ulit ito ng guest — at ang bawat
        // POST ay dating gumagawa ng sariling PayMongo checkout session,
        // kaya dalawang buhay na QR para sa iisang booking. Ang server
        // ang totoong tagapagpatupad nito (may lock at may session reuse
        // ang createCheckout()); dito lang ito ginagawang kitang-kita,
        // para hindi na muling pindutin ng guest.
        (function () {
            const form = document.getElementById('payForm');
            const btn = document.getElementById('payBtn');

            if (!form || !btn) return;

            form.addEventListener('submit', function (e) {
                if (form.dataset.submitted === '1') {
                    e.preventDefault();
                    return;
                }

                form.dataset.submitted = '1';
                btn.disabled = true;
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Opening secure checkout…';
            });

            // Kapag bumalik ang guest gamit ang Back button, ibinabalik
            // ng browser ang page mula sa bfcache — kasama ang naka-
            // disable na button. Kung hindi ito ibabalik sa dati,
            // mukhang sira ang page at wala silang magagawa.
            window.addEventListener('pageshow', function (event) {
                if (!event.persisted) return;

                form.dataset.submitted = '';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-lock-fill"></i> Pay Now via PayMongo';
            });
        })();

        // ── Realtime: pinananatiling totoo ang page habang nakabukas ──
        (function () {
            const BASELINE_PAID = Number(@json((float) $booking->amount_paid));
            const BOOKING_URL = @json(route('customer.bookings.show', $booking));
            const PAY_URL = @json(route('payment.page', $booking));

            function peso(n) {
                return '₱' + Number(n).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            document.addEventListener('villa:payment-state', function (e) {
                const d = e.detail;

                const balance = document.getElementById('ckBalance');
                if (balance) balance.textContent = peso(d.balance_due);

                const paid = document.getElementById('ckPaid');
                const paidRow = document.getElementById('ckPaidRow');
                if (paid) paid.textContent = peso(d.amount_paid);
                if (paidRow) paidRow.style.display = Number(d.amount_paid) > 0 ? '' : 'none';
            });

            document.addEventListener('villa:payment-received', function (e) {
                const d = e.detail;
                const received = Math.max(0, Number(d.amount_paid) - BASELINE_PAID);
                const settled = Number(d.balance_due) <= 0;

                // Inaalis ang buong form, hindi lang dini-disable ang
                // button: ang natitirang radio at halaga ay naglalarawan
                // ng isang pagpiling wala nang saysay, at ang page ay
                // hindi na "checkout" sa puntong ito.
                const wrap = document.getElementById('ckForm');
                if (!wrap) return;

                const panel = document.createElement('div');
                panel.className = 'ck-done';
                panel.innerHTML =
                    '<div class="ck-done-icon"><i class="bi bi-check-circle-fill"></i></div>' +
                    '<div class="ck-done-title">Payment Received</div>' +
                    '<div class="ck-done-note"></div>' +
                    '<div class="ck-done-amount"></div>';

                panel.querySelector('.ck-done-note').textContent = settled
                    ? 'This booking is now fully paid. There is nothing left to pay.'
                    : 'Your payment has been recorded. A balance of ' + peso(d.balance_due) + ' remains.';
                panel.querySelector('.ck-done-amount').textContent = peso(received);

                // May natitira pang balanse: ang bagong laman ng page na
                // ito ang tamang susunod na hakbang, dahil sa BAGONG
                // halaga na. Hindi ito puwedeng i-update sa lugar —
                // nakatali ang deposit/full na pagpipilian sa balanseng
                // umiral noong na-render ang page.
                const link = document.createElement('a');
                link.className = 'btn-pay';
                link.href = settled ? BOOKING_URL : PAY_URL;
                link.style.textDecoration = 'none';
                link.innerHTML = settled
                    ? '<i class="bi bi-calendar-check"></i> View My Booking'
                    : '<i class="bi bi-arrow-right-circle"></i> Pay Remaining Balance';

                wrap.replaceChildren(panel, link);
            });
        })();
    </script>
@endpush

