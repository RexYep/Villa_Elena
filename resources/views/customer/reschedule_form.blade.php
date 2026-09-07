@extends('layouts.customer')

@section('title', 'Reschedule Booking — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 600px;
        }

        .page-title {
            font-weight: 700;
        }

        .booking-summary {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .summary-name,
        .summary-dates {
            overflow-wrap: anywhere;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .summary-logo {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .summary-name {
            font-weight: 600;
            font-size: 15px;
        }

        .summary-dates {
            font-size: 14px;
            color: var(--muted);
            margin-top: 3px;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .form-card-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }

        .form-card-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 600;
        }

        .form-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            display: block;
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        .form-control {
            padding: 11px 14px;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .mb-16 {
            margin-bottom: 16px;
        }

        /* Kaparehong anyo ng slot picker sa portal/property.blade.php. */
        .slot-options {
            gap: 10px;
        }

        .slot-option {
            position: relative;
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 34px 12px 14px;
            cursor: pointer;
            transition: all .15s;
        }

        .slot-option input {
            position: absolute;
            top: 14px;
            right: 12px;
            margin: 0;
        }

        .slot-option-label strong {
            display: block;
            font-size: 14px;
            color: var(--stone);
        }

        .slot-option-label small {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
            line-height: 1.4;
        }

        .slot-option:has(input:checked) {
            border-color: var(--gold);
            background: rgba(184, 148, 63, .06);
        }

        .slot-option:hover {
            border-color: var(--gold);
        }

        /* ── Availability ── */
        .slot-state {
            display: inline-block;
            margin-top: 6px;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .2px;
        }

        .slot-state.is-open {
            background: #dcfce7;
            color: #15803d;
        }

        .slot-state.is-taken {
            background: #fee2e2;
            color: #b91c1c;
        }

        .slot-state.is-past {
            background: var(--sand);
            color: var(--muted);
        }

        /* Ang saradong card ay hindi lang naka-disable — mukha rin siyang
           hindi mapipili, kung hindi ay paulit-ulit itong tatapikin. */
        .slot-option.is-unavailable {
            opacity: .55;
            cursor: not-allowed;
            background: #fafafa;
        }

        .slot-option.is-unavailable:hover {
            border-color: var(--border);
        }

        .slot-alert {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.5;
        }

        .slot-suggest {
            margin-top: 12px;
        }

        .slot-suggest-label {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .slot-suggest-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .slot-suggest-btn {
            background: var(--sand);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 8px 14px;
            font-family: 'Jost', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--stone);
            cursor: pointer;
            transition: all .15s;
        }

        .slot-suggest-btn:hover {
            background: var(--gold);
            border-color: var(--gold);
        }

        .btn-submit:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .btn-submit:disabled:hover {
            background: var(--stone);
            color: #fff;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .field-error {
            font-size: 13px;
            color: #dc2626;
            margin-top: 4px;
        }

        .notice-box {
            background: #f9f5ee;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .btn-submit {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            margin-top: 8px;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .btn-back:hover {
            color: var(--stone);
        }

        @media (max-width:480px) {
            .two-col {
                grid-template-columns: 1fr;
            }

            /* Dating `flex-wrap: wrap`: bumababa ang teksto sa ilalim ng
               logo at nauubos ang isang buong linya, kahit kasya naman
               silang magkatabi (48px na icon + ~180px na teksto sa 320px).
               Naka-align na lang ang icon sa itaas kasama ng pangalan. */
            .booking-summary {
                align-items: flex-start;
                gap: 14px;
                padding: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to booking
    </a>

    <div class="page-title">Reschedule Booking</div>
    <div class="page-sub">Change your check-in/check-out date and time</div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <div class="booking-summary">
        <div class="summary-icon"><img src="{{ asset('images/logo.png') }}" alt="" class="summary-logo"></div>
        <div>
            <div class="summary-name">{{ $booking->property->property_name }}</div>
            <div class="summary-dates">
                Currently: {{ $booking->check_in_date->format('M d, Y') }}
                {{ \Carbon\Carbon::parse($booking->check_in_time)->format('g:i A') }}
                — {{ $booking->check_out_date->format('M d, Y') }}
                {{ \Carbon\Carbon::parse($booking->check_out_time)->format('g:i A') }}
                · {{ $booking->booking_ref }}
            </div>
        </div>
    </div>

    <div class="notice-box">
        <i class="bi bi-info-circle me-1"></i>
        Choose a new check-in date and slot — Day (8:00 AM–5:00 PM) or Night (7:00 PM–6:00 AM).
        If the new dates have a different price, we'll bill you the difference or issue a refund automatically.
        <br><br>
        <strong style="color:var(--stone);">Reschedule policy:</strong>
        Each booking may be rescheduled up to {{ \App\Models\Booking::MAX_RESCHEDULES }} times, and only up to
        {{ \App\Models\Booking::RESCHEDULE_CUTOFF_DAYS }} days before check-in.
        You have <strong style="color:var(--stone);">{{ $booking->reschedulesRemaining() }}</strong> left for this booking.
    </div>

    <form method="POST" action="{{ route('customer.bookings.reschedule.update', $booking) }}">
        @csrf
        @method('PATCH')
        <div class="form-card">
            <div class="form-card-head">
                <h3>New Dates</h3>
            </div>
            <div class="form-card-body">
                <div class="mb-16">
                    <label class="form-label">Check-in Date</label>
                    <input type="date" name="checkin" id="checkinDate" class="form-control @error('checkin') is-invalid @enderror"
                        value="{{ old('checkin', $booking->check_in_date->format('Y-m-d')) }}"
                        min="{{ today()->format('Y-m-d') }}" required>
                </div>
                {{-- Dalawang hubad na radio ang mga ito dati: 16px na target
                     sa telepono, at ibang-iba sa mga card na nakita ng bisita
                     noong nag-book siya sa property page. Iisang pagpili ito,
                     kaya iisa rin dapat ang hitsura — at ang buong card ang
                     mapipindot, hindi lang ang maliit na bilog. --}}
                <div class="mb-16">
                    <label class="form-label">Slot</label>
                    <div class="two-col slot-options">
                        @foreach (\App\Models\Booking::SLOTS as $key => $def)
                            <label class="slot-option" data-slot="{{ $key }}">
                                <input type="radio" name="slot" value="{{ $key }}" id="slot_{{ $key }}"
                                    {{ old('slot', $booking->slotKey() ?? 'day') === $key ? 'checked' : '' }}>
                                <span class="slot-option-label">
                                    <strong>{{ ucfirst($key) }}</strong>
                                    <small>{{ \Carbon\Carbon::parse($def['check_in'])->format('g:i A') }} –
                                        {{ \Carbon\Carbon::parse($def['check_out'])->format('g:i A') }}{{ $def['overnight'] ? ' next day' : '' }}</small>
                                    <span class="slot-state" data-state-for="{{ $key }}"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    {{-- Lumilitaw lang kapag may hindi mapipili sa napiling
                         petsa — at hindi ito dead end: mga tunay na bukas na
                         petsa ang inaalok, mapipindot para punan ang form. --}}
                    <div class="slot-alert" id="slotAlert" hidden></div>
                    <div class="slot-suggest" id="slotSuggest" hidden>
                        <div class="slot-suggest-label">Next available</div>
                        <div class="slot-suggest-list" id="slotSuggestList"></div>
                    </div>
                </div>
                @error('dates')
                    <div class="field-error" style="margin-bottom:12px;">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn-submit" id="submitBtn"><i class="bi bi-calendar-check"></i> Confirm Reschedule</button>
            </div>
        </div>
    </form>
@endsection


@push('scripts')
    <script>
        // ── Availability para sa reschedule ──────────────────────────────
        // Kaparehong mapa ng ipinipinta ng calendar sa property page
        // (Booking::slotAvailabilityMap), pero hindi kasama ang booking na
        // ito — kaya hindi nito hinaharangan ang sarili nitong slot, tulad
        // ng ginagawa ng hasConflict() kapag nag-submit na.
        const BOOKED_SLOTS = @json($slotAvailability);
        const PAST_SLOTS_TODAY = @json($pastSlotsToday);
        const SLOT_DEFS = @json(\App\Models\Booking::SLOTS);
        const SLOT_KEYS = Object.keys(SLOT_DEFS);
        const TODAY_STR = @json(now()->format('Y-m-d'));

        const dateInput = document.getElementById('checkinDate');
        const alertBox = document.getElementById('slotAlert');
        const suggestBox = document.getElementById('slotSuggest');
        const suggestList = document.getElementById('slotSuggestList');
        const submitBtn = document.getElementById('submitBtn');

        function isTaken(dateStr, slotKey) {
            return Object.prototype.hasOwnProperty.call(BOOKED_SLOTS[dateStr] || {}, slotKey);
        }

        // Ang slot na lumipas na ngayong araw ay tinatanggihan din ng
        // server (`$checkin->isPast()`), kaya dito pa lang ay sarado na.
        function isPast(dateStr, slotKey) {
            return dateStr === TODAY_STR && PAST_SLOTS_TODAY.includes(slotKey);
        }

        function stateOf(dateStr, slotKey) {
            if (isPast(dateStr, slotKey)) return 'past';
            return isTaken(dateStr, slotKey) ? 'taken' : 'open';
        }

        function addDays(dateStr, n) {
            const [y, m, d] = dateStr.split('-').map(Number);
            const dt = new Date(y, m - 1, d + n);
            return dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' +
                String(dt.getDate()).padStart(2, '0');
        }

        function prettyDate(dateStr) {
            const [y, m, d] = dateStr.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        }

        // Unang tatlong (petsa, slot) na bukas pa, simula sa napiling petsa.
        function nextOpen(fromDate, limit) {
            const found = [];
            let cursor = fromDate;
            for (let i = 0; i < 120 && found.length < limit; i++) {
                for (const key of SLOT_KEYS) {
                    if (stateOf(cursor, key) === 'open') {
                        found.push({ date: cursor, slot: key });
                        if (found.length === limit) break;
                    }
                }
                cursor = addDays(cursor, 1);
            }
            return found;
        }

        function refresh() {
            const dateStr = dateInput.value;
            if (!dateStr) return;

            let openCount = 0;

            SLOT_KEYS.forEach(function (key) {
                const card = document.querySelector('.slot-option[data-slot="' + key + '"]');
                const badge = card.querySelector('.slot-state');
                const radio = card.querySelector('input');
                const state = stateOf(dateStr, key);

                badge.className = 'slot-state is-' + (state === 'open' ? 'open' : state);
                badge.textContent = state === 'open' ? 'Available' :
                    (state === 'past' ? 'Already started today' : 'Already booked');

                card.classList.toggle('is-unavailable', state !== 'open');
                radio.disabled = state !== 'open';

                if (state === 'open') {
                    openCount++;
                } else if (radio.checked) {
                    // Huwag iwang nakapili ang isang slot na hindi naman
                    // puwede — iyon ang eksaktong pagpili na tatanggihan.
                    radio.checked = false;
                }
            });

            // Kung nabakante ang pinili dahil sarado ito, ilipat sa natitirang bukas.
            if (openCount > 0 && !document.querySelector('input[name="slot"]:checked')) {
                const firstOpen = SLOT_KEYS.find(k => stateOf(dateStr, k) === 'open');
                document.getElementById('slot_' + firstOpen).checked = true;
            }

            const noneOpen = openCount === 0;
            submitBtn.disabled = noneOpen;
            alertBox.hidden = !noneOpen;
            if (noneOpen) {
                alertBox.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' +
                    'Both slots on ' + prettyDate(dateStr) + ' are taken. Pick another date.';
            }

            const suggestions = noneOpen ? nextOpen(dateStr, 3) : [];
            suggestBox.hidden = suggestions.length === 0;
            suggestList.innerHTML = '';
            suggestions.forEach(function (s) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'slot-suggest-btn';
                btn.textContent = prettyDate(s.date) + ' · ' + s.slot.charAt(0).toUpperCase() + s.slot.slice(1);
                btn.addEventListener('click', function () {
                    dateInput.value = s.date;
                    refresh();
                    document.getElementById('slot_' + s.slot).checked = true;
                });
                suggestList.appendChild(btn);
            });
        }

        dateInput.addEventListener('change', refresh);
        dateInput.addEventListener('input', refresh);
        refresh();
    </script>
@endpush
