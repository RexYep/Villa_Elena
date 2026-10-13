{{-- Ang availability grid. Hiwalay na partial dahil DALAWA ang nag-render:
     ang page mismo, at ang GET /staff/availability/grid na kinukuha muli ng
     page kapag may nagbago (FrontDeskController::availabilityGrid). Iisang
     Blade, iisang hasConflict() — walang panuntunang kinopya sa JS. --}}
{{-- `--slot-cols` ay itinatakda dito, hindi sa CSS ng page: ang partial na
     ito ay ini-render din nang mag-isa ng GET /staff/availability/grid, kaya
     ang bilang ng column ay kailangang kasama sa markup na ipinapadala. --}}
{{-- The columns are the slots sold on an ordinary date (Day, Night). A
     22-hour date is the only thing sold that day, so its row is ONE cell
     across every column (`span`, from buildSlotGrid()), with the slot's name
     and times inside it since no column heading names it. --}}
<div class="grid-card" style="--slot-cols: {{ max(1, count($slotDefs)) }};">
    <div class="grid-head">
        <div>Date</div>
        @foreach($slotDefs as $slotKey => $def)
            <div>{{ $def['name'] }} — {{ $def['times'] }}</div>
        @endforeach
    </div>

    @foreach($grid as $row)
    <div class="grid-row {{ $row['is_today'] ? 'today' : '' }}">
        <div class="date-cell">
            <div class="date-day">{{ $row['date']->format('M j') }}</div>
            <div class="date-dow">{{ $row['date']->format('l') }}</div>
            @if($row['is_today'])
                <span class="today-tag">Today</span>
            @endif
        </div>

        @foreach($row['slots'] as $slotKey => $slot)
            @php
                // `name` mula sa Booking::SLOTS, hindi ucfirst($slotKey) — ang
                // huli ay nagbubunga ng "Stay22". Ito ang mobile label
                // (`.slot-cell::before`), kaya nakikita ito ng staff.
                $slotDef = \App\Models\Booking::SLOTS[$slotKey] ?? [];
                $slotName = $slotDef['name'] ?? ucfirst($slotKey);
                $wide = ! isset($slotDefs[$slotKey]);
            @endphp
            {{-- `--span`, not an inline grid-column: the stacked layout has one
                 column, and a literal "span 2" there would add a second. --}}
            <div class="slot-cell {{ $wide ? 'is-wide' : '' }}" data-slot="{{ $slotName }}"
                style="--span: {{ $slot['span'] ?? 1 }};">
                @if ($wide)
                    <span class="slot-kind">
                        <strong>{{ $slotName }}</strong>
                        <span>{{ $slotDef['times'] ?? '' }}</span>
                    </span>
                @endif

                @if($slot['state'] === 'free')
                    <a class="slot-box free"
                       href="{{ route('staff.walkin', ['date' => $row['date']->format('Y-m-d'), 'slot' => $slotKey]) }}"
                       title="Book this slot for {{ $row['date']->format('M j, Y') }}">
                        <span class="slot-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                        <span class="slot-text">
                            <span class="slot-state">Available</span>
                            <span class="slot-sub d-block">
                                @if ($slot['promo'])
                                    {{-- Ang tinatawid ay ang list price; ang presyong sinisingil
                                         ay ang nasa gilid nito. Ito ang sinasabi ni staff sa guest. --}}
                                    <s>₱{{ number_format($slot['base'], 2) }}</s>
                                    <strong>₱{{ number_format($slot['price'], 2) }}</strong>
                                    · {{ $slot['promo'] }}
                                @else
                                    ₱{{ number_format($slot['price'], 2) }} · book now
                                @endif
                            </span>
                        </span>
                    </a>
                @elseif($slot['state'] === 'booked' && $slot['booking_id'])
                    {{-- Opens the booking (v7.76). The cell used to show a name
                         and a reference and lead nowhere. --}}
                    <a class="slot-box booked" href="{{ route('staff.bookings.show', $slot['booking_id']) }}"
                       title="Open this booking">
                        <span class="slot-icon"><i class="bi bi-person-fill" aria-hidden="true"></i></span>
                        <span class="slot-text">
                            <span class="slot-state">Booked</span>
                            {{-- Two pieces that wrap between them, with no
                                 separator to be left hanging at a line end. --}}
                            <span class="slot-sub slot-who"><span>{{ $slot['guest'] }}</span><span>{{ $slot['label'] }}</span></span>
                        </span>
                    </a>
                @else
                    <div class="slot-box {{ $slot['state'] }}">
                        <span class="slot-icon">
                            @if($slot['state'] === 'booked')
                                <i class="bi bi-person-fill" aria-hidden="true"></i>
                            @elseif($slot['state'] === 'blocked')
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            @elseif($slot['state'] === 'unoffered')
                                <i class="bi bi-slash-circle" aria-hidden="true"></i>
                            @else
                                <i class="bi bi-dash-lg" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="slot-text">
                            <span class="slot-state">
                                @if($slot['state'] === 'booked') Booked
                                @elseif($slot['state'] === 'blocked') Blocked
                                {{-- A column's slot that this date does not sell. With
                                     the 22-hour slots out of the columns this should
                                     not come up; it is kept so that such a cell would
                                     say so and not read as bookable. --}}
                                @elseif($slot['state'] === 'unoffered') Not offered
                                @else Passed @endif
                            </span>
                            @if($slot['state'] === 'blocked')
                                <span class="slot-sub d-block">{{ $slot['label'] }}</span>
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endforeach
</div>
