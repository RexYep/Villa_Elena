        {{-- Isang villa, dalawang slot kada araw: halos laging 0–2 ang mga
             bilang, kaya ang ipinapakita ay ang mismong guest at ang
             kailangang gawin, hindi ang bilang. --}}
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="stat-lbl stat-lbl-top">Next Arrival</div>
            @if ($nextArrival)
                @php
                    $arrivalIn = $nextArrival->checkInDateTime();
                    $arrivalDay = $arrivalIn->isToday() ? 'Today' : ($arrivalIn->isTomorrow() ? 'Tomorrow' : $arrivalIn->format('D, M j'));
                    $arrivalSlot = $nextArrival->slotKey();
                @endphp
                <div class="stat-name" title="{{ $nextArrival->user->full_name ?? 'Guest' }}">{{ $nextArrival->user->full_name ?? 'Guest' }}</div>
                <div class="stat-sub">
                    {{ $arrivalDay }}{{ $arrivalSlot ? ' · '.ucfirst($arrivalSlot) : '' }} · {{ $arrivalIn->format('g:i A') }}
                </div>
                <div class="stat-sub">
                    @if ($nextArrival->balance_due > 0)
                        <span class="stat-warn">₱{{ number_format($nextArrival->balance_due, 0) }} balance</span>
                    @else
                        <span class="stat-ok">Fully paid</span>
                    @endif
                </div>
            @else
                <div class="stat-name stat-empty">No upcoming bookings</div>
            @endif
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-blue"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-lbl stat-lbl-top">To Collect</div>
            @if ($toCollect->isNotEmpty())
                <div class="stat-val">₱{{ number_format($stats['to_collect'], 0) }}</div>
                @foreach ($toCollect as $b)
                    <div class="stat-sub" title="{{ $b->user->full_name ?? 'Guest' }}">
                        {{ $b->user->full_name ?? 'Guest' }}
                        ({{ $b->status === 'checked_in' ? 'in villa' : 'arriving' }})
                        · ₱{{ number_format($b->balance_due, 0) }}
                    </div>
                @endforeach
            @else
                <div class="stat-name stat-ok"><i class="bi bi-check-circle-fill"></i> All paid</div>
                <div class="stat-sub">In-house and today's arrivals</div>
            @endif
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-lbl stat-lbl-top">Awaiting Payment</div>
            <div class="stat-val">{{ $activeHolds->count() }}</div>
            <div class="stat-sub">
                @if ($activeHolds->isNotEmpty())
                    @php $nextExpiry = $activeHolds->map(fn ($b) => $b->created_at->copy()->addMinutes($holdMinutes))->min(); @endphp
                    hold{{ $activeHolds->count() != 1 ? 's' : '' }} · next expires in {{ max(1, (int) ceil(now()->diffInMinutes($nextExpiry))) }} min
                @else
                    No active holds
                @endif
            </div>
            @if ($paidPending->isNotEmpty())
                <div class="stat-sub stat-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    {{ $paidPending->count() }} paid, slot conflict — admin review
                </div>
            @endif
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-purple"><i class="bi bi-brush"></i></div>
            <div class="stat-lbl stat-lbl-top">Housekeeping</div>
            <div class="stat-val" id="fdHousekeepingStat">{{ $stats['pending_tasks'] }}</div>
            <div class="stat-sub">Open tasks and reports</div>
        </div>
