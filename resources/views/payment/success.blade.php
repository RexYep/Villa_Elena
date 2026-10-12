@extends('layouts.payment')

@php
    // Puwedeng marating ang page na ito nang HINDI PA bayad — normal ito
    // sa QR Ph, kung saan ini-scan ng guest ang QR sa ibang device at
    // maaaring hindi pa nagse-settle ang bayad pagbalik niya rito.
    // Kailangan itong sabihin nang tapat sa halip na magpakita ng
    // berdeng tsek na "Payment Successful" na wala namang pinatutunayan.
    $booking->refresh();

    $confirmed = $paymentConfirmed ?? true;
    $cancelled = $booking->status === 'cancelled';

    // APAT ang estado ng page na ito, hindi dalawa (v7.75):
    //
    //   paid      — may natanggap na bayad, buhay ang booking.
    //   waiting   — wala pang nakukumpirmang bayad, buhay ang booking.
    //   late      — may natanggap na bayad pero CANCELLED ang booking:
    //               dumating ito matapos ma-cancel at hindi na naibalik
    //               (Booking::reinstateAfterLatePayment()). Totoo ang
    //               "received"; hindi totoo ang "confirmed".
    //   cancelled — walang bayad AT cancelled ang booking. Dating
    //               "Waiting for Payment" ang ipinapakita rito — "keep this
    //               page open, we are checking" para sa booking na wala
    //               nang hinihintay.
    $state = match (true) {
        $confirmed && $cancelled => 'late',
        $confirmed => 'paid',
        $cancelled => 'cancelled',
        default => 'waiting',
    };

    // Iisang kopya ng ulo ng card para sa unang render at sa script sa
    // ibaba, na nagpapalit nito kapag nagbago ang estado habang bukas ang
    // page. `tone` ang kulay ng bilog.
    $heads = [
        'paid' => [
            'icon' => 'bi-check-lg', 'tone' => 'ok', 'tab' => 'Payment Successful',
            'title' => 'Payment Successful!',
            'sub' => 'Your payment has been received and confirmed.',
        ],
        'waiting' => [
            'icon' => 'bi-hourglass-split', 'tone' => 'wait', 'tab' => 'Waiting for Payment Confirmation',
            'title' => 'Waiting for Payment',
            'sub' => 'We have not yet confirmed the payment for this booking.',
        ],
        'late' => [
            'icon' => 'bi-exclamation-triangle', 'tone' => 'wait', 'tab' => 'Payment Received',
            'title' => 'Payment Received',
            'sub' => 'This booking was already cancelled when your payment arrived.',
        ],
        'cancelled' => [
            'icon' => 'bi-x-lg', 'tone' => 'off', 'tab' => 'Booking Cancelled',
            'title' => 'Booking Cancelled',
            'sub' => 'There is no payment to wait for on this booking.',
        ],
    ];
    $head = $heads[$state];

    // May binabantayan pa ba? Habang naghihintay, oo. Sa cancelled na
    // booking, kapag may checkout session pa itong hawak lang: iniiwan ng
    // Booking::closeCheckoutSession() ang id kapag TUMANGGI ang PayMongo na
    // isara ito (bayad na o binabayaran) o hindi ito naabot — iyon mismo ang
    // mga kasong may bayad pang maaaring dumating. Kapag wala nang id, sarado
    // na ang session at walang darating.
    $watching = $state === 'waiting' || ($state === 'cancelled' && $booking->paymongo_session_id);

    $paidNow = $amountPaid ?? 0;

    // Para sa `late`: "the resort will contact you" na walang paraan para
    // maabot ang resort. Galing sa Settings, gaya ng footer ng mga email.
    $resort = \App\Support\GuestEmail::contact();
    // Mga link, para isang tap lang sa telepono. Naka-escape rito dahil
    // `{!! !!}` ang nagpi-print nito sa ibaba.
    $reachUs = array_filter([
        $resort['phone']
            ? 'call <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $resort['phone'])) . '">' . e($resort['phone']) . '</a>'
            : null,
        $resort['email']
            ? 'email <a href="mailto:' . e($resort['email']) . '">' . e($resort['email']) . '</a>'
            : null,
    ]);

    $paymentDisplay = $booking->paymentProgressDisplay();
    $statusDisplay = $booking->statusDisplay();
@endphp

@section('title', $head['tab'] . ' — Villa Elena Resort')

@push('styles')
    <style>
        .pay-main {
            width: 100%;
            max-width: 440px;
        }

        /* Maraming elemento rito ang may sariling `display`, na tatalo sa
           hidden attribute kung wala ito. */
        .card [hidden] {
            display: none !important;
        }

        .card-top {
            background: var(--stone);
            padding: 26px 24px 24px;
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

        .head-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            position: relative;
            z-index: 1;
            border: 2px solid var(--gold);
            background: rgba(184, 148, 63, .15);
            color: var(--gold);
            font-size: 22px;
        }

        .head-circle.is-ok {
            border-color: #4ade80;
            background: rgba(74, 222, 128, .15);
            color: #4ade80;
        }

        .head-circle.is-off {
            border-color: rgba(255, 255, 255, .55);
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 255, 255, .85);
        }

        .card-top h1 {
            font-family: var(--font-display);
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        /* .8, hindi .45: ang dating kulay ay 4.26:1 sa 13px, kulang sa 4.5:1,
           at sa `late` ito ang pinakamahalagang pangungusap sa page. */
        .card-top p {
            color: rgba(255, 255, 255, .8);
            font-size: 14px;
            line-height: 1.45;
            margin: 6px 0 0;
            position: relative;
            z-index: 1;
        }

        .amount-box {
            background: var(--sand);
            border-radius: 14px;
            padding: 14px 16px;
            text-align: center;
            margin-bottom: 16px;
        }

        .amount-label {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .amount-val {
            font-family: var(--font-display);
            font-size: 30px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--stone);
        }

        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            padding: 8px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row dt {
            color: var(--muted);
            font-weight: 400;
            flex-shrink: 0;
        }

        .detail-row dd {
            margin: 0;
            font-weight: 500;
            text-align: right;
        }

        .status-confirmed,
        .status-partial,
        .status-unpaid,
        .status-cancelled {
            display: inline-block;
            white-space: nowrap;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-confirmed {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .status-partial,
        .status-unpaid {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .status-cancelled {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .details {
            margin: 18px 0 0;
        }

        .btn-primary {
            display: block;
            background: var(--btn-primary);
            color: #fff;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 4px;
            transition: background-color .2s;
        }

        .btn-primary:hover {
            background: var(--btn-primary-hover);
            color: #fff;
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

        .pay-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            background: var(--sand);
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--stone);
            margin-bottom: 16px;
        }

        .pay-note > i {
            font-size: 15px;
            color: var(--gold-text);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .pay-note p {
            margin: 0 0 6px;
        }

        .pay-note a {
            color: var(--stone);
            font-weight: 600;
        }

        .pay-note div > :last-child {
            margin-bottom: 0;
        }

        /* Ang tanging gumagalaw sa page: sinasagot nito ang "tumitingin pa
           ba ito?". Pinapatigil ito ng reduced-motion rule sa base.css. */
        .checking {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .checking-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--tag-green-fg);
            animation: checking-pulse 1.4s ease-in-out infinite;
        }

        @keyframes checking-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .25; }
        }

        .btn-check {
            margin-top: 8px;
            background: none;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            padding: 7px 14px;
            font: inherit;
            font-weight: 600;
            color: var(--stone);
            cursor: pointer;
        }

        .btn-check:hover {
            background: #fff;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 20px;
            }
        }

        /* Sa pinakamakitid na telepono ay hindi na kasya ang label at ang
           halaga sa iisang linya; mas malinis ang magkapatong kaysa sa
           halagang napuputol sa gitna. */
        @media (max-width: 360px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }

            .detail-row dd {
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    <main class="pay-main">
        <div class="card">
            <div class="card-top">
                {{-- Pinapalitan ng script sa ibaba ang laman nito kapag
                     nagbago ang estado habang bukas ang page. `role="status"`
                     para masabi rin iyon ng screen reader: dati, tahimik na
                     nagiging "Payment Successful" ang "Waiting for Payment". --}}
                <div id="payHead" role="status">
                    <div class="head-circle is-{{ $head['tone'] }}"><i class="bi {{ $head['icon'] }}"
                            aria-hidden="true"></i></div>
                    <h1>{{ $head['title'] }}</h1>
                    <p>{{ $head['sub'] }}</p>
                </div>
            </div>

            <div class="card-body">
                {{-- Ang tatlong tala sa ibaba ay laging nasa DOM kapag may
                     script na puwedeng magpalit ng estado, at nakatago ang
                     hindi kailangan. --}}

                {{-- late: bayad na, cancelled ang booking. --}}
                <div class="pay-note" id="payLateNote" @if ($state !== 'late') hidden @endif>
                    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                    <div>
                        <p>Your payment was received, but this booking had already been cancelled and could not be
                            restored. The resort has been notified and will contact you about this payment.</p>
                        @if ($reachUs)
                            <p>You can also {!! implode(' or ', $reachUs) !!}.</p>
                        @endif
                    </div>
                </div>

                {{-- cancelled: walang bayad, cancelled ang booking. --}}
                <div class="pay-note" id="payCancelledNote" @if ($state !== 'cancelled') hidden @endif>
                    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                    <div>
                        <p>This booking was cancelled, so nothing is due. Open the booking to see why.</p>
                        @if ($watching)
                            <div data-checking>
                                <p><strong>Paid just before that happened?</strong> Keep this page open. A payment
                                    that arrives is still recorded, and this page will update by itself.</p>
                            </div>
                            <div data-stopped hidden>
                                <p>We have stopped checking for a payment.</p>
                                <button type="button" class="btn-check" data-check-again>Check again</button>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- waiting: wala pang nakukumpirmang bayad. --}}
                <div id="payWaiting" @if ($state !== 'waiting') hidden @endif>
                    @if ($state === 'waiting')
                        {{-- Ang tunay na deadline. Ipinapakita ito ng checkout
                             at ng booking details; ang page na ito, kung saan
                             mismo naghihintay ang guest, ay wala nito. Hindi
                             ito umaalis sa page sa pagtatapos ng oras: ang
                             script sa ibaba ang nagpapakita ng resulta. --}}
                        @include('partials.hold_deadline', ['holdStaysOnPage' => true])

                        <div class="pay-note">
                            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                            <div>
                                <div data-checking>
                                    <p class="checking"><span class="checking-dot" aria-hidden="true"></span>
                                        Checking for your payment</p>
                                    <p><strong>Already paid?</strong> Keep this page open. It will update by itself
                                        when the payment arrives.</p>
                                </div>
                                {{-- Tumitigil ang pagtatanong pagkatapos ng 20
                                     minuto (payment/_status_watcher). Dating
                                     nananatili ang "we are checking right now"
                                     matapos iyon. --}}
                                <div data-stopped hidden>
                                    <p><strong>We have stopped checking automatically.</strong> If you have paid,
                                        check again and this page will look for your payment.</p>
                                    <button type="button" class="btn-check" data-check-again>Check again</button>
                                </div>
                                @if ($booking->balance_due > 0)
                                    <p id="payNotPaid"><strong>Not paid yet?</strong> You can try the payment again
                                        below. You will not be charged twice.</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Ang halagang natanggap: bayad na lang ang may ganitong
                     kahon. Habang naghihintay ay dating "Amount Due" ito na may
                     BUONG balanse ng booking — ang guest na nagbabayad ng 50%
                     deposit sa ₱4,000 na booking ay sinasabihang ₱4,000 ang
                     dapat bayaran. Ang balanse ay isa na lang hanay sa mga
                     detalye sa ibaba. --}}
                <div class="amount-box" id="payAmount" @if (!$confirmed) hidden @endif>
                    <div class="amount-label" id="payAmountLabel">
                        {{ ($amountIsTotal ?? false) ? 'Total paid' : 'Amount paid' }}</div>
                    <div class="amount-val" id="payAmountValue">₱{{ number_format($paidNow, 2) }}</div>
                </div>

                {{-- Ang mga aksiyon ay NAUUNA sa mga detalye, sa lahat ng estado.
                     Sa isang 667px na telepono ay nasa 990px ang button na ito
                     habang naghihintay — lampas isang buong screen pababa. Iisa
                     ang ayos sa bawat estado dahil pinapalitan ng script ang
                     estado nang hindi nire-reload ang page. --}}
                <div class="actions">
                    <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-primary">
                        <i class="bi bi-calendar-check me-2" aria-hidden="true"></i> View My Booking
                    </a>
                    @if ($state === 'waiting' && $booking->balance_due > 0)
                        <a href="{{ route('payment.page', $booking) }}" class="btn-secondary" id="payRetryLink">
                            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Try the payment again
                        </a>
                    @endif
                    <a href="{{ route('customer.home') }}" class="btn-secondary">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Dashboard
                    </a>
                </div>

                {{-- Ang slot at ang oras ang paglalarawan ng booking, hindi
                     dalawang petsa (tingnan ang "UI conventions" sa CLAUDE.md):
                     ang Day booking ay dating "Check-in Nov 29 / Check-out
                     Nov 29". Wala ring "Property" na hanay: iisa ang villa. --}}
                <dl class="details">
                    <div class="detail-row">
                        <dt>Booking reference</dt>
                        <dd>{{ $booking->booking_ref }}</dd>
                    </div>
                    @if ($booking->slot_name)
                        <div class="detail-row">
                            <dt>Slot</dt>
                            <dd>{{ $booking->slot_name }}</dd>
                        </div>
                    @endif
                    <div class="detail-row">
                        <dt>Check-in</dt>
                        <dd>{{ $booking->check_in_date->format('D, M j, Y') }}, {{ $booking->check_in_time_label }}</dd>
                    </div>
                    <div class="detail-row">
                        <dt>Check-out</dt>
                        <dd>{{ $booking->check_out_date->format('D, M j, Y') }}, {{ $booking->check_out_time_label }}</dd>
                    </div>
                    <div class="detail-row">
                        <dt>Booking status</dt>
                        {{-- Galing sa Booking::statusDisplay() at
                             ::paymentProgressDisplay() — iisa ang pinagmulan
                             nito at ng isinasagot ng payment.status sa
                             watcher, kaya hindi puwedeng mag-iba ang sinasabi
                             ng kusang nag-update na page sa bagong na-load. --}}
                        <dd id="payBookingStatus">
                            <span class="{{ $statusDisplay['class'] }}">{{ $statusDisplay['text'] }}</span>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Payment status</dt>
                        <dd id="payPaymentStatus">
                            <span class="{{ $paymentDisplay['class'] }}">{{ $paymentDisplay['text'] }}</span>
                        </dd>
                    </div>
                    {{-- Habang naghihintay lang: kung magkano pa ang natitira
                         sa booking. Sinusundan nito ang bawat sagot ng
                         payment.status. --}}
                    <div class="detail-row" id="payBalanceRow" @if ($state !== 'waiting') hidden @endif>
                        <dt>Booking balance</dt>
                        <dd id="payBalanceValue">₱{{ number_format($booking->balance_due, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </main>

    {{-- Ang mismong page na ito ang dahilan kung bakit umiiral ang
         watcher. Ang "Waiting for Payment" ay isang estadong WALANG
         katapusan kung walang nagmamasid: asynchronous ang QR Ph, kaya
         maaaring ang webhook na lang ang magtala ng bayad, minuto
         matapos makarating dito ang guest. Walang binabantayan kapag
         nakumpirma na ang bayad, o kapag cancelled ang booking at sarado na
         ang checkout session nito — wala nang hinihintay. --}}
    @if ($watching)
        @include('payment._status_watcher')

        @push('scripts')
            <script>
                (function () {
                    const BASELINE_PAID = Number({{ Js::from((float) $booking->amount_paid) }});
                    const HEADS = {{ Js::from($heads) }};
                    const TAB_SUFFIX = ' — Villa Elena Resort';

                    let state = {{ Js::from($state) }};
                    let received = false;

                    function el(id) {
                        return document.getElementById(id);
                    }

                    function show(id, on) {
                        const node = el(id);
                        if (node) node.hidden = !on;
                    }

                    function peso(n) {
                        return '₱' + Number(n).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }

                    function pill(display) {
                        const span = document.createElement('span');
                        span.className = display.class;
                        span.textContent = display.text;
                        return span;
                    }

                    // Ang buong page ay sumusunod sa iisang estado. Tinatawag
                    // ito sa bawat sagot ng payment.status, hindi minsan lang:
                    // ibinabalik ang isang na-cancel na booking sa hiwalay na
                    // hakbang matapos maitala ang bayad, kaya puwedeng
                    // "cancelled" pa ang unang basa at "confirmed" na ang
                    // kasunod.
                    function setState(next) {
                        if (next === state) return;
                        state = next;

                        const head = el('payHead');
                        const h = HEADS[next];

                        if (head) {
                            const circle = document.createElement('div');
                            circle.className = 'head-circle is-' + h.tone;
                            const icon = document.createElement('i');
                            icon.className = 'bi ' + h.icon;
                            icon.setAttribute('aria-hidden', 'true');
                            circle.appendChild(icon);

                            const title = document.createElement('h1');
                            title.textContent = h.title;
                            const sub = document.createElement('p');
                            sub.textContent = h.sub;

                            head.replaceChildren(circle, title, sub);
                        }

                        show('payWaiting', next === 'waiting');
                        show('payCancelledNote', next === 'cancelled');
                        show('payLateNote', next === 'late');
                        show('payAmount', next === 'paid' || next === 'late');
                        show('payBalanceRow', next === 'waiting');
                        show('payRetryLink', next === 'waiting');

                        // Ang deadline ay para lang sa naghihintay.
                        if (next !== 'waiting') show('holdDeadline', false);

                        document.title = h.tab + TAB_SUFFIX;
                    }

                    // Bawat tanong, kahit hindi pa dumarating ang bayad.
                    // Puwedeng magbago ang booking habang bukas ang page sa
                    // ibang dahilan — lumipas ang hold at na-cancel ito, o may
                    // naitalang cash sa front desk — at dating ang dalawang
                    // pill lang sa ibaba ang sumusunod: "Waiting for Payment"
                    // pa rin ang ulo ng isang booking na cancelled na.
                    document.addEventListener('villa:payment-state', function (e) {
                        const d = e.detail;
                        const isCancelled = d.booking_status === 'cancelled';

                        if (d.payment_display) el('payPaymentStatus')?.replaceChildren(pill(d.payment_display));
                        if (d.booking_status_display) el('payBookingStatus')?.replaceChildren(pill(d.booking_status_display));

                        if (received) {
                            setState(isCancelled ? 'late' : 'paid');
                            return;
                        }

                        setState(isCancelled ? 'cancelled' : 'waiting');

                        if (!isCancelled) {
                            const balance = el('payBalanceValue');
                            if (balance) balance.textContent = peso(d.balance_due);
                        }
                    });

                    // Dumating na. Ito ang sandaling dating hindi
                    // kailanman naaabot ng page nang hindi nagre-refresh.
                    document.addEventListener('villa:payment-received', function (e) {
                        const d = e.detail;

                        // Ang IPINAKIKITANG "Amount paid" ay ang bagong
                        // dating na bayad, hindi ang kabuuang naibayad na
                        // — ganito rin ang ibig sabihin nito kapag
                        // server-rendered ang confirmed na estado, kaya
                        // pareho ang basa ng guest anuman ang daanan.
                        const paidNow = Math.max(0, Number(d.amount_paid) - BASELINE_PAID);

                        received = true;

                        const label = el('payAmountLabel');
                        const value = el('payAmountValue');
                        if (label) label.textContent = 'Amount paid';
                        if (value) value.textContent = peso(paidNow);

                        setState(d.booking_status === 'cancelled' ? 'late' : 'paid');

                        // Walang dapat subukang muli — bayad na. Kung
                        // maiiwan ito, ang pinakamalapit na aksiyon sa
                        // guest matapos magbayad ay ang magbayad ulit.
                        el('payRetryLink')?.remove();
                    });

                    // Lumipas ang oras ng pagbabayad (partials/hold_deadline).
                    // Tatanggihan na ng checkout page ang isa pang subok.
                    document.addEventListener('villa:hold-ended', function () {
                        show('payRetryLink', false);
                        show('payNotPaid', false);
                    });

                    // Tumigil na ang pagtatanong. Sabihin iyon, at ibigay ang
                    // paraan para tumingin ulit: ang pag-reload ay dumadaan
                    // sa success(), na nagtatanong mismo sa PayMongo.
                    document.addEventListener('villa:payment-watch-ended', function () {
                        if (received) return;

                        document.querySelectorAll('[data-checking]').forEach(function (node) { node.hidden = true; });
                        document.querySelectorAll('[data-stopped]').forEach(function (node) { node.hidden = false; });
                    });

                    document.querySelectorAll('[data-check-again]').forEach(function (button) {
                        button.addEventListener('click', function () { window.location.reload(); });
                    });
                })();
            </script>
        @endpush
    @endif
@endsection
