{{--
    The guest's refund, on their booking details: how much, why, where it
    stands, and the one thing they may need to do.

    Renders nothing unless the booking has a refund.

    WHY IT EXISTS
    The only way to the "where should we send it" form used to be the link
    inside one notification, and the notifications page marks everything
    read the moment it is opened. A guest who lost that link had no way
    back, and the booking page showed "Paid ₱0.00" and a "-₱4,000 Refund"
    row from the moment the refund was issued — before any money moved.

    WHERE THE WORDS COME FROM
    The state is Payment::guestRefundState(), the short label is
    guest_refund_status_label (the Payment History row prints the same one)
    and the reason is guest_refund_reason, which is the sentence the
    notification used. Only the "what happens next" sentence lives here,
    because this is the only place that says it.

    The account is always shown as its last four digits.

    Expects: $booking with payments.refundTransfers and
    payments.refundDestination loaded.
--}}
@php
    $refunds = $booking->payments->where('payment_type', 'refund')->sortByDesc('id');
    $refundNeedsAction = $refunds->contains(
        fn ($r) => in_array($r->guestRefundState(), ['needs_details', 'details_rejected'], true)
    );
@endphp

@if ($refunds->isNotEmpty())
    <section class="card refund-card {{ $refundNeedsAction ? 'needs-action' : '' }}" id="refund"
        aria-labelledby="refundHeading">
        <div class="card-head">
            <h3 id="refundHeading">{{ $refunds->count() === 1 ? 'Your refund' : 'Your refunds' }}</h3>
        </div>
        <div class="card-body">
            @foreach ($refunds as $refund)
                @php
                    $state = $refund->guestRefundState();
                    $account = $refund->refundAccountPhrase();
                    $closedOn = $refund->refundClosedAt()?->format('M d, Y');
                    $editUrl = route('customer.refunds.destination', $refund);

                    $tone = 'is-' . $refund->guestStatusTone();

                    $next = match ($state) {
                        'needs_details' => "We can't send this until you tell us which bank or e-wallet account should receive it.",
                        'details_rejected' => 'We tried to send this to your '
                            . \App\Models\Payment::accountPhrase($refund->rejectedDetailsTransfer())
                            . ', ' . \App\Models\Payment::DETAILS_REJECTED_NOTE,
                        'not_sent' => "It will go to your {$account}. We'll notify you when it's sent.",
                        'on_its_way' => "Sent to your {$account}. We'll notify you when it arrives.",
                        'cash_due' => 'This will be paid to you in cash by the resort.',
                        'received' => "Arrived in your {$account} on {$closedOn}.",
                        'sent' => 'Sent' . ($account ? " to your {$account}" : '') . " on {$closedOn}.",
                        'paid_cash' => "Paid to you in cash on {$closedOn}.",
                        default => '',
                    };

                    $reference = match ($state) {
                        'received' => $refund->succeededTransfer()?->receipt_reference,
                        'sent' => $refund->transaction_ref,
                        default => null,
                    };

                    $action = match ($state) {
                        'needs_details' => ['Add account details', 'btn-refund-action'],
                        'details_rejected' => ['Check account details', 'btn-refund-action'],
                        'not_sent' => ['Change account', 'refund-item-link'],
                        default => null,
                    };
                @endphp

                <div class="refund-item">
                    <div class="refund-item-top">
                        <div class="refund-item-amount">₱{{ number_format($refund->amount, 2) }}</div>
                        <span class="refund-pill {{ $tone }}">{{ $refund->guest_refund_status_label }}</span>
                    </div>

                    @if ($refund->guest_refund_reason)
                        <p class="refund-item-reason">{{ $refund->guest_refund_reason }}</p>
                    @endif

                    <div class="refund-item-next">
                        <p>
                            {{ $next }}
                            @if ($reference)
                                <span class="refund-item-ref">Transfer reference: {{ $reference }}</span>
                            @endif
                        </p>
                        @if ($action)
                            <a href="{{ $editUrl }}" class="{{ $action[1] }}">{{ $action[0] }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @once
        @push('styles')
            <style>
                .refund-card {
                    margin-bottom: 24px;
                }

                /* The one case where the guest has to act: the card's edge
                   says so before any of it is read. */
                .refund-card.needs-action {
                    border-color: var(--terracotta);
                }

                .refund-item + .refund-item {
                    margin-top: 18px;
                    padding-top: 18px;
                    border-top: 1px solid var(--border);
                }

                .refund-item-top {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 8px 14px;
                }

                .refund-item-amount {
                    font-family: var(--font-display);
                    font-size: 24px;
                    font-weight: 700;
                    color: var(--stone);
                    line-height: 1.2;
                }

                /* Sentence case, unlike `.badge`: this is a status to read,
                   and "NEEDS YOUR ACCOUNT DETAILS" in capitals shouts. */
                .refund-pill {
                    border-radius: 20px;
                    padding: 4px 12px;
                    font-size: 13px;
                    font-weight: 600;
                }

                .refund-pill.is-waiting {
                    background: var(--tag-amber-bg);
                    color: var(--tag-amber-fg);
                }

                .refund-pill.is-moving {
                    background: var(--tag-blue-bg);
                    color: var(--tag-blue-fg);
                }

                .refund-pill.is-done {
                    background: var(--tag-green-bg);
                    color: var(--tag-green-fg);
                }

                .refund-pill.is-problem {
                    background: var(--tag-red-bg);
                    color: var(--tag-red-fg);
                }

                .refund-item-reason {
                    margin: 8px 0 0;
                    font-size: 14px;
                    line-height: 1.55;
                    color: var(--muted);
                    max-width: 68ch;
                }

                .refund-item-next {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px 20px;
                    margin-top: 12px;
                }

                .refund-item-next p {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.55;
                    color: var(--stone);
                    max-width: 68ch;
                    min-width: 0;
                }

                /* A reference is one long unbroken string; without this it
                   pushes the card wider than a phone. */
                .refund-item-ref {
                    display: block;
                    color: var(--muted);
                    font-size: 13px;
                    overflow-wrap: anywhere;
                }

                .btn-refund-action {
                    flex: none;
                    background: var(--btn-primary);
                    color: #fff;
                    border-radius: 10px;
                    padding: 12px 20px;
                    font-size: 14px;
                    font-weight: 600;
                    text-align: center;
                    text-decoration: none;
                    transition: background-color .2s;
                }

                .btn-refund-action:hover,
                .btn-refund-action:focus-visible {
                    background: var(--btn-primary-hover);
                    color: #fff;
                }

                .refund-item-link {
                    flex: none;
                    font-size: 14px;
                    font-weight: 600;
                    color: var(--terracotta);
                    /* 44px tall, so it can be hit with a thumb. */
                    padding: 12px 0;
                }

                @media (max-width: 600px) {
                    .refund-item-amount {
                        font-size: 22px;
                    }

                    .refund-item-next {
                        flex-direction: column;
                        align-items: stretch;
                    }

                    .btn-refund-action {
                        width: 100%;
                    }

                    .refund-item-link {
                        align-self: flex-start;
                    }
                }
            </style>
        @endpush
    @endonce
@endif
