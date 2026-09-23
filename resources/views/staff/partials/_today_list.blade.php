                @forelse($todayItems as $item)
                    @php
                        $booking = $item['booking'];
                        $isArrival = $item['action'] === 'checkin';
                        $isInHouse = $item['action'] === 'inhouse';
                        $outAt = $item['at'];
                    @endphp
                    <div class="booking-row">
                        <div class="guest-avatar"
                            @if ($isInHouse) style="background:#ede9fe;color:#7c3aed;"
                            @elseif (!$isArrival) style="background:#dbeafe;color:#1d4ed8;" @endif>
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
                            <div class="booking-ref">{{ $booking->booking_ref }}</div>
                            <div class="booking-prop"><i
                                    class="bi bi-house me-1"></i>{{ $booking->property->property_name }}</div>
                            <div class="booking-meta">
                                @if ($isArrival)
                                    <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                                @else
                                    <span><i class="bi bi-calendar3"></i> Checked in
                                        {{ $booking->check_in_date->format('M d') }}</span>
                                @endif
                                <span><i class="bi bi-cash"></i>
                                    @if ($booking->balance_due > 0)
                                        <span style="color:#dc2626;">₱{{ number_format($booking->balance_due, 0) }}
                                            {{ $isArrival ? 'balance' : 'unpaid' }}</span>
                                    @else
                                        <span style="color:#16a34a;">Fully paid</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="booking-action"
                            style="display:flex; gap:8px; flex-direction:column; align-items:flex-end;"
                            @if (!$isArrival && !$isInHouse) data-checkout-row="{{ $booking->id }}" @endif>
                            @if ($isArrival)
                                {{-- Kapag may natitirang balance ang booking na ito,
                                 hindi na basta magsu-submit ang form na ito —
                                 hahadlangan muna ito ng handleCheckInSubmit()
                                 para bumukas ang Check-in Confirmation modal,
                                 na siyang mag-a-attach ng "balance_arrangement"
                                 hidden input bago ipasa ang totoong submit. --}}
                                <form method="POST" action="{{ route('staff.checkin', $booking) }}"
                                    id="checkinForm_{{ $booking->id }}"
                                    onsubmit="return handleCheckInSubmit(event, {{ $booking->id }}, '{{ addslashes($booking->user->full_name) }}', {{ $booking->balance_due ?? 0 }})">
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
                                    id="checkoutForm_{{ $booking->id }}" class="checkout-form-{{ $booking->id }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-checkout"
                                        onclick="return confirm('Check out {{ $booking->user->full_name }}?')">
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
                                <button type="button" class="btn-sm"
                                    style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:7px;padding:6px 11px;font-size: 13px;font-weight:600;cursor:pointer;"
                                    onclick="openPaymentModal({{ $booking->id }}, '{{ $booking->booking_ref }}', {{ $booking->balance_due ?? 0 }})">
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
