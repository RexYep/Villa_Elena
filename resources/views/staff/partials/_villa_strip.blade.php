    {{-- Villa status + next two slots. A partial because two paths render
         it: the page, and GET /staff/frontdesk/today, which re-renders it
         when a booking changes (FrontDeskController::villaStripData). It
         used to be drawn once, so after the scheduler's automatic check-in
         it went on saying "Vacant right now" beside a list that had already
         moved the guest in. --}}
    @if ($villa)
        <div class="villa-strip">
            <div class="villa-badge {{ $villa->status }}">
                <i class="bi bi-house-door-fill"></i>
            </div>
            <div class="villa-main">
                <div class="villa-name">{{ $villa->property_name }}</div>
                <div class="villa-state {{ $villa->status }}">
                    @if ($villa->status === 'occupied' && $villa->currentBooking)
                        <i class="bi bi-person-fill"></i>
                        {{ $villa->currentBooking->user->full_name ?? 'Guest' }} —
                        checking out {{ $villa->currentBooking->checkOutDateTime()->format('M j, g:i A') }}
                    @elseif($villa->status === 'occupied')
                        <i class="bi bi-person-fill"></i> Currently occupied
                    @elseif($villa->status === 'maintenance')
                        <i class="bi bi-tools"></i> Under maintenance — not bookable
                    @else
                        <i class="bi bi-check-circle-fill"></i> Vacant right now
                    @endif
                </div>
            </div>
            <div class="villa-next">
                @foreach ($nextSlots as $row)
                    @foreach ($row['slots'] as $slotKey => $slot)
                        {{-- `unoffered` is skipped like `past`: nobody took that slot, it
                             simply isn't sold on that date, so "Booked" would be wrong. --}}
                        @if (! in_array($slot['state'], ['past', 'unoffered'], true))
                            @if ($slot['state'] === 'free')
                                <a class="next-slot free"
                                    href="{{ route('staff.walkin', ['date' => $row['date']->format('Y-m-d'), 'slot' => $slotKey]) }}">
                                    <div class="next-slot-lbl">{{ $row['is_today'] ? 'Today' : $row['date']->format('D') }}
                                        · {{ \App\Models\Booking::SLOTS[$slotKey]['name'] ?? ucfirst($slotKey) }}</div>
                                    <div class="next-slot-val">Available</div>
                                </a>
                            @else
                                <div class="next-slot taken">
                                    <div class="next-slot-lbl">{{ $row['is_today'] ? 'Today' : $row['date']->format('D') }}
                                        · {{ \App\Models\Booking::SLOTS[$slotKey]['name'] ?? ucfirst($slotKey) }}</div>
                                    <div class="next-slot-val">{{ $slot['state'] === 'blocked' ? 'Blocked' : 'Booked' }}
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endforeach
                <a href="{{ route('staff.availability') }}" class="next-slot see-all">
                    <div class="next-slot-lbl">See all</div>
                    <div class="next-slot-val">14 days →</div>
                </a>
            </div>
        </div>
    @endif
