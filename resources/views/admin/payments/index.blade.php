@extends('layouts.admin')

@section('title', 'Payments — Villa Elena Admin')
@section('page-title', 'Payments')
@section('page-subtitle', 'All transactions and payment records')

@section('topbar-right')
    <button class="btn-navy" onclick="openRecordModal()">
        <i class="bi bi-plus-lg"></i> Record Payment
    </button>
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </form>
@endsection

@push('styles')
    <style>
        /* ── STAT CARDS ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 20px 22px;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44, 36, 22, .09);
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            margin-bottom: 12px;
            background: var(--gold-dim);
            color: var(--gold);
        }

        .stat-val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── FILTER CARD ── */
        .filter-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 22px;
            margin-bottom: 20px;
        }

        .form-label-sm {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        /* ── TABLE CARD ── */
        .table-card-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--stone);
        }

        thead tr {
            background: var(--sand);
        }

        thead th {
            padding: 11px 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--sand);
        }

        tbody td {
            padding: 13px 16px;
            font-size: 13px;
            vertical-align: middle;
            color: var(--text-main);
        }

        /* ── BADGES — semantic, unchanged ── */
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        .b-cash {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .b-qrph {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
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

        .b-awaiting {
            background: #fef3c7;
            color: #92400e;
            margin-left: 4px;
        }

        /* Naghihintay pa ng detalye ng guest — hindi pa ito maipapadala
           kahit gustuhin mo. Iba ang kulay sa "NOT SENT" dahil iba ang
           kailangang gawin: ang isa ay hinihintay ang guest, ang isa ay
           hinihintay ka. */
        .b-nodest {
            background: #e0e7ff;
            color: #3730a3;
            margin-left: 4px;
        }

        /* Nasa daan na ang pera. Hindi ito estadong may kailangang
           gawin — kaya mainit ang kulay para makita, pero hindi pula:
           walang mali, hindi pa lang tapos. */
        .b-clearing {
            background: #fef3c7;
            color: #92400e;
            margin-left: 4px;
        }

        /* Matagal nang nakaupo. Pinakamalakas na kulay sa tatlo dahil
           ito ang tanging estado kung saan mayroon nang aktibong
           napapako ang guest. */
        .b-overdue {
            background: #fee2e2;
            color: #b91c1c;
            margin-left: 4px;
        }

        /* ── BUTTONS ── */
        .btn-navy {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
            text-decoration: none;
        }

        .btn-navy:hover {
            background: var(--gold);
            color: #fff;
        }

        .view-link {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .view-link:hover {
            color: var(--terracotta);
        }

        .refund-link {
            color: #dc2626;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        /* ── MODAL ── */
        .modal-box {
            width: 480px;
        }

        .modal-head {
            background: var(--terracotta);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .mb-12 {
            margin-bottom: 12px;
        }

        .payments-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        /* This table has NINE columns and wants ~1000px even with short sample
           data; real notes and guest names push it wider. admin.css only turns
           .table-card into a scroller below 900px, so from 900px up to roughly
           1350px the base `overflow: hidden` just cut the View / Refund column
           off with no scrollbar to reveal it (measured: a 999px table inside a
           659px card at a 1000px viewport). Prefixed with .main-content for
           specificity — admin.css sets `overflow: hidden` on a bare .table-card,
           and `composer dev` injects admin.css after this block. */
        .main-content .table-card {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Both sit inside that scroller, so without this they slide out of view
           with the table when it is dragged sideways. */
        .table-card>.table-card-header,
        .table-card>.pagination-wrap {
            position: sticky;
            left: 0;
        }

        @media (max-width: 1100px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }

            .payments-filter-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (max-width: 700px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            .payments-filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {

            /* Two columns, not one. Five full-width cards is most of a phone
               screen of chrome before the first payment row. */
            .stats-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 15px;
                margin-bottom: 8px;
            }

            .stat-val {
                font-size: 20px;
            }

            .payments-filter-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            /* The banner is a nowrap flex row whose button carries
               `white-space: nowrap`, so the button kept its 110px and the
               paragraph was squeezed into a 163px ribbon — 338px tall on a 360px
               screen. Wrapping puts the button on its own line and gives the text
               the full width. */
            .alert-warning {
                flex-wrap: wrap;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    {{-- Ang mga validation error ay hindi kailanman naipapakita rito.
         Ang pahinang ito ay nagpo-post ng dalawang form na may
         validation (ang refund modal, at ngayon ang mark-paid-out), at
         ang dalawa ay nagba-`back()->withErrors()` — na tahimik na
         walang ginagawa nang wala ito. Napansin ito sa browser: isang
         tinanggihang transfer reference ay nagmukhang parang walang
         pumindot sa button. --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stat Cards --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-val">₱{{ number_format($totalRevenue, 2) }}</div>
            <div class="stat-lbl">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-val">₱{{ number_format($todayRevenue, 2) }}</div>
            <div class="stat-lbl">Today's Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-val">₱{{ number_format($monthRevenue, 2) }}</div>
            <div class="stat-lbl">This Month</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7; color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">₱{{ number_format($pendingBalance, 2) }}</div>
            <div class="stat-lbl">Pending Balance</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="bi bi-arrow-counterclockwise"></i>
            </div>
            <div class="stat-val">₱{{ number_format($totalRefunds, 2) }}</div>
            <div class="stat-lbl">Total Refunds</div>
        </div>
    </div>

    {{-- Mga refund na inaprubahan na pero hindi pa naipapadala ang pera --}}
    @if ($pendingRefundsCount > 0)
        <div class="alert alert-warning" style="display:flex;align-items:center;gap:12px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:20px;"></i>
            <div style="flex:1;">
                <strong>{{ $pendingRefundsCount }} refund{{ $pendingRefundsCount === 1 ? '' : 's' }}
                    ({{ '₱' . number_format($pendingRefunds, 2) }}) awaiting payout.</strong>
                <div style="font-size: 14px;margin-top:2px;">
                    Open each one and use <em>Send Refund</em> — the system transfers the money itself.
                    <em>Mark Paid Out</em> is only for cash, or for money you already sent by hand.
                    @if ($refundsNeedingDetails > 0)
                        <br>
                        <strong>{{ $refundsNeedingDetails }}</strong>
                        of {{ $refundsNeedingDetails === 1 ? 'them is' : 'them are' }} still waiting on the guest to
                        say where to send it — open the refund and use <em>Add Details</em> if you have it already.
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.payments.index', ['status' => 'awaiting_payout']) }}" class="btn-navy"
                style="padding:8px 16px;font-size: 14px;white-space:nowrap;">
                Show these
            </a>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.payments.index') }}">
            <div class="payments-filter-grid">
                <div>
                    <label class="form-label-sm">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Booking ref or guest name" value="{{ request('search') }}">
                </div>
                <div>
                    <label class="form-label-sm">Method</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="cash" {{ request('method') == 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="qrph" {{ request('method') == 'qrph' ? 'selected' : '' }}>QR Ph</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="full_payment" {{ request('type') == 'full_payment' ? 'selected' : '' }}>Full Payment
                        </option>
                        <option value="partial" {{ request('type') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="balance" {{ request('type') == 'balance' ? 'selected' : '' }}>Balance</option>
                        <option value="refund" {{ request('type') == 'refund' ? 'selected' : '' }}>Refund</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">From</label>
                    <input type="date" name="from" class="form-control form-control-sm"
                        value="{{ request('from') }}">
                </div>
                <div>
                    <label class="form-label-sm">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div style="padding-top:18px;">
                    <button type="submit" class="btn-navy" style="padding:8px 16px; font-size: 14px;">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div style="padding-top:18px;">
                    <a href="{{ route('admin.payments.index') }}" class="text-muted-theme"
                        style="background:#fff; border:1.5px solid var(--border);
                              border-radius:7px; padding:8px 14px; font-size: 14px; text-decoration:none;
                              display:inline-block;">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Payment Records Table --}}
    <div class="table-card">
        <div class="table-card-header">
            <h3>Payment Records</h3>
            <span class="text-muted-theme" style="font-size: 14px;">{{ $payments->total() }} records</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <i class="bi bi-credit-card-2-back"></i>
                No payment records found.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Booking Ref</th>
                        <th>Guest</th>
                        <th>Property</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="text-muted-theme" style="font-size: 14px;">
                                {{ $payment->created_at->format('M d, Y') }}
                            </td>
                            <td>
                                <strong style="font-size:13px;">{{ $payment->booking->booking_ref ?? 'N/A' }}</strong>
                            </td>
                            <td>{{ $payment->booking->user->full_name ?? 'N/A' }}</td>
                            <td>{{ $payment->booking->property->property_name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge b-{{ $payment->payment_method }}">
                                    {{ $payment->method_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge b-{{ $payment->payment_type }}">
                                    {{ $payment->type_label }}
                                </span>
                                @if ($payment->isAwaitingPayout())
                                    <span class="badge b-awaiting"
                                        title="Refund approved — money not sent to the guest yet">
                                        NOT SENT
                                    </span>
                                    {{-- Dalawang magkaibang estado ang "hindi pa naipapadala":
                                         hinihintay ang guest, o handa nang ipadala. Magkaiba ang
                                         kailangang gawin, kaya hindi sila dapat magmukhang pareho. --}}
                                    @if ($payment->needsRefundDestination())
                                        <span class="badge b-nodest"
                                            title="Waiting for the guest to tell us where to send it">
                                            NEEDS DETAILS
                                        </span>
                                    @endif
                                    {{-- Naipadala na ang utos, hindi pa na-settle. Ito ang
                                         tanging estado kung saan walang dapat gawin ang admin
                                         kundi maghintay — kaya sinasadyang walang aksyon
                                         sa tabi nito. --}}
                                    @if ($payment->hasTransferInFlight())
                                        <span class="badge b-clearing"
                                            title="Sent to PayMongo — waiting for the receiving bank or e-wallet">
                                            CLEARING
                                        </span>
                                    @endif
                                    @if ($payment->isOverduePayout())
                                        <span class="badge b-overdue"
                                            title="The guest was told they would hear back — this has been sitting since it was approved">
                                            {{ $payment->daysAwaitingPayout() }}D WAITING
                                        </span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                            </td>
                            <td class="text-muted-theme"
                                style="font-size: 14px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ $payment->notes ?? '—' }}
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('admin.bookings.show', $payment->booking_id) }}"
                                    class="view-link me-2">View</a>
                                @if ($payment->payment_type !== 'refund')
                                    <a href="#" class="refund-link"
                                        onclick="openRefundModal({{ $payment->id }}, {{ $payment->amount }}, '{{ $payment->booking->booking_ref ?? '' }}')">
                                        Refund
                                    </a>
                                @elseif($payment->isAwaitingPayout())
                                    @if ($payment->hasTransferInFlight())
                                        {{-- Walang aksyon nang sinasadya: ang muling pagpapadala
                                             habang nasa daan pa ang isa ay magpapadala ng pera
                                             nang dalawang beses. --}}
                                        <a href="{{ route('admin.payments.show', $payment) }}"
                                            class="view-link">Check</a>
                                    @elseif ($payment->canSendTransfer())
                                        {{-- ANG PANGUNAHING DAAN.
                                             Dati ay `Mark Paid Out` ang nasa puwesto nito,
                                             kaya ang unang tunay na pagsubok ay napunta
                                             diretso sa manu-manong fallback at namarkahang
                                             naipadala ang isang refund na hindi naman
                                             naipadala. Ang pagpapadala ay nasa detail page
                                             at hindi rito nang sinasadya: kailangan nitong
                                             makita ang account bago pumindot. --}}
                                        <a href="{{ route('admin.payments.show', $payment) }}"
                                            class="refund-link">Send Refund</a>
                                    @elseif ($payment->needsRefundDestination())
                                        {{-- Walang "Mark Paid Out" dito nang sinasadya. Tatanggihan
                                             ito ng controller hangga't walang destinasyon — at ang
                                             pagpapakita ng button na garantisadong mag-e-error ay
                                             pagtuturo sa staff na balewalain ang mga error.

                                             Ang detalye ay ipinapasok sa detail page, kasama ang
                                             buong konteksto ng booking — financial account data
                                             ito, hindi dapat ipasok nang nagmamadali sa isang
                                             modal sa gitna ng listahan. --}}
                                        <a href="{{ route('admin.payments.show', $payment) }}"
                                            class="refund-link">Add Details</a>
                                    @else
                                        <a href="#" class="refund-link"
                                            onclick="event.preventDefault(); openPayoutModal(
                                                {{ $payment->id }},
                                                {{ $payment->amount }},
                                                @js($payment->refundDestination?->institution_name),
                                                @js($payment->refundDestination?->account_name),
                                                @js($payment->refundDestination?->account_number))">
                                            Mark Paid Out
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($payments->hasPages())
                <div style="padding:16px 22px;">
                    {{ $payments->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection

@section('modals')
    {{-- Record Payment Modal --}}
    <div class="modal-overlay" id="recordModal">
        <div class="modal-box">
            <div class="modal-head">
                <div class="modal-title">Record Payment</div>
                <button class="modal-close" onclick="closeModal('recordModal')">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.payments.store') }}" class="modal-body">
                @csrf
                <div class="mb-12">
                    <label class="form-label">Booking Reference</label>
                    <input type="text" name="booking_ref" class="form-control" placeholder="e.g. VE-XXXXXXXX"
                        autocomplete="off" oninput="lookupBooking(this.value)">
                    <input type="hidden" name="booking_id" id="bookingIdInput">
                    <div id="bookingInfo" class="text-muted-theme"
                        style="margin-top:8px; font-size: 14px; display:none; background:#f8fafc; border-radius:8px; padding:10px 12px;">
                    </div>
                </div>
                <div class="two-col mb-12">
                    <div>
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" name="amount" class="form-control" min="1" step="0.01"
                            required placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}"
                            required>
                    </div>
                </div>
                <div class="two-col mb-12">
                    <div>
                        <label class="form-label">Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="qrph">QR Ph (GCash / Maya / bank app)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <select name="payment_type" class="form-select" required>
                            <option value="full_payment">Full Payment</option>
                            <option value="partial">Partial</option>
                            <option value="balance">Balance</option>
                        </select>
                    </div>
                </div>
                <div class="mb-12">
                    <label class="form-label">Notes <span class="text-muted-theme"
                            style="font-weight:400;">(optional)</span></label>
                    <input type="text" name="notes" class="form-control"
                        placeholder="e.g. Cash received at frontdesk">
                </div>
                {{-- Kailangan lang ito kapag may kamukhang bayad na naitala
                     ngayong araw — hinaharangan ang pagtatala hangga't hindi
                     ito nakatik, para hindi maitala nang dalawang beses ang
                     iisang bayad. --}}
                <label class="form-label"
                    style="display:flex; align-items:flex-start; gap:8px; font-weight:400; margin-bottom:12px;">
                    <input type="checkbox" name="confirm_duplicate" value="1" style="margin-top:3px;">
                    <span>This is a <strong>separate</strong> payment — tick only if the guest really paid this
                        amount again today.</span>
                </label>
                <button type="submit" class="btn-submit"><i class="bi bi-check-circle me-2"></i> Record Payment</button>
            </form>
        </div>
    </div>

    {{-- Refund Modal --}}
    <div class="modal-overlay" id="refundModal">
        <div class="modal-box">
            <div class="modal-head" style="background:#dc2626;">
                <div class="modal-title">Issue Refund</div>
                <button class="modal-close" onclick="closeModal('refundModal')">✕</button>
            </div>
            <form method="POST" id="refundForm" class="modal-body">
                @csrf
                <div
                    style="background:#fee2e2; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#dc2626;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Refund for booking <strong id="refundBookingRef"></strong>
                </div>
                <div class="mb-12">
                    <label class="form-label">Refund Amount (₱)</label>
                    <input type="number" name="refund_amount" id="refundAmountInput" class="form-control"
                        min="1" step="0.01" required>
                    <div class="text-muted-theme" style="font-size: 13px; margin-top:4px;">Max: ₱<span
                            id="refundMax"></span></div>
                </div>
                <div class="mb-12">
                    <label class="form-label">Reason for Refund</label>
                    <textarea name="refund_reason" class="form-control" rows="2" required minlength="5"
                        placeholder="e.g. Guest cancelled 48 hours before check-in"></textarea>
                </div>
                <button type="submit"
                    style="background:#dc2626; color:#fff; border:none; border-radius:9px; padding:12px; width:100%; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
                    <i class="bi bi-arrow-counterclockwise me-2"></i> Process Refund
                </button>
            </form>
        </div>
    </div>

    {{-- Mark Paid Out Modal ────────────────────────────────────────
         Dating isang `confirm()` na dialog lang ito. Ang problema sa
         confirm() ay masyadong madaling pindutin ang OK nang hindi mo
         talaga ginawa ang bagay — at nangyari na iyon dito
         (project.md §6.11): napindot ito nang walang perang gumagalaw.

         Ang paghingi ng reference ng aktwal na transfer ang nag-aalis
         ng aksidenteng iyon: kung hindi mo ito ipinadala, wala kang
         maikokopya rito. --}}
    <div class="modal-overlay" id="payoutModal">
        <div class="modal-box">
            <div class="modal-head" style="background:var(--terracotta);">
                <div class="modal-title">Confirm Refund Sent</div>
                <button class="modal-close" onclick="closeModal('payoutModal')">✕</button>
            </div>
            <form method="POST" id="payoutForm" class="modal-body">
                @csrf
                @method('PATCH')

                <div
                    style="background:#fef3c7; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400e;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>This does not send any money.</strong>
                    It only records that you already sent
                    <strong>₱<span id="payoutAmount"></span></strong> to the guest yourself.
                    To have the system transfer it for you, close this and use
                    <strong>Send Refund</strong> instead.
                </div>

                <div id="payoutDest"
                    style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size: 14px;line-height:1.7;">
                </div>

                <div class="mb-12">
                    <label class="form-label">Transfer Reference Number</label>
                    <input type="text" name="transfer_reference" id="payoutRef" class="form-control"
                        minlength="4" maxlength="100" placeholder="e.g. 1029384756123" required>
                    <div class="text-muted-theme" style="font-size: 13px; margin-top:4px;">
                        Copy it from your GCash / Maya / bank receipt. This is the only proof the
                        money left, so it is stored with the refund.
                    </div>
                </div>

                <button type="submit"
                    style="background:var(--terracotta); color:#fff; border:none; border-radius:9px; padding:12px; width:100%; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
                    <i class="bi bi-check2-circle me-2"></i> Confirm Sent
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function openRecordModal() {
            document.getElementById('recordModal').style.display = 'flex';
        }

        function openRefundModal(paymentId, amount, bookingRef) {
            document.getElementById('refundBookingRef').textContent = bookingRef;
            document.getElementById('refundMax').textContent = parseFloat(amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('refundAmountInput').max = amount;
            document.getElementById('refundAmountInput').value = amount;
            // Gumagamit ng route() helper (naka-embed via Blade) sa halip na
            // hardcoded na "/villa-elena/public/..." path — para gumana ito
            // kahit paano ma-access ang app (php artisan serve, XAMPP subfolder,
            // custom domain, atbp.), dahil laging tama ang APP_URL-aware na URL
            // na ginagawa ng Laravel mismo.
            document.getElementById('refundForm').action =
                "{{ route('admin.payments.refund', ['payment' => '__PAYMENT_ID__']) }}".replace('__PAYMENT_ID__',
                    paymentId);
            document.getElementById('refundModal').style.display = 'flex';
        }

        // Ipinapakita ang destinasyon sa loob mismo ng modal para hindi
        // na kailangang lumipat ng pahina ang admin para makita kung
        // kanino niya dapat ipadala — ang paglipat ay kung saan
        // nawawala o napapalitan ang mga numero.
        function openPayoutModal(paymentId, amount, institution, accountName, accountNumber) {
            document.getElementById('payoutAmount').textContent =
                parseFloat(amount).toLocaleString('en-PH', {
                    minimumFractionDigits: 2
                });

            document.getElementById('payoutDest').innerHTML = institution ?
                '<strong>Send to</strong><br>' + institution + '<br>' + accountName + '<br>' +
                '<span style="font-family:monospace;font-size:13px;">' + accountNumber + '</span>' :
                '<strong>Cash refund</strong> — handed over at the front desk. A reference is optional here.';

            document.getElementById('payoutRef').required = !!institution;
            document.getElementById('payoutRef').value = '';

            document.getElementById('payoutForm').action =
                "{{ route('admin.payments.paidOut', ['payment' => '__PAYMENT_ID__']) }}".replace(
                    '__PAYMENT_ID__', paymentId);
            document.getElementById('payoutModal').style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        ['recordModal', 'refundModal', 'payoutModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeModal(id);
            });
        });

        let lookupTimer;
        const bookingLookupUrlTemplate = '{{ route('admin.bookings.lookup', ['ref' => '__REF__']) }}';

        function lookupBooking(ref) {
            clearTimeout(lookupTimer);
            if (ref.length < 6) {
                document.getElementById('bookingInfo').style.display = 'none';
                return;
            }
            lookupTimer = setTimeout(() => {
                fetch(bookingLookupUrlTemplate.replace('__REF__', encodeURIComponent(ref)), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.id) {
                            document.getElementById('bookingIdInput').value = data.id;
                            const info = document.getElementById('bookingInfo');
                            info.style.display = 'block';
                            info.innerHTML =
                                `<strong>${data.guest}</strong> · ${data.property} · Balance: <strong style="color:#dc2626;">₱${parseFloat(data.balance).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>`;
                        }
                    }).catch(() => {});
            }, 400);
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
        }, 5000);
    </script>
@endpush

