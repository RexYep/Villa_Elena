                {{-- The Awaiting Payment rows. A partial because two paths render it:
                     the page, and GET /staff/frontdesk/today. Only the tab's count
                     used to refresh, so the tab could say 2 above "No pending
                     bookings", and "Hold expires in N min" never counted down. --}}
                @forelse($pendingBookings as $booking)
                    <div class="booking-row">
                        <div class="guest-avatar tag-amber">
                            {{ strtoupper(substr($booking->user->full_name, 0, 1)) }}
                        </div>
                        <div class="booking-info">
                            <div class="booking-name">{{ $booking->user->full_name }}</div>
                            <div class="booking-ref">
                                <a href="{{ route('staff.bookings.show', $booking) }}">{{ $booking->booking_ref }}</a>
                                @if (filled($booking->user->email))
                                    · {{ $booking->user->email }}
                                @endif
                            </div>
                            <div class="booking-meta">
                                {{-- The slot and its stored times, not a date range: a Day
                                     booking read "Oct 13 – Oct 13", which says nothing
                                     about which half of the day is being held. --}}
                                <span><i class="bi bi-calendar3"></i> {{ $booking->check_in_date->format('D, M j') }}
                                    · {{ $booking->slot_name ?? 'Stay' }}, {{ $booking->stay_hours }}</span>
                                <span><i class="bi bi-people"></i> {{ $booking->num_guests }} guests</span>
                                <span>₱{{ number_format($booking->total_amount, 0) }}</span>
                            </div>
                        </div>
                        <div class="booking-action text-end">
                            @if ($booking->amount_paid > 0)
                                <span class="badge b-pending tag-red">Paid · slot conflict</span>
                                <div class="stat-sub text-wrap">₱{{ number_format($booking->amount_paid, 0) }} paid — admin decides</div>
                            @else
                                @php $holdLeft = max(1, (int) ceil(now()->diffInMinutes($booking->created_at->copy()->addMinutes($holdMinutes)))); @endphp
                                <span class="badge b-pending">Pending</span>
                                <div class="stat-sub">Hold expires in {{ $holdLeft }} min</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-check-all"></i>
                        <p>No pending bookings.</p>
                    </div>
                @endforelse
