                @forelse($todayItems as $item)
                    @php
                        $booking = $item['booking'];
                        $isArrival = $item['action'] === 'checkin';
                        $isInHouse = $item['action'] === 'inhouse';
                        $outAt = $item['at'];
                    @endphp
                    <div class="booking-row">
                        <div class="guest-avatar {{ $isInHouse ? 'is-inhouse' : ($isArrival ? '' : 'is-departure') }}">
                            {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                        </div>
                        <div class="booking-info">
                            @if ($isInHouse)
                                <div class="row-tag inhouse">
                                    <i class="bi bi-house-door-fill"></i>
                                    In villa · out
                                    {{ $outAt->isTomorrow() ? 'tomorrow' : $outAt->format('M j') }}
                                    {{ $outAt->format('g:i A') }}
                                </div>
                            @else
                                <div class="row-tag {{ $isArrival ? 'arrival' : 'departure' }}">
                                    <i class="bi bi-box-arrow-{{ $isArrival ? 'in-right' : 'right' }}"></i>
                                    {{ $isArrival ? 'Arrival' : 'Departure' }} · {{ $item['at']->format('g:i A') }}
                                </div>
                            @endif
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            {{-- The reference opens the booking (staff.bookings.show). --}}
                            <div class="booking-ref">
                                <a href="{{ route('staff.bookings.show', $booking) }}">{{ $booking->booking_ref }}</a>
                            </div>
                            <div class="booking-meta">
                                {{-- The number to ring when the guest is late; a link,
                                     so a phone at the frontdesk can dial it. --}}
                                @if (filled($booking->user->phone))
                                    <span><i class="bi bi-telephone" aria-hidden="true"></i>
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->user->phone) }}">{{ $booking->user->phone }}</a></span>
                                @endif
                                @if ($isArrival)
                                    <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                                @else
                                    <span><i class="bi bi-calendar3"></i> Checked in
                                        {{ $booking->check_in_date->format('M d') }}</span>
                                @endif
                                <span><i class="bi bi-cash"></i>
                                    @if ($booking->balance_due > 0)
                                        <span class="text-red">₱{{ number_format($booking->balance_due, 0) }}
                                            {{ $isArrival ? 'balance' : 'unpaid' }}</span>
                                    @else
                                        <span class="text-green">Fully paid</span>
                                    @endif
                                </span>
                            </div>
                            {{-- What the guest asked for when booking. The walk-in
                                 form collects it, and until v7.76 no staff screen
                                 showed it. --}}
                            @if (filled($booking->special_requests))
                                <div class="booking-note">
                                    <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                    <span><span class="visually-hidden">Special requests: </span>{{ $booking->special_requests }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="booking-action is-stacked"
                            @if (!$isArrival && !$isInHouse) data-checkout-row="{{ $booking->id }}" @endif>
                            {{-- No button before the booking's own time: checkIn() and
                                 checkOut() refuse an early one, so it was a button
                                 that asked "Check in …?" and then always failed —
                                 all day, for a Night arrival. Same tests as the
                                 controller. This list is re-rendered every minute,
                                 so the button appears when the time comes. --}}
                            @if ($isArrival && $booking->checkInDateTime()->isFuture())
                                <span class="row-wait">
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    Check-in opens {{ $item['at']->format('g:i A') }}
                                </span>
                            @elseif (!$isArrival && !$isInHouse && $booking->checkOutDateTime()->isFuture())
                                <span class="row-wait">
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    Check-out opens {{ $item['at']->format('g:i A') }}
                                </span>
                            @elseif ($isArrival)
                                {{-- Kapag may natitirang balance ang booking na ito,
                                 hindi na basta magsu-submit ang form na ito —
                                 hahadlangan muna ito ng handleCheckInSubmit()
                                 para bumukas ang Check-in Confirmation modal,
                                 na siyang mag-a-attach ng "balance_arrangement"
                                 hidden input bago ipasa ang totoong submit. --}}
                                {{-- `data-checkin` marks the form for the submit listener
                                     in staff/frontdesk, which reads the guest and the
                                     balance from these attributes. Until v7.76 they were
                                     arguments in an inline onsubmit, the guest's name
                                     among them; the project keeps a name out of inline
                                     handlers (see the note on the Check Out form below). --}}
                                <form method="POST" action="{{ route('staff.checkin', $booking) }}"
                                    id="checkinForm_{{ $booking->id }}"
                                    data-checkin="{{ $booking->id }}"
                                    data-guest="{{ $booking->user->full_name }}"
                                    data-balance="{{ $booking->balance_due ?? 0 }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-checkin">
                                        <i class="bi bi-box-arrow-in-right"></i> Check In
                                    </button>
                                </form>
                            @elseif (!$isInHouse)
                                {{-- Check Out lang kapag ngayon ang takdang
                                     checkout — ganito rin ang dating Current
                                     Guests tab: walang early-checkout button
                                     para sa guest na bukas pa aalis. --}}
                                <form method="POST" action="{{ route('staff.checkout', $booking) }}"
                                    id="checkoutForm_{{ $booking->id }}" class="checkout-form-{{ $booking->id }}"
                                    data-confirm="Check out {{ $booking->user->full_name }}?"
                                    data-confirm-label="Check out" data-confirm-tone="neutral">
                                    @csrf @method('PATCH')
                                    {{-- The guest's name goes in a data attribute, never an inline
                                         handler: an onclick is parsed as JavaScript after the HTML
                                         parser has already decoded the escaping, so a name like
                                         '+alert(1)+' ran there. partials/confirm_dialog reads this
                                         attribute and sets it with textContent. --}}
                                    <button type="submit" class="btn-checkout">
                                        <i class="bi bi-box-arrow-right"></i> Check Out
                                    </button>
                                </form>
                            @else
                                <span class="badge b-checked_in">Checked In</span>
                            @endif
                            {{-- Walang Payment button kapag wala nang babayaran:
                                 tinatanggihan naman ito ng
                                 Payment::manualEntryProblem() ("more than the
                                 remaining balance"), kaya isa lang itong
                                 pindutang laging nabibigo. Babalik ito kapag
                                 may bagong balance (hal. dinagdagan ng extras). --}}
                            @if ($booking->balance_due > 0)
                                {{-- Opened by the click listener in staff/frontdesk, which
                                     reads these attributes. The guest's name is in a data
                                     attribute and not in an inline handler: see the note on
                                     the Check Out form above. data-pay-booking is also how
                                     a refused payment and "Pay Now" find this button. --}}
                                <button type="button" class="btn-sm btn-pay" data-pay-booking="{{ $booking->id }}"
                                    data-pay-label="{{ $booking->user->full_name }} · {{ $booking->booking_ref }}"
                                    data-pay-balance="{{ $booking->balance_due ?? 0 }}">
                                    <i class="bi bi-cash"></i> Payment
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-calendar-check"></i>
                        <p>Nothing today — nobody in the villa, no arrivals, no departures.</p>
                    </div>
                @endforelse
