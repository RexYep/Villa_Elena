@extends('layouts.portal')

@section('title', $property->property_name . ' — Villa Elena Resort')

@push('styles')
    {{-- FullCalendar (parehong library na ginagamit sa admin calendar module) --}}
    @vite(['resources/js/portal-calendar.js'])
    <style>
        body {
            background: var(--cream);
        }

        .main {
            max-width: 1320px;
        }

        /* ── Gallery ── */
        .gallery {
            display: grid;
            grid-template-columns: 2.1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 12px;
            border-radius: 20px;
            overflow: hidden;
            height: 100%;
            min-height: 520px;
        }

        .gallery-main {
            grid-row: 1/-1;
            position: relative;
            height: 100%;
        }

        .gallery-main:only-child {
            grid-column: 1/-1;
        }

        .gallery-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }

        .gallery>div {
            overflow: hidden;
            position: relative;
        }

        .gallery>div:hover .gallery-img {
            transform: scale(1.03);
        }

        .gallery-placeholder {
            width: 100%;
            height: 100%;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            color: var(--muted);
            opacity: .3;
        }

        .gallery-count {
            position: absolute;
            bottom: 14px;
            right: 14px;
            background: rgba(0, 0, 0, .7);
            backdrop-filter: blur(4px);
            color: #fff;
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 12.5px;
            font-weight: 500;
        }

        /* ── Top Grid: Gallery + Availability (side by side) ── */
        .top-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 24px;
            align-items: stretch;
            margin-bottom: 36px;
        }

        .availability-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(44, 36, 22, .06);
        }

        .availability-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .availability-card-sub {
            font-size: 12.5px;
            color: var(--muted);
            margin-bottom: 14px;
            line-height: 1.5;
        }

        /* ── Layout ── */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 32px;
            align-items: start;
        }

        /* ── Property Info ── */
        .prop-type {
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .prop-name {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 14px;
            line-height: 1.1;
        }

        .prop-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .prop-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 14px;
            padding-top: 28px;
            border-top: 1px solid var(--border);
        }

        .section-title:first-of-type {
            border-top: none;
            padding-top: 0;
        }

        .description {
            font-size: 14px;
            line-height: 1.8;
            color: #5a4f3e;
        }

        /* ── Amenities ── */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: var(--sand);
            border-radius: 10px;
            font-size: 13px;
        }

        .amenity-item i {
            color: var(--gold);
            font-size: 14px;
        }

        /* ── Availability Calendar (FullCalendar, tulad ng ginagamit sa admin) ── */
        .fc-wrap {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            overflow: visible;
        }

        .fc {
            font-family: 'Jost', sans-serif;
        }

        .fc .fc-toolbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            color: var(--stone);
        }

        /* Toolbar: stack on small widths so buttons don't overlap the title */
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 8px;
            row-gap: 10px;
        }

        .fc .fc-toolbar .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .fc .fc-button {
            background: var(--sand);
            border: 1px solid var(--border);
            color: var(--stone);
            box-shadow: none;
            text-transform: capitalize;
            font-size: 14px;
            padding: 4px 10px;
        }

        .fc .fc-button:hover {
            background: var(--gold-light);
            color: #fff;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background: var(--stone);
            border-color: var(--stone);
            color: #fff;
        }

        .fc .fc-daygrid-day.fc-day-today {
            background: rgba(184, 148, 63, .08);
        }

        /* Make day numbers more visible */
        .fc .fc-daygrid-day-number {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 8px;
            color: var(--stone);
        }

        .fc .fc-col-header-cell-cushion {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 4px;
        }

        .fc .fc-daygrid-day-frame {
            min-height: 48px;
        }

        .fc-event.booked-event {
            background: #dc2626;
            border: none;
            color: #fff;
            font-size: 12px;
            padding: 1px 4px;
            cursor: default;
            border-radius: 4px;
        }

        /* List view: i-override ang default light/white hover overlay ng
           FullCalendar (nagiging invisible ang puting text sa ibabaw
           nito) — panatilihing pula ang background kahit naka-hover. */
        .fc-list-event.booked-event td {
            background: #dc2626 !important;
            color: #fff !important;
        }

        .fc-list-event.booked-event:hover td {
            background: #a91d1d !important;
            color: #fff !important;
        }

        .fc-list-event.booked-event .fc-list-event-title,
        .fc-list-event.booked-event .fc-list-event-time {
            color: #fff !important;
        }

        .cal-legend {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: var(--muted);
            margin-top: 14px;
        }

        .cal-legend span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cal-legend i {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            display: inline-block;
        }

        /* ── Booking Card ── */
        .booking-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 24px;
            position: sticky;
            top: calc(var(--nav-h) + 20px);
            box-shadow: 0 8px 32px rgba(44, 36, 22, .08);
        }

        .booking-price {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .booking-price span {
            font-size: 15px;
            font-weight: 400;
            color: var(--muted);
            font-family: 'Jost', sans-serif;
        }

        .price-weekend {
            font-size: 14px;
            color: var(--gold);
            margin-bottom: 18px;
        }

        .form-label {
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
            display: block;
            font-weight: 600;
        }

        .price-preview {
            background: var(--sand);
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }

        .price-line {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 5px 0;
            color: var(--muted);
        }

        .price-line.total {
            font-weight: 700;
            font-size: 15px;
            color: var(--stone);
            border-top: 1px solid var(--border);
            padding-top: 10px;
            margin-top: 4px;
        }

        .price-line.promo {
            color: #15803d;
            font-weight: 600;
        }

        .price-deposit {
            font-size: 14px;
            color: var(--terracotta);
            text-align: center;
            margin-top: 8px;
        }

        .btn-book-now {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Jost', sans-serif;
            width: 100%;
            cursor: pointer;
            transition: all .2s;
            margin-top: 8px;
        }

        .btn-book-now:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-book-now:disabled {
            background: var(--border);
            color: var(--muted);
            cursor: not-allowed;
        }

        .login-prompt {
            background: var(--sand);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 8px;
        }

        .login-prompt a {
            color: var(--gold);
            font-weight: 600;
            text-decoration: none;
        }

        .unavail-banner {
            background: #fee2e2;
            color: #dc2626;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .invalid-feedback {
            font-size: 14px;
            color: #dc2626;
            margin-top: 4px;
            display: block;
        }

        .duration-note {
            font-size: 14px;
            margin-top: 6px;
            display: none;
            padding: 8px 10px;
            border-radius: 8px;
        }

        .duration-note.ok {
            display: block;
            background: #dcfce7;
            color: #15803d;
        }

        .duration-note.warn {
            display: block;
            background: #fee2e2;
            color: #dc2626;
        }

        .slot-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .slot-option {
            position: relative;
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all .15s;
        }

        .slot-option input {
            position: absolute;
            top: 10px;
            right: 10px;
            margin: 0;
        }

        .slot-option-label strong {
            display: block;
            font-size: 13px;
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

        .price-preview .estimate-tag {
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
            display: block;
        }

        .price-preview .estimate-disclaimer {
            font-size: 13px;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.5;
        }

        .popover {
            font-family: 'Jost', sans-serif;
            max-width: 240px;
        }

        .popover-header {
            font-family: 'Playfair Display', serif;
            font-size: 13px;
            background: var(--stone);
            color: #fff;
        }

        .popover-body {
            font-size: 14px;
        }

        @media(max-width:900px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .top-grid {
                grid-template-columns: 1fr;
            }

            .gallery {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: 240px 140px;
                min-height: auto;
                height: auto;
            }

            .gallery-main {
                grid-row: 1/-1;
            }

            .booking-card {
                position: static;
            }
        }

        @media(max-width:480px) {
            .prop-name {
                font-size: 26px;
            }

            .slot-options {
                grid-template-columns: 1fr;
            }

            .gallery {
                grid-template-columns: 1fr;
                grid-template-rows: 200px 120px 120px;
            }

            .gallery-main {
                grid-row: auto;
            }
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a>
        <span>›</span>
        <a href="{{ route('home') }}#properties">Properties</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">{{ $property->property_name }}</span>
    </div>

    @if ($errors->any())
        <div
            style="background:#fee2e2;color:#dc2626;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
        </div>
    @endif

    {{-- Gallery + Availability (side by side) --}}
    <div class="top-grid">
        <div class="gallery" style="margin-bottom:0;">
            <div class="gallery-main">
                @if ($property->primaryImage)
                    <img src="{{ $property->primaryImage->url }}" class="gallery-img" alt="{{ $property->property_name }}">
                    @if ($property->images->count() > 1)
                        <div class="gallery-count"><i class="bi bi-images"></i> {{ $property->images->count() }} photos
                        </div>
                    @endif
                @else
                    <div class="gallery-placeholder"><i class="bi bi-house"></i></div>
                @endif
            </div>
            @foreach ($property->images->where('is_primary', 0)->take(2) as $img)
                <div>
                    <img src="{{ $img->url }}" class="gallery-img" alt="">
                </div>
            @endforeach
        </div>

        <div class="availability-card">
            <div class="availability-card-title">Check Availability</div>
            <p class="availability-card-sub">
                Tap or click the marked (red) date to view the exact booking time.
            </p>
            <div class="fc-wrap">
                <div id="availabilityCalendar"></div>
            </div>
            <div class="cal-legend">
                <span><i style="background:var(--sand);border:1px solid var(--border);"></i> Available</span>
                <span><i style="background:#dc2626;"></i> Booked</span>
            </div>
        </div>
    </div>

    <div class="detail-grid">

        {{-- Left: Property Info --}}
        <div>
            <div class="prop-type">{{ ucfirst($property->type) }}</div>
            <div class="prop-name">{{ $property->property_name }}</div>
            <div class="prop-meta">
                <span><i class="bi bi-people"></i> Up to {{ $property->max_capacity }} guests</span>
                @if ($property->floor_area)
                    <span><i class="bi bi-arrows-angle-expand"></i> {{ $property->floor_area }} sqm</span>
                @endif
                <span><i class="bi bi-geo-alt"></i> Villa Elena Resort</span>
            </div>

            @if ($property->description)
                <div class="section-title" style="border-top:none;padding-top:0;">About This Property</div>
                <p class="description">{{ $property->description }}</p>
            @endif

            {{-- Amenities --}}
            @php
                $amenities = is_array($property->amenities)
                    ? $property->amenities
                    : json_decode($property->amenities ?? '[]', true);
                $amenityIcons = [
                    'pool' => 'bi-water',
                    'wifi' => 'bi-wifi',
                    'ac' => 'bi-thermometer-snow',
                    'parking' => 'bi-car-front',
                    'kitchen' => 'bi-cup-hot',
                    'bbq' => 'bi-fire',
                    'tv' => 'bi-tv',
                    'washer' => 'bi-basket',
                    'gym' => 'bi-bicycle',
                    'bar' => 'bi-cup-straw',
                    'breakfast' => 'bi-egg-fried',
                    'spa' => 'bi-flower1',
                ];
            @endphp
            @if (count($amenities ?? []))
                <div class="section-title">Amenities</div>
                <div class="amenities-grid">
                    @foreach ($amenities as $amenity)
                        <div class="amenity-item">
                            <i class="bi {{ $amenityIcons[$amenity] ?? 'bi-check-circle' }}"></i>
                            {{ ucwords(str_replace('_', ' ', $amenity)) }}
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Right: Booking Card --}}
        <div>
            <div class="booking-card">
                @if ($property->status !== 'maintenance' && $allowOnlineBooking)
                    <div class="booking-price">₱{{ number_format($property->base_price, 0) }} <span>/ package</span></div>

                    <form method="GET" action="{{ route('portal.book', $property) }}" id="bookingForm">
                        <div class="mb-12">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" name="checkin" id="checkin" class="form-control"
                                value="{{ $checkin }}" min="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="mb-12">
                            <label class="form-label">Choose Your Slot</label>
                            <div class="slot-options">
                                <label class="slot-option">
                                    <input type="radio" name="slot" value="day" id="slot_day"
                                        {{ ($slot ?? 'day') === 'day' ? 'checked' : '' }}>
                                    <span class="slot-option-label">
                                        <strong>Day</strong>
                                        <small>8:00 AM – 5:00 PM</small>
                                    </span>
                                </label>
                                <label class="slot-option">
                                    <input type="radio" name="slot" value="night" id="slot_night"
                                        {{ ($slot ?? 'day') === 'night' ? 'checked' : '' }}>
                                    <span class="slot-option-label">
                                        <strong>Night</strong>
                                        <small>7:00 PM – 6:00 AM</small>
                                    </span>
                                </label>
                            </div>
                            <div id="durationNote" class="duration-note"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Guests</label>
                            <select name="guests" class="form-select">
                                @for ($g = 1; $g <= $property->max_capacity; $g++)
                                    <option value="{{ $g }}" {{ (int) $guests === $g ? 'selected' : '' }}>
                                        {{ $g }} guest{{ $g > 1 ? 's' : '' }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        {{-- Price Preview --}}
                        <div class="price-preview" id="pricePreview" style="display:none;">
                            <span class="estimate-tag" id="previewTag"><i class="bi bi-calculator"></i> Price</span>
                            <div class="price-line">
                                <span id="previewNights">— rate</span>
                                <span id="previewBase">—</span>
                            </div>
                            {{-- Lumalabas lang kapag may tumatamang seasonal
                                 promo sa napiling petsa/slot. --}}
                            <div class="price-line promo" id="previewPromoRow" style="display:none;">
                                <span id="previewPromoLabel">Promo</span>
                                <span id="previewPromoAmount">—</span>
                            </div>
                            <div class="price-line total">
                                <span>Total</span>
                                <span id="previewTotal">—</span>
                            </div>

                        </div>

                        @auth
                            <button type="submit" class="btn-book-now" id="bookBtn">
                                Check Availability
                            </button>
                        @else
                            <button type="button" class="btn-book-now" onclick="window.location='{{ route('login') }}'">
                                Sign In to Book
                            </button>
                            <div class="login-prompt">
                                Don't have an account?
                                <a href="{{ route('register') }}">Create one free →</a>
                            </div>
                        @endauth
                    </form>

                    {{-- Booked dates notice --}}
                    @if ($bookedRanges->count())
                        <div class="text-muted-theme"
                            style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size: 14px;">
                            <i class="bi bi-info-circle me-1"></i>
                            Some dates may not be available. We'll confirm availability when you proceed.
                        </div>
                    @endif
                @elseif ($property->status === 'maintenance')
                    <div class="unavail-banner">
                        <i class="bi bi-x-circle me-2"></i>
                        This property is currently unavailable.<br>
                        <a href="{{ route('home') }}" style="color:#dc2626;font-weight:600;">View other properties →</a>
                    </div>
                @else
                    <div class="unavail-banner">
                        <i class="bi bi-telephone me-2"></i>
                        Online booking is temporarily unavailable.<br>
                        Please contact us directly to reserve your stay.
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        const PRICE_PREVIEW_URL = "{{ route('portal.price-preview', $property) }}";

        function getSelectedSlot() {
            const checked = document.querySelector('input[name="slot"]:checked');
            return checked ? checked.value : null;
        }

        function showSlotNote() {
            const note = document.getElementById('durationNote');
            const slot = getSelectedSlot();
            if (!slot) {
                note.className = 'duration-note';
                note.textContent = '';
                return;
            }
            note.className = 'duration-note ok';
            note.innerHTML = slot === 'day' ?
                '<i class="bi bi-check-circle me-1"></i>Day slot — check-in 8:00 AM, check-out 5:00 PM.' :
                '<i class="bi bi-check-circle me-1"></i>Night slot — check-in 7:00 PM, check-out 6:00 AM.';
        }

        let previewAbortController = null;
        let previewDebounceTimer = null;

        function showPreviewLoading() {
            const box = document.getElementById('pricePreview');
            box.style.display = 'block';
            document.getElementById('previewTag').innerHTML = '<i class="bi bi-hourglass-split"></i> Kinukumpirma...';
            document.getElementById('previewNights').textContent = 'Checking availability...';
            document.getElementById('previewBase').textContent = '—';
            document.getElementById('previewTotal').textContent = '—';
        }

        function showPreviewError(message) {
            const box = document.getElementById('pricePreview');
            box.style.display = 'block';
            document.getElementById('previewTag').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Hindi Available';
            document.getElementById('previewNights').textContent = message;
            document.getElementById('previewBase').textContent = '—';
            document.getElementById('previewTotal').textContent = '—';
            const bookBtn = document.getElementById('bookBtn');
            if (bookBtn) bookBtn.disabled = true;
        }

        function fetchServerPreview(ci, slot) {
            if (previewAbortController) previewAbortController.abort();
            previewAbortController = new AbortController();

            showPreviewLoading();

            const params = new URLSearchParams({
                checkin: ci,
                slot: slot
            });

            fetch(`${PRICE_PREVIEW_URL}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: previewAbortController.signal
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.valid) {
                        showPreviewError(data.message || 'The selected date/time is not available.');
                        return;
                    }
                    document.getElementById('previewTag').innerHTML = '<i class="bi bi-calculator"></i> Price';
                    document.getElementById('previewNights').textContent = data.is_peak ?
                        'Peak package (Fri–Sun)' :
                        'Regular package (Mon–Thu / Sun PM)';
                    // `base_formatted` ang listahang presyo, `price_formatted`
                    // ang aktwal na babayaran matapos ang promo — magkaiba
                    // lang sila kapag may tumatamang promo.
                    document.getElementById('previewBase').textContent = data.base_formatted;
                    document.getElementById('previewTotal').textContent = data.price_formatted;

                    const promoRow = document.getElementById('previewPromoRow');
                    if (data.discount > 0) {
                        document.getElementById('previewPromoLabel').textContent = data.promo_label;
                        document.getElementById('previewPromoAmount').textContent = '−' + data.discount_formatted;
                        promoRow.style.display = '';
                    } else {
                        promoRow.style.display = 'none';
                    }
                    const bookBtn = document.getElementById('bookBtn');
                    if (bookBtn) {
                        bookBtn.disabled = false;
                        bookBtn.textContent = 'Reserve Now →';
                    }
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    showPreviewError('Unable to retrieve the price. Please try again.');
                });
        }

        function updatePreview() {
            const ci = document.getElementById('checkin').value;
            const slot = getSelectedSlot();
            showSlotNote();

            if (!ci || !slot) {
                document.getElementById('pricePreview').style.display = 'none';
                return;
            }

            // Debounce: maghintay ng 350ms bago mag-request, para hindi
            // sumabog ang requests habang patuloy na nag-a-adjust ang user.
            clearTimeout(previewDebounceTimer);
            previewDebounceTimer = setTimeout(() => fetchServerPreview(ci, slot), 350);
        }

        document.getElementById('checkin')?.addEventListener('change', updatePreview);
        document.querySelectorAll('input[name="slot"]').forEach(el => el.addEventListener('change', updatePreview));

        // Run on load if dates pre-filled
        updatePreview();

        // ── Availability Calendar (FullCalendar) ────────────────────────
        // Kung may from_time/to_time, gumagawa tayo ng TIMED event (hindi all-day)
        // para makita ang eksaktong oras kahit sa week/list view. Kung wala,
        // fallback sa all-day block gaya ng dati (ang "end" ay EXCLUSIVE sa
        // FullCalendar kaya may +1 araw na dinadagdag para tamang-tama ang
        // huling naka-highlight na araw).
        //
        // Ang time strings mula sa backend ay pwedeng "08:00" (24-hr) o
        // "8:00 AM" (12-hr) depende sa format function na ginagamit sa Model/
        // Controller — kaya kailangan ng flexible parser dito bago gawing Date
        // object, kung hindi, magiging "Invalid Date" ito at hindi mag-render
        // ang event nang walang anumang error na makikita (tahimik na fail).
        function parseTimeToHM(timeStr) {
            if (!timeStr) return null;
            const t = timeStr.trim();
            // 12-hour format: "8:00 AM", "08:00 PM", "8:00AM"
            let m = t.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
            if (m) {
                let h = parseInt(m[1], 10);
                const min = parseInt(m[2], 10);
                const period = m[3].toUpperCase();
                if (period === 'PM' && h !== 12) h += 12;
                if (period === 'AM' && h === 12) h = 0;
                return {
                    h,
                    min
                };
            }
            // 24-hour format: "08:00", "14:30"
            m = t.match(/^(\d{1,2}):(\d{2})$/);
            if (m) {
                return {
                    h: parseInt(m[1], 10),
                    min: parseInt(m[2], 10)
                };
            }
            return null;
        }

        function buildDate(dateStr, timeStr) {
            const [y, mo, d] = dateStr.split('-').map(Number);
            const hm = parseTimeToHM(timeStr);
            if (!hm) return new Date(y, mo - 1, d, 0, 0);
            return new Date(y, mo - 1, d, hm.h, hm.min);
        }

        const bookedRangesRaw = @json($bookedRanges);
        const calendarEvents = bookedRangesRaw.map((r, i) => {
            const timeLabel = (r.from_time || r.to_time) ?
                ` (${r.from_time ?? '—'} → ${r.to_time ?? '—'})` :
                '';
            const startHM = parseTimeToHM(r.from_time);
            const endHM = parseTimeToHM(r.to_time);
            if (startHM && endHM) {
                return {
                    id: 'booking-' + r.id,
                    title: 'Booked' + timeLabel,
                    start: buildDate(r.from, r.from_time),
                    end: buildDate(r.to, r.to_time),
                    allDay: false,
                    display: 'block',
                    classNames: ['booked-event'],
                    extendedProps: {
                        rangeIndex: i,
                        booking_id: r.id
                    }
                };
            }
            const endDate = new Date(r.to + 'T00:00:00');
            endDate.setDate(endDate.getDate() + 1);
            return {
                id: 'booking-' + r.id,
                title: 'Booked' + timeLabel,
                start: r.from,
                end: endDate.toISOString().split('T')[0],
                display: 'block',
                classNames: ['booked-event'],
                extendedProps: {
                    rangeIndex: i,
                    booking_id: r.id
                }
            };
        });

        // ── Live availability updates ────────────────────────────────────
        // When someone else books (or cancels) this property while this page
        // is open, patch the calendar in place instead of leaving it stale
        // until the visitor manually refreshes.
        function buildLiveEvent(data) {
            const timeLabel = (data.check_in_time || data.check_out_time) ?
                ` (${data.check_in_time ?? '—'} → ${data.check_out_time ?? '—'})` :
                '';
            const startHM = parseTimeToHM(data.check_in_time);
            const endHM = parseTimeToHM(data.check_out_time);
            if (startHM && endHM) {
                return {
                    id: 'booking-' + data.booking_id,
                    title: 'Booked' + timeLabel,
                    start: buildDate(data.check_in, data.check_in_time),
                    end: buildDate(data.check_out, data.check_out_time),
                    allDay: false,
                    display: 'block',
                    classNames: ['booked-event'],
                };
            }
            const endDate = new Date(data.check_out + 'T00:00:00');
            endDate.setDate(endDate.getDate() + 1);
            return {
                id: 'booking-' + data.booking_id,
                title: 'Booked' + timeLabel,
                start: data.check_in,
                end: endDate.toISOString().split('T')[0],
                display: 'block',
                classNames: ['booked-event'],
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('availabilityCalendar');
            const availCalendar = new FullCalendar.Calendar(el, {
                plugins: [FullCalendar.dayGridPlugin, FullCalendar.listPlugin],
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth'
                },
                height: 'auto',
                aspectRatio: 1.2,
                slotMinTime: '06:00:00',
                slotMaxTime: '26:00:00',
                scrollTime: '08:00:00',
                editable: false,
                selectable: false,
                events: calendarEvents,
                eventDidMount: function(info) {
                    // Tap/click-friendly popover (gumagana rin sa mobile, hindi lang hover)
                    // Ang title mismo ng popover ang nagpapakita ng eksaktong oras
                    // ng booking (hal. "Booked (8:00 AM → 12:00 PM)").
                    //
                    // Manual na kontrol (hindi basta trigger:'click') para awtomatikong
                    // mawala ang popover pagkalipas ng ilang segundo — hindi na
                    // kailangang i-click ulit para itago ito.
                    const popover = new bootstrap.Popover(info.el, {
                        title: info.event.title,
                        content: 'Naka-book ang Villa sa petsa/oras na ito.',
                        trigger: 'manual',
                        placement: 'top',
                        container: 'body'
                    });

                    info.el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const alreadyOpen = info.el.classList.contains('popover-open');

                        // Isara muna ang ibang bukas na popover (isa lang dapat bukas kada oras)
                        document.querySelectorAll('.popover-open').forEach(other => {
                            if (other !== info.el) {
                                bootstrap.Popover.getInstance(other)?.hide();
                                other.classList.remove('popover-open');
                                clearTimeout(other._popoverTimer);
                            }
                        });

                        if (alreadyOpen) {
                            popover.hide();
                            info.el.classList.remove('popover-open');
                            clearTimeout(info.el._popoverTimer);
                        } else {
                            popover.show();
                            info.el.classList.add('popover-open');
                            clearTimeout(info.el._popoverTimer);
                            info.el._popoverTimer = setTimeout(() => {
                                popover.hide();
                                info.el.classList.remove('popover-open');
                            }, 4000);
                        }
                    });
                }
            });
            availCalendar.render();

            // ── Live availability sync (Pusher) ────────────────────────────
            const PUSHER_KEY = '{{ env('PUSHER_APP_KEY') }}';
            if (PUSHER_KEY && window.Pusher) {
                const pusher = new Pusher(PUSHER_KEY, {
                    cluster: '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}'
                });
                const channel = pusher.subscribe('property-availability.{{ $property->id }}');

                channel.bind('availability.changed', function(data) {
                    if (data.action === 'blocked') {
                        if (!availCalendar.getEventById('booking-' + data.booking_id)) {
                            availCalendar.addEvent(buildLiveEvent(data));
                        }
                    } else if (data.action === 'freed') {
                        availCalendar.getEventById('booking-' + data.booking_id)?.remove();
                    }
                });
            }
        });
    </script>
@endpush

