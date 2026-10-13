@extends('layouts.staff')

@section('title', $booking->booking_ref . ' — Villa Elena Staff')
@section('page-title', 'Booking ' . $booking->booking_ref)
@section('page-subtitle', $booking->user->full_name ?? 'Guest')

@push('styles')
    <style>
        .main {
            max-width: 1100px;
        }

        /* In the page, not the top bar: beside "Booking VE-…" there is no
           room for it on a phone. */
        .btn-all-bookings {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding: 9px 16px;
            border: 1.5px solid var(--border-strong);
            border-radius: 8px;
            background: #fff;
            color: var(--stone);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-all-bookings:hover {
            border-color: var(--stone);
        }

        /* Details on the left; money and the payment form on the right,
           where they stay in view while the left column scrolls. */
        .booking-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0 20px;
            align-items: start;
        }

        @media (min-width: 1100px) {
            .booking-layout {
                grid-template-columns: minmax(0, 1.5fr) minmax(300px, 1fr);
            }

            .booking-side {
                position: sticky;
                top: calc(var(--topbar-h) + 28px);
            }
        }

        /* Label and value pairs. */
        .facts {
            margin: 0;
        }

        .fact {
            display: grid;
            grid-template-columns: 140px minmax(0, 1fr);
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--sand);
            font-size: 14px;
        }

        .fact:first-child {
            padding-top: 0;
        }

        .fact:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .fact dt {
            font-weight: 400;
            color: var(--muted);
        }

        .fact dd {
            margin: 0;
            font-weight: 600;
            color: var(--text-main);
            overflow-wrap: anywhere;
        }

        .fact dd a {
            color: var(--terracotta);
            text-decoration: none;
        }

        .fact dd a:hover {
            text-decoration: underline;
        }

        .fact-none {
            font-weight: 400;
            color: var(--muted);
        }

        .fact-note {
            display: block;
            margin-top: 2px;
            font-size: 13px;
            font-weight: 400;
            color: var(--muted);
        }

        @media (max-width: 480px) {
            .fact {
                grid-template-columns: minmax(0, 1fr);
                gap: 2px;
            }
        }

        /* Money */
        .money-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 7px 0;
            font-size: 14px;
        }

        .money-row span:first-child {
            color: var(--muted);
        }

        .money-due {
            margin-top: 6px;
            padding-top: 12px;
            border-top: 2px solid var(--border);
            font-weight: 700;
            font-size: 15px;
        }

        .money-due span:first-child {
            color: var(--text-main);
        }

        .money-due .is-due {
            font-family: var(--font-display);
            font-size: 20px;
            color: var(--tag-red-fg);
        }

        .money-due .is-clear {
            color: var(--tag-green-fg);
        }

        .pay-form {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        .pay-form h3 {
            margin: 0 0 14px;
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
        }

        .pay-field {
            margin-bottom: 14px;
        }

        .pay-error {
            margin-bottom: 14px;
            padding: 10px 14px;
            border-radius: 9px;
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
            font-size: 13px;
            line-height: 1.5;
        }

        .pay-separate {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 14px;
            font-size: 13px;
            color: var(--muted);
        }

        .pay-separate input {
            margin-top: 3px;
        }

        .btn-record {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px;
            border: none;
            border-radius: 9px;
            background: var(--btn-primary);
            color: #fff;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-record:hover {
            background: var(--btn-primary-hover);
        }

        .pay-closed {
            margin: 14px 0 0;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Payment history */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .pay-table th {
            padding: 0 12px 10px 0;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .pay-table td {
            padding: 10px 12px 10px 0;
            border-bottom: 1px solid var(--sand);
            vertical-align: top;
        }

        .pay-table tr:last-child td {
            border-bottom: none;
        }

        .pay-table th:last-child,
        .pay-table td:last-child {
            padding-right: 0;
            text-align: right;
            white-space: nowrap;
        }

        .pay-table .pay-sub {
            display: block;
            font-size: 13px;
            color: var(--muted);
        }
    </style>
@endpush

@section('content')

    {{-- A recorded payment comes back here as `success`. A refused one is
         printed inside the form below, from its own error bag, so the first
         validation error is not repeated up here. --}}
    <a href="{{ route('staff.bookings.index') }}" class="btn-all-bookings">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> All bookings
    </a>

    @include('staff.partials.flash', ['validation' => false])

    @php
        $closed = in_array($booking->status, ['cancelled', 'no_show'], true);
        $checkIn = $booking->checkInDateTime();
        $checkOut = $booking->checkOutDateTime();
        $holdEnds = $booking->holdExpiresAt();
        $guest = $booking->user;
        $sourceLabel = [
            'walk_in' => 'Walk-in, at the frontdesk',
            'online' => 'Online, by the guest',
            'phone' => 'By phone',
            'partner' => 'Through a partner',
        ][$booking->source] ?? ucfirst(str_replace('_', ' ', (string) $booking->source));
        // Today's list is where a guest is checked in or out.
        $onTodayList = ($booking->status === 'confirmed' && $booking->check_in_date->isToday())
            || $booking->status === 'checked_in';
    @endphp

    <div class="booking-layout">

        <div class="booking-main">

            <section class="panel" aria-labelledby="stayHeading">
                <div class="panel-head">
                    <h2 id="stayHeading">Stay</h2>
                    <span class="status-pill is-{{ $booking->status }}">{{ $booking->statusDisplay()['text'] }}</span>
                </div>
                <div class="panel-body">
                    {{-- By slot and by the stored times, never by nights: a Day
                         booking is "1 night" in `num_nights`, and a stay the admin
                         extended keeps its real check-out here. --}}
                    <dl class="facts">
                        <div class="fact">
                            <dt>Date</dt>
                            <dd>{{ $booking->check_in_date->format('l, F j, Y') }}</dd>
                        </div>
                        <div class="fact">
                            <dt>Slot</dt>
                            <dd>{{ $booking->slot_name ?? 'Custom times' }}</dd>
                        </div>
                        <div class="fact">
                            <dt>Check-in</dt>
                            <dd>{{ $checkIn->format('g:i A') }}</dd>
                        </div>
                        <div class="fact">
                            <dt>Check-out</dt>
                            <dd>
                                {{ $checkOut->format('g:i A') }}
                                @if (! $checkOut->isSameDay($checkIn))
                                    <span class="fact-note">{{ $checkOut->format('l, F j') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="fact">
                            <dt>Guests</dt>
                            <dd>{{ $booking->num_guests }}</dd>
                        </div>
                        <div class="fact">
                            <dt>Special requests</dt>
                            <dd>
                                @if (filled($booking->special_requests))
                                    {{ $booking->special_requests }}
                                @else
                                    <span class="fact-none">None</span>
                                @endif
                            </dd>
                        </div>
                        <div class="fact">
                            <dt>Booked</dt>
                            <dd>
                                {{ $sourceLabel }}
                                <span class="fact-note">{{ $booking->created_at?->format('M j, Y, g:i A') }}</span>
                            </dd>
                        </div>
                        @if ($holdEnds)
                            <div class="fact">
                                <dt>Unpaid hold</dt>
                                <dd>
                                    @if ($holdEnds->isFuture())
                                        Held until {{ $holdEnds->format('g:i A') }}
                                        <span class="fact-note">Cancelled automatically if nothing is paid by then.</span>
                                    @else
                                        The hold has run out
                                        <span class="fact-note">It will be cancelled automatically unless a payment is recorded.</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($onTodayList)
                        <p class="pay-closed">
                            Check-in and check-out are done from the
                            <a href="{{ route('staff.frontdesk') }}" class="link-accent">Frontdesk</a>.
                        </p>
                    @endif
                </div>
            </section>

            <section class="panel" aria-labelledby="guestHeading">
                <div class="panel-head">
                    <h2 id="guestHeading">Guest</h2>
                </div>
                <div class="panel-body">
                    <dl class="facts">
                        <div class="fact">
                            <dt>Name</dt>
                            <dd>{{ $guest->full_name ?? 'Guest' }}</dd>
                        </div>
                        <div class="fact">
                            <dt>Phone</dt>
                            <dd>
                                @if (filled($guest?->phone))
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $guest->phone) }}">{{ $guest->phone }}</a>
                                @else
                                    <span class="fact-none">None on record</span>
                                @endif
                            </dd>
                        </div>
                        <div class="fact">
                            <dt>Email</dt>
                            <dd>
                                @if (filled($guest?->email))
                                    <a href="mailto:{{ $guest->email }}">{{ $guest->email }}</a>
                                @else
                                    <span class="fact-none">None on record</span>
                                @endif
                            </dd>
                        </div>
                        @if ($guest)
                            <div class="fact">
                                <dt>Stays completed</dt>
                                <dd>{{ $guest->completedStayCount() }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="panel" aria-labelledby="paymentsHeading">
                <div class="panel-head">
                    <h2 id="paymentsHeading">Payments</h2>
                    <span class="note-text">{{ $payments->count() }} recorded</span>
                </div>
                <div class="panel-body">
                    @if ($payments->isEmpty())
                        <p class="pay-closed m-0-all">No payment has been recorded for this booking.</p>
                    @else
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M j, Y') }}</td>
                                        <td>
                                            {{ $payment->type_label }}
                                            <span class="pay-sub">{{ $payment->guest_method_label }} · {{ $payment->guest_status_label }}</span>
                                        </td>
                                        {{-- A refund is money out: shown with a minus. --}}
                                        <td>{{ \App\Models\Payment::formatPesos(($payment->isRefund() ? -1 : 1) * (float) $payment->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

        </div>

        <aside class="booking-side" aria-labelledby="moneyHeading">
            <section class="panel">
                <div class="panel-head">
                    <h2 id="moneyHeading">Balance</h2>
                </div>
                <div class="panel-body">
                    @if ((float) $booking->discount_amount > 0)
                        <div class="money-row">
                            <span>List price</span>
                            <span>₱{{ number_format($booking->base_amount + $booking->extras_amount, 2) }}</span>
                        </div>
                        <div class="money-row">
                            <span>Discount</span>
                            <span>−₱{{ number_format($booking->discount_amount, 2) }}</span>
                        </div>
                    @endif
                    <div class="money-row">
                        <span>Total</span>
                        <span>₱{{ number_format($booking->total_amount, 2) }}</span>
                    </div>
                    <div class="money-row">
                        <span>Paid</span>
                        <span>₱{{ number_format($booking->amount_paid, 2) }}</span>
                    </div>
                    {{-- `balance_due` as stored, never total minus paid: a goodwill
                         refund lowers what was paid without being owed back. --}}
                    <div class="money-row money-due">
                        <span>Balance due</span>
                        @if ($closed)
                            <span>Nothing due</span>
                        @elseif ((float) $booking->balance_due > 0)
                            <span class="is-due">₱{{ number_format($booking->balance_due, 2) }}</span>
                        @else
                            <span class="is-clear">Fully paid</span>
                        @endif
                    </div>

                    @if ($canRecordPayment)
                        {{-- The same endpoint as the frontdesk's Record Payment form,
                             so the same checks: no more than the balance, and a
                             look-alike payment on the same day has to be confirmed.
                             The payment type is worked out by the server. --}}
                        <form method="POST" action="{{ route('staff.payment', $booking) }}" class="pay-form"
                            data-submit-once aria-labelledby="payFormHeading">
                            @csrf
                            <h3 id="payFormHeading">Record a payment</h3>

                            @if ($errors->payment->any())
                                <div class="pay-error" role="alert">{{ $errors->payment->first() }}</div>
                            @endif

                            <div class="pay-field">
                                <label for="payAmount" class="field-label">Amount</label>
                                <input type="number" name="amount" id="payAmount" class="form-control" required
                                    min="1" step="0.01" inputmode="decimal"
                                    value="{{ old('amount', number_format((float) $booking->balance_due, 2, '.', '')) }}">
                            </div>
                            <div class="pay-field">
                                <label for="payMethod" class="field-label">Method</label>
                                <select name="payment_method" id="payMethod" class="form-select" required>
                                    <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                                    <option value="qrph" @selected(old('payment_method') === 'qrph')>QR Ph (GCash / Maya / bank app)</option>
                                </select>
                            </div>
                            <div class="pay-field">
                                <label for="payNotes" class="field-label">Notes
                                    <span class="fw-400 text-muted-theme">(optional)</span></label>
                                <input type="text" name="notes" id="payNotes" class="form-control" maxlength="300"
                                    value="{{ old('notes') }}" placeholder="e.g. Cash received at frontdesk">
                            </div>

                            {{-- Only after the server has refused a look-alike payment. --}}
                            @if ($askSeparate)
                                <label class="pay-separate">
                                    <input type="checkbox" name="confirm_duplicate" value="1">
                                    <span>This is a <strong>separate</strong> payment — tick only if the guest really
                                        paid this amount again today.</span>
                                </label>
                            @endif

                            <button type="submit" class="btn-record" data-busy-label="Recording…">
                                <i class="bi bi-check-circle" aria-hidden="true"></i> Record Payment
                            </button>
                        </form>
                    @elseif ($closed)
                        <p class="pay-closed">This booking is {{ strtolower($booking->statusDisplay()['text']) }}, so no
                            payment can be recorded on it. A refund is handled by the admin.</p>
                    @endif
                </div>
            </section>
        </aside>

    </div>

@endsection
