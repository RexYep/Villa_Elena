{{--
    Payment deadline for an unpaid booking — "Pay by 3:42 PM", counting down.

    Included by the checkout page and the guest's booking details. Renders
    nothing unless Booking::holdExpiresAt() says a clock is running.

    WHY IT EXISTS
    The guest pays on PayMongo's page, which we cannot change, and that page
    shows its own 30-minute timer. Our hold is shorter and starts when the
    booking was made, not when the QR appeared. So the only place the real
    deadline can be said is here, BEFORE they leave — and the note says
    outright that the other timer is not the one that counts.

    WHAT HAPPENS AT ZERO
    The wording changes, a `villa:hold-ended` event fires on `document` (the
    checkout page uses it to switch off its Pay button), and the page asks
    payment.status every 10 seconds for up to 3 minutes. The sweeper runs
    once a minute, so the booking is not cancelled the instant the clock
    stops. When the status leaves `pending`, the guest is taken to the
    booking details, which then show the cancelled banner — or the confirmed
    booking, if a payment landed in the last seconds.

    The countdown runs on the SERVER's clock: the page is given the server's
    "now" and measures its own offset from it, so a phone set five minutes
    fast does not show five minutes too few.
--}}
@php
    $holdEndsAt = $booking->holdExpiresAt();
@endphp

@if ($holdEndsAt)
    @php
        $holdOver = $holdEndsAt->isPast();
        $holdBy = $holdEndsAt->isToday() ? $holdEndsAt->format('g:i A') : $holdEndsAt->format('M d, g:i A');

        $holdOverTitle = 'The time to pay has ended';
        $holdOverNote = 'No payment arrived in time, so this booking is being cancelled. '
            . 'You can book again if the date is still open.';
    @endphp

    <div class="hold-deadline {{ $holdOver ? 'is-over' : '' }}" id="holdDeadline"
        data-ends="{{ $holdEndsAt->getTimestampMs() }}"
        data-now="{{ now()->getTimestampMs() }}"
        data-status-url="{{ route('payment.status', $booking) }}"
        data-booking-url="{{ route('customer.bookings.show', $booking) }}"
        data-over-title="{{ $holdOverTitle }}"
        data-over-note="{{ $holdOverNote }}">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <div class="hold-deadline-body">
            <div class="hold-deadline-head">
                <strong data-hold-title>{{ $holdOver ? $holdOverTitle : 'Pay by ' . $holdBy }}</strong>
                {{-- Hidden from screen readers: it changes every second, and
                     the deadline beside it already says the same thing. --}}
                <span class="hold-deadline-left" data-hold-left aria-hidden="true"></span>
            </div>
            <div class="hold-deadline-note" data-hold-note>
                @if ($holdOver)
                    {{ $holdOverNote }}
                @else
                    If no payment arrives by then, this booking is cancelled automatically.
                    The QR page may show a longer timer. This deadline is the one that counts.
                @endif
            </div>
        </div>
    </div>

    @once
        @push('styles')
            <style>
                .hold-deadline {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    background: var(--tag-amber-bg);
                    color: var(--stone);
                    border-radius: 10px;
                    padding: 12px 14px;
                    margin-bottom: 18px;
                    font-size: 13px;
                    line-height: 1.5;
                    text-align: left;
                }

                .hold-deadline > i {
                    font-size: 16px;
                    color: var(--tag-amber-fg);
                    flex-shrink: 0;
                    margin-top: 1px;
                }

                .hold-deadline-body {
                    flex: 1;
                    min-width: 0;
                }

                .hold-deadline-head {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: baseline;
                    justify-content: space-between;
                    column-gap: 12px;
                    font-size: 14px;
                }

                /* Same width for every digit, so the line does not shiver
                   as the seconds change. */
                .hold-deadline-left {
                    font-weight: 700;
                    font-variant-numeric: tabular-nums;
                    white-space: nowrap;
                }

                .hold-deadline-note {
                    margin-top: 2px;
                    font-size: 12.5px;
                }

                .hold-deadline.is-urgent,
                .hold-deadline.is-over {
                    background: var(--tag-red-bg);
                    color: var(--tag-red-fg);
                }

                .hold-deadline.is-urgent > i,
                .hold-deadline.is-over > i {
                    color: var(--tag-red-fg);
                }
            </style>
        @endpush

        @push('scripts')
            <script>
                (function () {
                    const box = document.getElementById('holdDeadline');
                    if (!box) return;

                    const ENDS = Number(box.dataset.ends);
                    // Server clock minus this device's clock, measured once.
                    const SKEW = Number(box.dataset.now) - Date.now();
                    const URGENT_MS = 2 * 60 * 1000;
                    const WATCH_EVERY = 10000;
                    const WATCH_TRIES = 18;

                    const title = box.querySelector('[data-hold-title]');
                    const left = box.querySelector('[data-hold-left]');
                    const note = box.querySelector('[data-hold-note]');

                    let ticker = null;

                    function remaining() {
                        return ENDS - (Date.now() + SKEW);
                    }

                    function label(ms) {
                        const total = Math.ceil(ms / 1000);
                        const h = Math.floor(total / 3600);
                        const m = Math.floor((total % 3600) / 60);
                        const s = total % 60;

                        return h > 0
                            ? h + ' h ' + String(m).padStart(2, '0') + ' min left'
                            : m + ':' + String(s).padStart(2, '0') + ' left';
                    }

                    // The booking is cancelled by the sweeper, up to a minute
                    // after the clock stops. Wait for that, then show it.
                    function watch() {
                        let tries = 0;

                        const poll = setInterval(function () {
                            if (document.hidden) return;
                            if (++tries > WATCH_TRIES) return clearInterval(poll);

                            fetch(box.dataset.statusUrl, {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            })
                                .then(function (res) { return res.ok ? res.json() : null; })
                                .then(function (data) {
                                    if (!data || data.booking_status === 'pending') return;

                                    clearInterval(poll);
                                    window.location.href = box.dataset.bookingUrl;
                                })
                                .catch(function () { /* next try */ });
                        }, WATCH_EVERY);
                    }

                    function end() {
                        if (ticker !== null) clearInterval(ticker);

                        box.classList.remove('is-urgent');
                        box.classList.add('is-over');
                        title.textContent = box.dataset.overTitle;
                        note.textContent = box.dataset.overNote;
                        left.textContent = '';

                        document.dispatchEvent(new CustomEvent('villa:hold-ended'));
                        watch();
                    }

                    function tick() {
                        const ms = remaining();
                        if (ms <= 0) return end();

                        left.textContent = label(ms);
                        box.classList.toggle('is-urgent', ms <= URGENT_MS);
                    }

                    if (box.classList.contains('is-over')) {
                        document.dispatchEvent(new CustomEvent('villa:hold-ended'));
                        watch();
                    } else {
                        tick();
                        ticker = setInterval(tick, 1000);
                    }
                })();
            </script>
        @endpush
    @endonce
@endif
