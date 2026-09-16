{{-- Ang availability grid. Hiwalay na partial dahil DALAWA ang nag-render:
     ang page mismo, at ang GET /staff/availability/grid na kinukuha muli ng
     page kapag may nagbago (FrontDeskController::availabilityGrid). Iisang
     Blade, iisang hasConflict() — walang panuntunang kinopya sa JS. --}}
<div class="grid-card">
    <div class="grid-head">
        <div>Date</div>
        <div>Day — 8:00 AM to 5:00 PM</div>
        <div>Night — 7:00 PM to 6:00 AM</div>
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
            <div class="slot-cell" data-slot="{{ ucfirst($slotKey) }}">
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
                            @else
                                <i class="bi bi-dash-lg"></i>
                            @endif
                        </span>
                        <span class="slot-text">
                            <span class="slot-state">
                                @if($slot['state'] === 'booked') Booked
                                @elseif($slot['state'] === 'blocked') Blocked
                                @else Passed @endif
                            </span>
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
