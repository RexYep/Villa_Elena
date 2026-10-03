{{-- Ang availability grid. Hiwalay na partial dahil DALAWA ang nag-render:
     ang page mismo, at ang GET /staff/availability/grid na kinukuha muli ng
     page kapag may nagbago (FrontDeskController::availabilityGrid). Iisang
     Blade, iisang hasConflict() — walang panuntunang kinopya sa JS. --}}
{{-- `--slot-cols` ay itinatakda dito, hindi sa CSS ng page: ang partial na
     ito ay ini-render din nang mag-isa ng GET /staff/availability/grid, kaya
     ang bilang ng column ay kailangang kasama sa markup na ipinapadala. --}}
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
            {{-- `name` mula sa Booking::SLOTS, hindi ucfirst($slotKey) — ang
                 huli ay nagbubunga ng "Stay22". Ito ang mobile label
                 (`.slot-cell::before`), kaya nakikita ito ng staff. --}}
            <div class="slot-cell" data-slot="{{ $slotDefs[$slotKey]['name'] ?? ucfirst($slotKey) }}">
                @if($slot['state'] === 'free')
                    <a class="slot-box free"
                       href="{{ route('staff.walkin', ['date' => $row['date']->format('Y-m-d'), 'slot' => $slotKey]) }}"
                       title="Book this slot for {{ $row['date']->format('M j, Y') }}">
                        <span class="slot-icon"><i class="bi bi-plus-lg"></i></span>
                        <span class="slot-text">
                            <span class="slot-state">Available</span>
                            <span class="slot-sub d-block">
                                @if ($slot['promo'])
                                    {{-- Ang tinatawid ay ang list price; ang presyong sinisingil
                                         ay ang nasa gilid nito. Ito ang sinasabi ni staff sa guest. --}}
                                    <s style="opacity:.55;">₱{{ number_format($slot['base'], 2) }}</s>
                                    <strong>₱{{ number_format($slot['price'], 2) }}</strong>
                                    · {{ $slot['promo'] }}
                                @else
                                    ₱{{ number_format($slot['price'], 2) }} · book now
                                @endif
                            </span>
                        </span>
                    </a>
                @else
                    <div class="slot-box {{ $slot['state'] }}">
                        <span class="slot-icon">
                            @if($slot['state'] === 'booked')
                                <i class="bi bi-person-fill"></i>
                            @elseif($slot['state'] === 'blocked')
                                <i class="bi bi-lock-fill"></i>
                            @elseif($slot['state'] === 'unoffered')
                                <i class="bi bi-slash-circle"></i>
                            @else
                                <i class="bi bi-dash-lg"></i>
                            @endif
                        </span>
                        <span class="slot-text">
                            <span class="slot-state">
                                @if($slot['state'] === 'booked') Booked
                                @elseif($slot['state'] === 'blocked') Blocked
                                {{-- Hindi inaalok ang slot sa petsang ito. Iba ito sa
                                     "Booked": walang kumuha — hindi talaga ito
                                     ipinagbibili sa araw na iyon. --}}
                                @elseif($slot['state'] === 'unoffered') Not offered
                                @else Passed @endif
                            </span>
                            @if($slot['state'] === 'unoffered')
                                <span class="slot-sub d-block">
                                    @if (\App\Models\Booking::slotRequiresWindow($slotKey))
                                        selected dates only
                                    @else
                                        22-hour date
                                    @endif
                                </span>
                            @endif
                            @if($slot['guest'])
                                <span class="slot-sub d-block">{{ $slot['guest'] }} · {{ $slot['label'] }}</span>
                            @elseif($slot['state'] === 'blocked')
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
