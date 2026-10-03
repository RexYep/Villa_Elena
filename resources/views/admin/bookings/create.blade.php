@extends('layouts.admin')

@section('title', 'New Booking — Villa Elena Admin')
@section('page-title', 'New Booking')
@section('page-subtitle', 'Create a booking manually or for a walk-in guest')

@push('styles')
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            align-items: start;
        }

        .btn-submit {
            padding: 12px 24px;
            width: 100%;
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
        }

        .btn-cancel:hover {
            background: var(--sand);
            color: var(--text-main);
        }

        .btn-submit:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        /* Live availability badge above the price preview */
        .availability-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            background: var(--sand);
            color: var(--muted);
        }

        .availability-status.is-available {
            background: #e6f4ea;
            color: #1e7b3c;
        }

        .availability-status.is-unavailable {
            background: #fdecea;
            color: #b3261e;
        }

        /* Price Preview */
        .price-preview {
            background: var(--terracotta);
            border-radius: 12px;
            padding: 20px;
            color: #fff;
        }

        .price-preview h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            color: var(--gold-light);
            margin-bottom: 14px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .price-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 16px;
            margin-top: 6px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .price-row span:first-child {
            color: rgba(255, 255, 255, 0.6);
        }

        /* 1150px, not 900px. The 320px price-preview column is a fixed track, so
           from 993px — where the admin's own 260px sidebar reappears — up to
           about 1150px the form is left with almost nothing: measured 317px of
           form against 320px of sidebar at a 1000px viewport, with the slot
           labels wrapping to two and three lines. The 561px rule below fixes the
           STACKED layout only; it cannot help here, because at these widths the
           form column is narrower than it would be on a 561px phone. Stacking
           through the band gives the form the full width instead.

           1200px is measured, and 1150px was not enough: at a 1160px viewport the
           two-column layout returns with a 477px form, the pairs land at 205px,
           and the slot labels wrap to two lines again. At 1200px the pairs are
           225px and the labels hold one line — matching the 226px-per-column
           floor measured for the 561px rule below. */
        @media (max-width: 1200px) {
            .form-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        /* admin.css collapses every .two-col to one column below 900px — but on
           this page 900px is precisely where .form-grid drops its 320px sidebar
           and hands the form the FULL width. So the inner grid collapsed at the
           exact moment its container got wider: at 768px the form column is
           713px and the two-col inside it was a single 663px column, which made
           the tablet render a TALLER page (1762px) than the phone (1732px).
           561px is measured, not picked: the longest slot label ("Night (7:00 PM
           – 6:00 AM)") needs 199px plus the radio's 24px indent, so a column has
           to clear ~223px. At a 540px viewport the columns come out 224px and
           that label wraps to two lines; at 561px they are 234px and both labels
           hold one line. Below it, single column is genuinely right.

           .form-grid prefix is for specificity, not scoping: admin.css's rule is
           a bare .two-col, and `composer dev` injects admin.css after this block,
           so an equal-specificity override would work in production and silently
           lose in dev. */
        @media (min-width: 561px) {
            .form-grid .two-col {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
@endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.bookings.index') }}">Bookings</a>
        <span class="sep">›</span>
        <span class="current">New Booking</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach ($errors->all() as $error)
                {{ $error }}.
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.store') }}" id="bookingForm">
        @csrf

        <div class="form-grid">

            <div>
                {{-- Guest --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-cyan"><i class="bi bi-person"></i></div>
                        <h3>Guest</h3>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label">Select Guest <span class="req">*</span></label>
                        {{-- Ang id ay ginagamit ng live quote: nakakaapekto sa
                             PRESYO kung sino ang guest, dahil may mga promong
                             para lang sa mga regular na customer. --}}
                        <select name="user_id" id="guestSelect"
                            class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Choose a guest...</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('user_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->full_name }} — {{ $customer->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <div class="text-muted-theme" style="margin-top:10px;font-size: 14px;">
                            Guest not in the list? <a href="{{ route('admin.users.create') }}" style="color:#2e5fa3;">Create
                                new guest account →</a>
                        </div>
                    </div>
                </div>

                {{-- Property & Dates --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-green"><i class="bi bi-house-door"></i></div>
                        <h3>Property & Dates</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label">Property</label>
                            @if ($villa)
                                {{-- Single-villa model: nothing to choose, so it's fixed. --}}
                                <input type="hidden" name="property_id" id="propertyId" value="{{ $villa->id }}">
                                <input type="text" class="form-control" value="{{ $villa->property_name }}" readonly
                                    tabindex="-1" aria-label="Property">
                            @else
                                <input type="hidden" id="propertyId" value="">
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-tools me-2"></i>The villa is under maintenance and can't be booked right now.
                                </div>
                            @endif
                            @error('property_id')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Check-in Date <span class="req">*</span></label>
                            <input type="date" name="check_in_date" id="checkIn"
                                class="form-control @error('check_in_date') is-invalid @enderror"
                                value="{{ old('check_in_date') }}" min="{{ date('Y-m-d') }}" required>
                            @error('check_in_date')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slot <span class="req">*</span></label>
                            {{-- auto-fit sa halip na .two-col (isang matigas na 1fr 1fr):
                                 tatlo na ang slot, at ang isang 2x2 na grid ay nag-iiwan ng
                                 ulilang cell. Hinahango sa bookableSlotKeys() — hindi
                                 inaalok ang slot na wala pang presyo. --}}
                            @php
                                $slotKeys = \App\Models\Booking::bookableSlotKeys();
                                $checkedSlot = old('slot', $slotKeys[0] ?? null);
                            @endphp
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;">
                                @foreach ($slotKeys as $slotKey)
                                    <div class="form-check">
                                        <input type="radio" name="slot" value="{{ $slotKey }}" id="slot_{{ $slotKey }}"
                                            class="form-check-input" {{ $checkedSlot === $slotKey ? 'checked' : '' }}
                                            @if ($loop->first) required @endif>
                                        <label for="slot_{{ $slotKey }}" class="form-check-label">{{ \App\Models\Booking::SLOTS[$slotKey]['label'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Ipinapaliwanag kung bakit iisa lang ang pagpipilian
                                 sa isang 22-oras na petsa. --}}
                            <div id="slotOnlyNote" hidden
                                style="margin-top:6px; color:#0f766e; font-size:13px;"></div>
                            @error('slot')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="two-col">
                            <div>
                                <label class="form-label">Number of Guests <span class="req">*</span></label>
                                <select name="num_guests"
                                    class="form-select @error('num_guests') is-invalid @enderror" required>
                                    @for ($g = 1; $g <= ($villa->max_capacity ?? 1); $g++)
                                        <option value="{{ $g }}" {{ (int) old('num_guests', 1) === $g ? 'selected' : '' }}>
                                            {{ $g }} guest{{ $g > 1 ? 's' : '' }}
                                        </option>
                                    @endfor
                                </select>
                                @error('num_guests')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Booking Source <span class="req">*</span></label>
                                <select name="source" class="form-select" required>
                                    <option value="walk_in" {{ old('source') == 'walk_in' ? 'selected' : '' }}>🚶 Walk-in
                                    </option>
                                    <option value="phone" {{ old('source') == 'phone' ? 'selected' : '' }}>📞 Phone</option>
                                    <option value="online" {{ old('source') == 'online' ? 'selected' : '' }}>🌐 Online</option>
                                    <option value="partner" {{ old('source') == 'partner' ? 'selected' : '' }}>🤝 Partner
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Special Requests --}}
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-amber"><i class="bi bi-chat-dots"></i></div>
                        <h3>Special Requests</h3>
                    </div>
                    <div class="form-card-body">
                        <textarea name="special_requests" class="form-control" rows="3"
                            placeholder="Any special requests or notes for this booking...">{{ old('special_requests') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Price Preview --}}
            <div>
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon tag-amber"><i class="bi bi-receipt"></i></div>
                        <h3>Price Preview</h3>
                    </div>
                    <div class="form-card-body">
                        <div id="availabilityStatus" class="availability-status" role="status" aria-live="polite">
                            <i class="bi bi-calendar-event"></i> <span>Pick a date and slot to check availability.</span>
                        </div>
                        <div class="price-preview">
                            <h4>Estimated Cost</h4>
                            <div class="price-row">
                                <span>Date</span>
                                <span id="previewDate">—</span>
                            </div>
                            <div class="price-row">
                                <span>Slot</span>
                                <span id="previewSlot">—</span>
                            </div>
                            <div class="price-row">
                                <span>Package Rate</span>
                                <span id="previewRate">—</span>
                            </div>
                            <div class="price-row" id="previewPromoRow" hidden>
                                <span id="previewPromoLabel">Promo</span>
                                <span id="previewDiscount">—</span>
                            </div>
                            <div class="price-row">
                                <span>Total</span>
                                <span id="previewTotal">—</span>
                            </div>
                        </div>
                        <p class="text-muted-theme" style="font-size: 13px;margin-top:10px;text-align:center;">
                            Includes seasonal pricing and any promo active on the check-in date.
                        </p>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-body">
                        <button type="submit" class="btn-submit" id="submitBtn" {{ $villa ? '' : 'disabled' }}>
                            <i class="bi bi-calendar-plus me-2"></i> Create Booking
                        </button>
                        <a href="{{ route('admin.bookings.index') }}" class="btn-cancel">Cancel</a>
                    </div>
                </div>
            </div>

        </div>
    </form>

@endsection

@push('scripts')
    <script>
        (function() {
            // Price and availability both come from the server (quoteFor() +
            // hasConflict(), the same calls store() makes). Never compute
            // pricing here — a JS copy can't see pricing_rules or promos.
            const QUOTE_URL = @json(route('admin.bookings.quote'));
            const propertyId = document.getElementById('propertyId').value;
            const checkInEl = document.getElementById('checkIn');
            const guestEl = document.getElementById('guestSelect');
            const submitBtn = document.getElementById('submitBtn');
            const statusEl = document.getElementById('availabilityStatus');
            const $ = id => document.getElementById(id);
            const peso = n => '₱' + Number(n).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            let controller = null;

            function setStatus(state, icon, text) {
                statusEl.className = 'availability-status' + (state ? ' is-' + state : '');
                statusEl.innerHTML = '';
                const i = document.createElement('i');
                i.className = 'bi ' + icon;
                const span = document.createElement('span');
                span.textContent = text;
                statusEl.append(i, ' ', span);
            }

            function resetPreview() {
                ['previewDate', 'previewSlot', 'previewRate', 'previewTotal'].forEach(id => $(id).textContent = '—');
                $('previewPromoRow').hidden = true;
            }

            async function refresh() {
                const checkIn = checkInEl.value;
                const slotInput = document.querySelector('input[name="slot"]:checked');

                if (!propertyId) return;
                if (!checkIn || !slotInput) {
                    resetPreview();
                    setStatus('', 'bi-calendar-event', 'Pick a date and slot to check availability.');
                    submitBtn.disabled = true;
                    return;
                }

                // Only the latest selection's answer may land.
                if (controller) controller.abort();
                controller = new AbortController();

                submitBtn.disabled = true;
                setStatus('', 'bi-hourglass-split', 'Checking availability…');

                try {
                    const params = new URLSearchParams({
                        property_id: propertyId,
                        checkin: checkIn,
                        slot: slotInput.value
                    });

                    // Kung sino ang guest ay bahagi ng presyo, hindi lang
                    // ng record: ang isang regular na customer ay maaaring
                    // may promong para lang sa kanya. Kapag wala pang
                    // piniling guest, hindi ito ipinapasa at list price
                    // ang ibinibigay ng server.
                    if (guestEl && guestEl.value) params.set('user_id', guestEl.value);
                    const res = await fetch(QUOTE_URL + '?' + params, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: controller.signal
                    });
                    const data = await res.json();

                    if (!data.valid) {
                        resetPreview();
                        setStatus('unavailable', 'bi-exclamation-circle', data.message || 'Select a valid date and slot.');
                        return;
                    }

                    $('previewDate').textContent = data.day_label;
                    $('previewSlot').textContent = data.slot_label;
                    $('previewRate').textContent = peso(data.base);
                    $('previewTotal').textContent = peso(data.total);

                    if (data.discount > 0) {
                        // Ang `promo_scope` ay naroon lang kapag ang bawas
                        // ay dahil REGULAR ang customer. Sinasabi ito
                        // dahil magkaibang bagay ang ipapaliwanag sa guest:
                        // ang seasonal promo ay para sa lahat, ito ay sa
                        // kanya lang.
                        $('previewPromoLabel').textContent = (data.promo_label || 'Promo') +
                            (data.promo_value ? ' (' + data.promo_value + ')' : '') +
                            (data.promo_scope ? ' — ' + data.promo_scope : '');
                        $('previewDiscount').textContent = '−' + peso(data.discount);
                        $('previewPromoRow').hidden = false;
                    } else {
                        $('previewPromoRow').hidden = true;
                    }

                    if (data.available) {
                        setStatus('available', 'bi-check-circle-fill', 'Available — this slot is open.');
                        submitBtn.disabled = false;
                    } else {
                        setStatus('unavailable', 'bi-x-circle-fill', data.message);
                    }
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    // Can't verify: let the admin submit — store() still
                    // checks availability atomically via reserveSlot().
                    setStatus('', 'bi-wifi-off', 'Couldn’t check availability. It will be verified on submit.');
                    submitBtn.disabled = false;
                }
            }

            // ── Aling slot ang inaalok sa piniling petsa ──────────────
            //
            // Kaparehong panuntunan ng Booking::slotsOfferedOn(): window muna
            // at EKSKLUSIBO, at kung wala, ang mga hindi-windowed na slot.
            const WINDOWED_SLOTS = @json(\App\Models\Booking::windowedSlotKeys());
            const SLOT_WINDOW_DATES = @json(\App\Models\Booking::slotWindowDates());
            const SLOT_NAMES = @json(collect(\App\Models\Booking::SLOTS)->map(fn ($d) => $d['name'])->all());
            const ALL_SLOT_KEYS = @json(\App\Models\Booking::bookableSlotKeys());

            function slotsOfferedOn(dateStr) {
                for (const s of WINDOWED_SLOTS) {
                    if ((SLOT_WINDOW_DATES[s] || []).includes(dateStr)) return [s];
                }
                return ALL_SLOT_KEYS.filter(k => !WINDOWED_SLOTS.includes(k));
            }

            function refreshSlotOptions() {
                const dateStr = checkInEl.value;
                if (!dateStr) return;

                const offered = slotsOfferedOn(dateStr);
                let keptChecked = false;

                ALL_SLOT_KEYS.forEach(function (key) {
                    const input = document.getElementById('slot_' + key);
                    if (!input) return;
                    const wrap = input.closest('.form-check');
                    const on = offered.includes(key);

                    if (wrap) wrap.hidden = !on;
                    input.disabled = !on;
                    if (input.checked && on) keptChecked = true;
                    if (input.checked && !on) input.checked = false;
                });

                if (!keptChecked && offered.length) {
                    const first = document.getElementById('slot_' + offered[0]);
                    if (first) first.checked = true;
                }

                const note = document.getElementById('slotOnlyNote');
                if (note) {
                    const only = offered.length === 1 && WINDOWED_SLOTS.includes(offered[0]);
                    note.hidden = !only;
                    if (only) {
                        note.textContent = 'This date is set up as a ' +
                            (SLOT_NAMES[offered[0]] || offered[0]) +
                            ' date, so Day and Night are not offered on it.';
                    }
                }
            }

            checkInEl.addEventListener('change', function () {
                refreshSlotOptions();
                refresh();
            });
            document.querySelectorAll('input[name="slot"]').forEach(el => el.addEventListener('change', refresh));
            // Ang pagpalit ng guest ay nagpapalit ng presyo kapag may
            // promong para sa mga regular — kaya muling nagtatanong.
            if (guestEl) guestEl.addEventListener('change', refresh);
            refreshSlotOptions();
            refresh();
        })();
    </script>
@endpush

