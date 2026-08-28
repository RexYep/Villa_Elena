@extends('layouts.admin')

@section('title', 'Payment Detail — Villa Elena')
@section('page-title', 'Payment Detail')
@section('page-subtitle', $payment->booking->booking_ref)

@push('styles')
    <style>
        .main-content {
            max-width: 820px;
        }

        .card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-head {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-head h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .card-body {
            padding: 22px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
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
            color: var(--text-main);
        }

        /* Ang `.lbl` ay naka-scope sa loob ng `.info-row`; ito ang
           katumbas para sa mga label ng form sa labas nito. */
        .field-lbl {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
        }

        .amount-hero {
            background: var(--terracotta);
            border-radius: 16px;
            padding: 28px;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .amount-hero::before {
            content: '';
            position: absolute;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184, 148, 63, .28) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .amount-label {
            color: rgba(255, 255, 255, .55);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .amount-val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 44px;
            font-weight: 700;
            color: #fff;
            position: relative;
            z-index: 1;
        }

        .amount-ref {
            color: var(--gold-light);
            font-size: 14px;
            margin-top: 8px;
            position: relative;
            z-index: 1;
        }

        /* Badges — semantic, unchanged */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .b-qrph {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .b-cash {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .b-full_payment {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .b-partial {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .b-balance {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .b-refund {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            padding: 8px 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: #fff;
            transition: all .2s;
        }

        .back-btn:hover {
            border-color: var(--terracotta);
            color: var(--terracotta);
        }

        @media (max-width: 700px) {
            .payment-detail-grid {
                grid-template-columns: 1fr !important;
            }

            .amount-hero {
                padding: 20px;
            }

            .amount-val {
                font-size: 34px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('admin.payments.index') }}">Payments</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $payment->booking->booking_ref }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    {{-- Amount Hero --}}
    <div class="amount-hero">
        <div class="amount-label">{{ $payment->payment_type === 'refund' ? 'Refund Amount' : 'Amount Paid' }}</div>
        <div class="amount-val" style="{{ $payment->payment_type === 'refund' ? 'color:#fca5a5;' : '' }}">
            {{ $payment->payment_type === 'refund' ? '-' : '' }}₱{{ number_format($payment->amount, 2) }}
        </div>
        <div class="amount-ref">{{ $payment->booking->booking_ref }} · {{ $payment->payment_date?->format('F d, Y') }}</div>
    </div>

    <div class="payment-detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        {{-- Payment Info --}}
        <div class="card">
            <div class="card-head">
                <h3>Payment Information</h3>
            </div>
            <div class="card-body">
                <div class="info-row"><span class="lbl">Amount</span><span class="val"
                        style="font-family:'Playfair Display',serif;font-size:16px;">₱{{ number_format($payment->amount, 2) }}</span>
                </div>
                <div class="info-row"><span class="lbl">Method</span><span class="val"><span
                            class="badge b-{{ $payment->payment_method }}">{{ $payment->method_label }}</span></span>
                </div>
                <div class="info-row"><span class="lbl">Type</span><span class="val"><span
                            class="badge b-{{ $payment->payment_type }}">{{ $payment->type_label }}</span></span></div>
                <div class="info-row"><span class="lbl">Date</span><span
                        class="val">{{ $payment->payment_date?->format('M d, Y') }}</span></div>
                @if ($payment->reference_number)
                    <div class="info-row"><span class="lbl">Reference</span><span class="val"
                            style="font-size:12px;word-break:break-all;">{{ $payment->reference_number }}</span></div>
                @endif
                {{-- Magkaibang bagay ang dalawang ito, at magkatabi sila
                     nang sinasadya: ang `reference_number` ay ang PAPASOK
                     na bayad mula sa PayMongo (`pay_…`); ang
                     `transaction_ref` ay ang PALABAS na transfer na
                     ipinadala ng resort. Sa isang refund, ito ang
                     patunay na aktwal na gumalaw ang pera. --}}
                @if ($payment->transaction_ref)
                    <div class="info-row"><span class="lbl">Transfer Ref</span><span class="val"
                            style="font-size:12px;word-break:break-all;font-family:monospace;">{{ $payment->transaction_ref }}</span>
                    </div>
                @endif
                @if ($payment->notes)
                    <div class="info-row"><span class="lbl">Notes</span><span class="val"
                            style="text-align:right;max-width:200px;">{{ $payment->notes }}</span></div>
                @endif
            </div>
        </div>

        {{-- Booking Info --}}
        <div class="card">
            <div class="card-head">
                <h3>Booking Summary</h3>
            </div>
            <div class="card-body">
                <div class="info-row"><span class="lbl">Guest</span><span
                        class="val">{{ $payment->booking->user->full_name }}</span></div>
                <div class="info-row"><span class="lbl">Property</span><span
                        class="val">{{ $payment->booking->property->property_name }}</span></div>
                <div class="info-row"><span class="lbl">Check-in</span><span
                        class="val">{{ $payment->booking->check_in_date->format('M d, Y') }}</span></div>
                <div class="info-row"><span class="lbl">Check-out</span><span
                        class="val">{{ $payment->booking->check_out_date->format('M d, Y') }}</span></div>
                <div class="info-row"><span class="lbl">Total Amount</span><span
                        class="val">₱{{ number_format($payment->booking->total_amount, 2) }}</span></div>
                <div class="info-row"><span class="lbl">Amount Paid</span><span class="val"
                        style="color:#16a34a;">₱{{ number_format($payment->booking->amount_paid, 2) }}</span></div>
                @if ($payment->booking->balance_due > 0)
                    <div class="info-row"><span class="lbl">Balance Due</span><span class="val"
                            style="color:#dc2626;">₱{{ number_format($payment->booking->balance_due, 2) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Saan ipapadala ang refund ────────────────────────────────
         Ipinapakita lang para sa mga refund. Ang cash refund ay
         inaabot sa front desk, kaya walang bank destination.

         Ang buong account number ay nasa likod ng isang "reveal" —
         financial account data ito, at hindi ito kailangang nakabukas
         sa screen tuwing may nagbubukas ng pahinang ito. Kailangan
         lang ito sa mismong sandali ng pagpapadala. --}}
    @if ($payment->isRefund() && $payment->payment_method !== 'cash')
        <div class="card" style="margin-bottom:20px;">
            <div class="card-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <h3>Refund Destination</h3>
                {{-- Ang pagkakasunod ay mahalaga: `isReadyToSend()` ay
                     nangangahulugang may destinasyon AT hindi pa naipapadala.
                     Ang pagtingin lang sa `refundDestination` ay nagpapakita
                     ng "READY TO SEND" sa isang refund na naipadala na —
                     napansin sa browser pagkatapos ng isang tunay na payout. --}}
                @if ($payment->isReadyToSend())
                    <span class="badge" style="background:#dcfce7;color:#166534;">READY TO SEND</span>
                @elseif ($payment->needsRefundDestination())
                    <span class="badge" style="background:#e0e7ff;color:#3730a3;">WAITING ON GUEST</span>
                @elseif ($payment->refundDestination)
                    <span class="badge" style="background:#e2e8f0;color:#475569;">SENT</span>
                @endif
            </div>
            <div class="card-body">

                @if ($destination = $payment->refundDestination)
                    <div class="info-row"><span class="lbl">Bank / E-Wallet</span>
                        <span class="val">{{ $destination->institution_name }}</span>
                    </div>
                    <div class="info-row"><span class="lbl">Account Name</span>
                        <span class="val">{{ $destination->account_name }}</span>
                    </div>
                    <div class="info-row"><span class="lbl">Account Number</span>
                        <span class="val">
                            <details style="display:inline;">
                                <summary style="cursor:pointer;list-style:none;">
                                    {{ $destination->masked_account_number }}
                                    <span style="font-size:11px;color:var(--muted);">(show)</span>
                                </summary>
                                <span style="font-family:monospace;font-size:14px;">{{ $destination->account_number }}</span>
                            </details>
                        </span>
                    </div>
                    <div class="info-row"><span class="lbl">Provided</span>
                        <span class="val" style="font-size:12px;">
                            {{ $destination->provided_at?->format('M d, Y g:i A') }}
                            @if ($destination->providedBy)
                                · by {{ $destination->providedBy->full_name }}
                            @endif
                        </span>
                    </div>

                    @if ($payment->isAwaitingPayout())
                        <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--border);">

                            @if ($payment->isOverduePayout())
                                <div style="font-size:12px;color:#b91c1c;font-weight:600;margin-bottom:12px;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    This has been waiting {{ $payment->daysAwaitingPayout() }} days.
                                </div>
                            @endif

                            @if ($payment->hasTransferInFlight())
                                {{-- Naipadala na ang utos pero hindi pa ito na-settle.
                                     WALANG button dito nang sinasadya: ang muling
                                     pagpapadala habang nasa daan pa ang isa ay
                                     magpapadala ng pera nang dalawang beses. --}}
                                @php($inFlight = $payment->refundTransfers->firstWhere('status', 'pending'))
                                <div class="alert alert-info" style="margin:0;">
                                    <strong>A transfer is on its way.</strong>
                                    @if ($inFlight && $inFlight->provider !== 'instapay')
                                        It went out over PESONet, which clears in batches on banking days
                                        (11am, 2pm, 5pm) — expect it later today or the next banking day.
                                        The system checks on it automatically; nothing is stuck.
                                    @else
                                        It usually clears within seconds — reload this page to see the result.
                                    @endif
                                    Nothing else is needed right now.
                                </div>
                            @elseif ($payment->canSendTransfer())
                                {{-- ANG AWTOMATIKONG DAAN.
                                     Ang `confirm_amount` ay hindi dekorasyon: kung
                                     nagbago ang halaga mula nang mabuksan ang pahina,
                                     tumatanggi ang controller sa halip na magpadala
                                     ng ibang bilang kaysa sa nakita ng admin. --}}
                                <form method="POST" action="{{ route('admin.payments.send', $payment) }}">
                                    @csrf
                                    <input type="hidden" name="confirm_amount" value="{{ $payment->amount }}">

                                    <div style="font-size:13px;margin-bottom:12px;">
                                        Send <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                                        to <strong>{{ $destination->account_name }}</strong>
                                        at <strong>{{ $destination->institution_name }}</strong>
                                        ({{ $destination->masked_account_number }}).
                                    </div>

                                    <div style="font-size:11px;color:var(--muted);margin-bottom:12px;">
                                        @if ($destination->isInstant())
                                            Goes out over <strong>InstaPay</strong> from the PayMongo wallet and
                                            usually lands within seconds.
                                        @else
                                            {{-- Ang GCash ay tinatanggihan ang InstaPay mula sa PayMongo
                                                 Wallet (AC06), kaya PESONet ang daan doon — at oras, hindi
                                                 segundo, ang tagal. Sinasabi ito nang maaga para hindi
                                                 isipin ng admin na sira ang sistema. --}}
                                            Goes out over <strong>PESONet</strong>, because
                                            {{ $destination->institution_name }} does not accept InstaPay
                                            from the PayMongo wallet. PESONet clears in batches on banking
                                            days (11am, 2pm, 5pm), so it arrives
                                            <strong>later today or the next banking day</strong> — not instantly.
                                        @endif
                                        A ₱{{ number_format(\App\Models\RefundTransfer::ESTIMATED_FEE, 2) }}
                                        transfer fee applies.
                                        <strong>This cannot be reversed once it lands.</strong>
                                        A failed transfer costs nothing and can be retried.
                                    </div>

                                    @if ($warning = $destination->transferWarning())
                                        {{-- Babala, hindi harang. Ang paghaharang dito ay
                                             minsan nang naging mali, at pinigilan sana nito
                                             ang pagtuklas na gumagana pala ito sa dashboard. --}}
                                        <div class="alert alert-warning" style="margin:0 0 12px;font-size:12px;">
                                            <strong>Heads up:</strong> {{ $warning }}
                                        </div>
                                    @endif

                                    <label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;margin-bottom:14px;cursor:pointer;">
                                        <input type="checkbox" required style="margin-top:2px;">
                                        <span>I have checked the account number and name above against what the guest gave us.</span>
                                    </label>

                                    <button type="submit" class="btn-submit" style="padding:10px 22px;font-size:13px;">
                                        <i class="bi bi-send me-1"></i>
                                        Send ₱{{ number_format($payment->amount, 2) }} now
                                    </button>
                                </form>
                            @elseif ($payment->amount <= \App\Models\RefundTransfer::MINIMUM_AMOUNT)
                                {{-- Tinatanggihan ng PayMongo ang wala pang ₱5, pero
                                     ang isinasagot nito ay mukhang tungkol sa account
                                     (AC06 / RR04) — kaya sinasabi natin ang totoong
                                     dahilan dito, sa halip na hayaang bumagsak. --}}
                                <div class="alert alert-warning" style="margin:0;">
                                    <strong>Too small to transfer automatically.</strong><br>
                                    PayMongo will not send amounts of
                                    ₱{{ number_format(\App\Models\RefundTransfer::MINIMUM_AMOUNT, 2) }}
                                    or less. Send ₱{{ number_format($payment->amount, 2) }} to the account
                                    above by hand, then record it below.
                                </div>
                            @elseif (! $destination->canReceiveTransfer())
                                {{-- Walang Send button dito nang sinasadya: alam
                                     nating babagsak ito. Mas mabuting sabihin
                                     kaysa magpakita ng button na hindi gagana. --}}
                                <div class="alert alert-warning" style="margin:0;">
                                    <strong>This one has to be sent by hand.</strong><br>
                                    {{ $destination->transferBlockedReason() }}
                                    Send ₱{{ number_format($payment->amount, 2) }} to the account above
                                    from your own GCash app, then record it below.
                                </div>
                            @endif

                            {{-- ANG MANU-MANONG DAAN, na hindi kailanman inaalis.
                                 Kailangan ito kapag hindi kayang abutin ng InstaPay
                                 ang institusyon, kapag walang laman ang wallet, at
                                 kapag ipinadala ito ng admin sa sarili niyang app. --}}
                            {{-- Nakabukas agad ito kapag ito na ang TANGING daan —
                                 walang saysay na itago ang tanging magagawa. --}}
                            <details style="margin-top:16px;"
                                {{ $payment->canSendTransfer() ? '' : 'open' }}>
                                <summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--stone);">
                                    I sent it myself — record it by hand
                                </summary>

                                <form method="POST" action="{{ route('admin.payments.paidOut', $payment) }}"
                                    style="margin-top:12px;">
                                    @csrf
                                    @method('PATCH')

                                    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
                                        Only use this if you already sent
                                        <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                                        to the account above from your own GCash / Maya / bank app.
                                    </div>

                                    <label class="field-lbl">Transfer Reference Number</label>
                                    <input type="text" name="transfer_reference" class="form-control"
                                        minlength="4" maxlength="100" placeholder="e.g. 1029384756123" required
                                        value="{{ old('transfer_reference') }}">
                                    <div style="font-size:11px;color:var(--muted);margin:4px 0 14px;">
                                        From your receipt. This is the only proof the money left.
                                    </div>

                                    <button type="submit" class="btn-submit" style="padding:10px 22px;font-size:13px;">
                                        <i class="bi bi-check2-circle me-1"></i> Confirm Sent
                                    </button>
                                </form>
                            </details>
                        </div>
                    @else
                        <div style="margin-top:14px;font-size:12px;color:var(--muted);">
                            This refund has been paid out. These are the details it was sent to,
                            and they can no longer be changed.
                        </div>
                    @endif
                @endif

                {{-- Nakasara ang pagpapalit habang naglilinaw ang transfer:
                     nakatakda na kung saan pupunta ang pera, at ang
                     pagpapakita ng form ay pag-aanyaya lang ng pagkalito. --}}
                @if ($payment->isAwaitingPayout() && ! $payment->hasTransferInFlight())
                    @if (empty($institutions))
                        <div class="alert alert-danger" style="margin:14px 0 0;">
                            Couldn't load the bank / e-wallet list from PayMongo right now, so details
                            can't be entered here yet. Try again shortly.
                        </div>
                    @else
                        <details style="margin-top:{{ $payment->refundDestination ? '14px' : '0' }};"
                            {{ $payment->refundDestination ? '' : 'open' }}>
                            <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--stone);">
                                {{ $payment->refundDestination ? 'Correct these details' : 'Enter details on the guest\'s behalf' }}
                            </summary>

                            <div style="font-size:12px;color:var(--muted);margin:10px 0 14px;">
                                The guest has been asked for this in-app. Only fill it in here if they
                                gave it to you another way — by text or over the phone.
                            </div>

                            <form method="POST" action="{{ route('admin.payments.destination', $payment) }}">
                                @csrf
                                @method('PUT')

                                <div style="margin-bottom:12px;">
                                    <label class="field-lbl">Bank / E-Wallet</label>
                                    <select name="institution_bic" class="form-control" required>
                                        <option value="">Select…</option>
                                        @foreach ($institutions as $institution)
                                            <option value="{{ $institution['bic'] }}"
                                                @selected(old('institution_bic', $payment->refundDestination->institution_bic ?? '') === $institution['bic'])>
                                                {{ $institution['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                                        GCash is listed as <strong>G-Xchange, Inc.</strong>
                                    </div>
                                </div>

                                <div style="margin-bottom:12px;">
                                    <label class="field-lbl">Account / Mobile Number</label>
                                    <input type="text" name="account_number" class="form-control" inputmode="numeric"
                                        placeholder="09171234567" required
                                        value="{{ old('account_number', $payment->refundDestination->account_number ?? '') }}">
                                </div>

                                <div style="margin-bottom:14px;">
                                    <label class="field-lbl">Account Name</label>
                                    <input type="text" name="account_name" class="form-control"
                                        placeholder="Juan Dela Cruz" required
                                        value="{{ old('account_name', $payment->refundDestination->account_name ?? $payment->booking->user->full_name ?? '') }}">
                                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                                        Exactly as registered on the account — a wrong name makes the transfer fail.
                                    </div>
                                </div>

                                <button type="submit" class="btn-submit" style="padding:10px 22px;font-size:13px;">
                                    <i class="bi bi-save me-1"></i> Save Destination
                                </button>
                            </form>
                        </details>
                    @endif
                @endif

                @if (! $payment->refundDestination && ! $payment->isAwaitingPayout())
                    <div style="font-size:12px;color:var(--muted);">
                        This refund was paid out before refund destinations were recorded,
                        so there is no record of where the money was sent.
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Ang permanenteng tala ng bawat pagtatangkang ipadala ito.
         Ipinapakita rin ang mga bigo — sinasadya. Ang isang refund na
         tatlong beses bumagsak bago dumating ay isang kuwentong dapat
         nakikita ng admin, hindi tahimik na binubura. --}}
    @if ($payment->isRefund() && $payment->refundTransfers->isNotEmpty())
        <div class="card" style="margin-bottom:20px;">
            <div class="card-head">
                <h3>Transfer History</h3>
            </div>
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:560px;">
                    <tbody>
                        @foreach ($payment->refundTransfers as $transfer)
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:12px 16px;vertical-align:top;">
                                    <div style="font-size:12px;font-weight:600;">
                                        @if ($transfer->isSucceeded())
                                            <span class="badge" style="background:#dcfce7;color:#166534;">DELIVERED</span>
                                        @elseif ($transfer->isPending())
                                            <span class="badge" style="background:#fef3c7;color:#92400e;">CLEARING</span>
                                        @else
                                            <span class="badge" style="background:#fee2e2;color:#991b1b;">FAILED</span>
                                        @endif
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:6px;">
                                        {{ $transfer->created_at?->format('M d, Y g:i A') }}
                                        @if ($transfer->initiatedBy)
                                            · by {{ $transfer->initiatedBy->full_name }}
                                        @endif
                                    </div>
                                </td>
                                <td style="padding:12px 16px;vertical-align:top;font-size:12px;">
                                    <div>
                                        ₱{{ number_format((float) $transfer->amount, 2) }}
                                        to {{ $transfer->institution_name }}
                                    </div>
                                    <div style="color:var(--muted);margin-top:4px;">
                                        {{ $transfer->account_name }} · {{ $transfer->masked_account_number }}
                                        · {{ strtoupper($transfer->provider) }}
                                    </div>

                                    @if ($transfer->isSucceeded())
                                        <div style="margin-top:6px;font-family:monospace;font-size:11px;">
                                            {{ $transfer->receipt_reference }}
                                        </div>
                                        @if ((float) $transfer->fee > 0)
                                            <div style="color:var(--muted);font-size:11px;margin-top:2px;">
                                                Fee ₱{{ number_format((float) $transfer->fee, 2) }}
                                            </div>
                                        @endif
                                    @elseif ($transfer->hasFailed())
                                        <div style="margin-top:6px;color:#991b1b;">
                                            {{ $transfer->failureReason() }}
                                        </div>
                                        {{-- Ang hilaw na code ay ipinapakita rin: ito ang
                                             tanging bagay na magagamit ng admin kapag
                                             kinausap niya ang PayMongo o ang bangko. --}}
                                        @if ($transfer->provider_error_code)
                                            <div style="color:var(--muted);font-size:11px;margin-top:2px;">
                                                Code {{ $transfer->provider_error_code }} · no fee charged
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- All Payments for this Booking --}}
    <div class="card">
        <div class="card-head">
            <h3>All Payments for {{ $payment->booking->booking_ref }}</h3>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:520px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th class="text-muted-theme"
                            style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">
                            Date</th>
                        <th class="text-muted-theme"
                            style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">
                            Method</th>
                        <th class="text-muted-theme"
                            style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);">
                            Type</th>
                        <th class="text-muted-theme"
                            style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--border);text-align:right;">
                            Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->booking->payments->sortByDesc(fn($x) => [$x->payment_date, $x->id]) as $p)
                        <tr
                            style="border-bottom:1px solid #f8fafc;{{ $p->id === $payment->id ? 'background:rgba(201,168,76,.07);' : '' }}">
                            <td style="padding:12px 16px;font-size:13px;">{{ $p->payment_date?->format('M d, Y') }}</td>
                            <td style="padding:12px 16px;"><span
                                    class="badge b-{{ $p->payment_method }}">{{ $p->method_label }}</span></td>
                            <td style="padding:12px 16px;"><span
                                    class="badge b-{{ $p->payment_type }}">{{ $p->type_label }}</span></td>
                            <td
                                style="padding:12px 16px;text-align:right;font-weight:700;font-family:'Playfair Display',serif;color:{{ $p->payment_type === 'refund' ? '#dc2626' : '#0D1B2A' }};">
                                {{ $p->payment_type === 'refund' ? '-' : '' }}₱{{ number_format($p->amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('admin.payments.index') }}" class="back-btn">
        <i class="bi bi-arrow-left"></i> Back to Payments
    </a>
@endsection

