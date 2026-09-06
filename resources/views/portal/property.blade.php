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

        /* ── Property Header (nauuna sa gallery — dapat alam agad ng
              bisita kung ANO ang tinitingnan niya bago ang mga larawan) ── */
        .prop-header {
            margin-bottom: 20px;
        }

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
            margin-bottom: 12px;
            line-height: 1.1;
        }

        .prop-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: var(--muted);
            flex-wrap: wrap;
        }

        .prop-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ── Gallery (buong lapad — ito ang hero ng page) ──
           Ang taas ay TINATAKDAAN dito, hindi ipinapasya ng larawan.
           Dating `min-height: 460px` lang ang nakasulat, at dahil ang
           taas ng grid ay indefinite, ang `height:100%` ng <img> ay
           bumabagsak pabalik sa intrinsic nitong sukat — kaya isang
           798×600 na litrato ang lumalaki nang 1240×932 sa laptop: mas
           mataas pa sa buong viewport, at ini-upscale nang lampas sa
           sariling resolusyon (kaya malabo). Ang clamp ay sumusunod sa
           lapad ng screen; ang `max-height` ang humahawak sa mga pandak
           na laptop screen, kung saan ang vw lang ay hindi sapat. */
        .gallery {
            display: grid;
            grid-template-columns: 2.1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 12px;
            border-radius: 20px;
            overflow: hidden;
            height: clamp(280px, 38vw, 520px);
            max-height: 56vh;
            margin-bottom: 36px;
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

        /* ── Layout ── */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 32px;
            align-items: start;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 6px;
            padding-top: 28px;
            border-top: 1px solid var(--border);
        }

        .section-title:first-of-type {
            border-top: none;
            padding-top: 0;
        }

        .section-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 14px;
            line-height: 1.5;
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

        /* ── Availability Calendar (FullCalendar, tulad ng sa admin) ──
           Walang events dito. Ang bawat araw ay may dalawang slot pill
           (Day/Night) na ipinipinta ng dayCellDidMount, dahil ang isang
           pulang bar sa buong araw ay nagsisinungaling: kapag gabi lang
           ang naka-book, bakante pa rin ang umaga. */
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

        /* Ang mga araw at header ng FullCalendar ay <a> — kung hindi ito
           ire-reset, minamana nila ang asul at may-salungguhit na estilo ng
           mga link ng portal, kaya mukhang pindutin sila kahit hindi. */
        .fc a {
            color: inherit;
            text-decoration: none;
        }

        /* Buong linggong lumipas na — walang pill, walang maipapakita.
           Sa telepono, isang buong hilera ito ng nasayang na taas. */
        .fc .fc-daygrid-body tr.week-all-past {
            display: none;
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

        .fc .fc-button-primary:disabled {
            background: var(--sand);
            border-color: var(--border);
            color: var(--muted);
            opacity: .5;
        }

        .fc .fc-daygrid-day.fc-day-today {
            background: rgba(184, 148, 63, .08);
        }

        .fc .fc-daygrid-day-number {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 8px 2px;
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
            min-height: 62px;
        }

        /* Laging walang laman ang events container — bawiin ang espasyo. */
        .fc .fc-daygrid-day-events {
            display: none;
        }

        .fc .fc-daygrid-day-frame.is-past {
            background: repeating-linear-gradient(-45deg,
                    transparent 0 6px,
                    rgba(44, 36, 22, .04) 6px 12px);
        }

        .fc .fc-daygrid-day-frame.is-past .fc-daygrid-day-number {
            color: var(--muted);
            opacity: .5;
        }

        /* ── Slot pills ── */
        .slot-pills {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 0 5px 5px;
        }

        .slot-pill {
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            border: 1px solid transparent;
            border-radius: 5px;
            padding: 2px 5px;
            font-family: 'Jost', sans-serif;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.5;
            text-align: left;
            letter-spacing: .2px;
        }

        /* Araw/gabi na icon: ito ang natitirang palatandaan kapag masikip
           na ang cell para sa buong salita. */
        .slot-pill i {
            flex: none;
            font-size: 10px;
            line-height: 1;
        }

        .slot-pill-txt {
            overflow: hidden;
            white-space: nowrap;
        }

        button.slot-pill {
            cursor: pointer;
            transition: all .15s;
        }

        /* Ang mga kulay ng estado ay hinahati ng pill sa grid at ng susi sa
           legend — iisang deklarasyon, para hindi sila magkahiwalay kapag
           may binago sa isa. */
        .slot-pill.is-open,
        .legend-swatch.is-open {
            background: var(--sand);
            border-color: var(--border);
            color: var(--stone);
        }

        button.slot-pill.is-open:hover {
            background: var(--gold-light);
            border-color: var(--gold);
            color: #fff;
        }

        .slot-pill.is-taken,
        .legend-swatch.is-taken {
            background: #fee2e2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        .slot-pill.is-taken .slot-pill-txt {
            text-decoration: line-through;
            text-decoration-thickness: 1px;
        }

        .slot-pill.is-selected,
        .legend-swatch.is-selected {
            background: var(--stone);
            border-color: var(--stone);
            color: #fff;
        }

        /* ── Legend ──
           Dalawang magkaibang tanong ang sinasagot nito, kaya dalawang
           pangkat — hindi isang patag na hanay ng apat na magkakapantay
           na bagay: (1) ANO ang dalawang slot, at (2) ANO ang ibig sabihin
           ng kulay. Ang "Top = Day · Bottom = Night" ay wala na: nakasulat
           na mismo sa mga pill ang "Day"/"Night", kaya ang posisyon ay
           hindi na kailangang isaulo. Ang oras naman ang talagang hindi
           nakikita sa grid — iyon ang pumalit. */
        .cal-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 30px;
            margin-top: 14px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .legend-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 16px;
        }

        /* Nakapirming lapad para pumila ang unang item ng dalawang pangkat
           sa iisang gilid — dalawang hilerang magkatugma ang nababasa nang
           mas mabilis kaysa dalawang nagsisimula sa magkaibang puwesto. */
        .legend-group-label {
            min-width: 86px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--stone);
            opacity: .45;
        }

        .cal-legend .legend-slot,
        .cal-legend .legend-state {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        /* Ang salitang Open/Booked/Your pick ang mismong sagot dito, kaya
           buo ang kulay nito; ang oras ang pangalawang detalye. */
        .cal-legend .legend-state {
            color: var(--stone);
        }

        .legend-slot i {
            color: var(--gold);
            font-size: 14px;
        }

        .legend-slot b {
            color: var(--stone);
            font-weight: 600;
        }

        /* Kaparehong hugis at kulay ng tunay na pill sa grid, hindi basta
           parisukat na patse — para tumugma ang tinitingnan sa itinuturo. */
        .legend-swatch {
            flex: none;
            position: relative;
            width: 26px;
            height: 14px;
            border: 1px solid transparent;
            border-radius: 5px;
        }

        /* Ang guhit sa gitna ang ikalawang senyas ng "booked" — hindi lang
           kulay, na hindi mapagkakatiwalaan sa colour-blind na mata at sa
           mababang liwanag ng screen sa labas. */
        .legend-swatch.is-taken::after {
            content: '';
            position: absolute;
            left: 4px;
            right: 4px;
            top: 50%;
            border-top: 1px solid currentColor;
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
            transition: box-shadow .3s, border-color .3s;
        }

        /* Kapag may pinili sa calendar, kailangang makita ng bisita na may
           nangyari sa card — lalo na sa mobile kung saan hindi ito sticky
           at nasa ibaba pa ng calendar. */
        .booking-card.is-flash {
            border-color: var(--gold);
            box-shadow: 0 8px 32px rgba(184, 148, 63, .35);
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

        @media(max-width:900px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }

            /* Isang hanay na ang layout dito, kaya ang mga hilera na ang
               nagtatakda ng taas — sumusukat sa lapad ng screen imbes na
               nakapako sa 240/140, at wala nang vh cap (walang sticky na
               booking card na kailangang makasabay sa fold). */
            .gallery {
                grid-template-columns: 1fr 1fr;
                grid-template-rows:
                    min(clamp(190px, 34vw, 300px), 34vh)
                    min(clamp(105px, 19vw, 170px), 19vh);
                height: auto;
                max-height: none;
                gap: 10px;
                border-radius: 16px;
            }

            .gallery-main {
                grid-row: 1/-1;
            }

            .booking-card {
                position: static;
            }

            /* Isang hanay na lang ang layout dito, kaya ang buong lapad ng
               screen ang pinakamahalagang resource ng kalendaryo: pitong
               cell iyon, at bawat pixel ay teksto sa loob ng pill. Kinakain
               nito pabalik ang padding ng `.main` (20px sa lapad na ito). */
            .fc-wrap {
                margin-left: -20px;
                margin-right: -20px;
                border-radius: 12px;
                border-left: 0;
                border-right: 0;
                padding: 14px 10px;
            }
        }

        /* Dating ginagawang 6px na walang-labas na bar ang mga pill dito
           (font-size:0). Ang resulta: sa telepono ay hindi na mababasa ang
           availability — ang mismong impormasyong hinahanap ng bisita sa
           bahaging ito. Ngayon ay lumiliit na lang sila: mas maliit na
           teksto, mas masikip na padding, at ang buong lapad ng screen ang
           ginagamit — hindi na nawawala ang label. */
        @media(max-width:560px) {
            .fc .fc-toolbar-title {
                font-size: 15px;
            }

            .fc .fc-button {
                font-size: 13px;
                padding: 3px 9px;
            }

            .fc .fc-col-header-cell-cushion {
                font-size: 11px;
                letter-spacing: 0;
                padding: 6px 2px;
            }

            .fc .fc-daygrid-day-number {
                font-size: 11px;
                padding: 4px 5px 2px;
            }

            .fc .fc-daygrid-day-frame {
                min-height: 58px;
            }

            .slot-pills {
                padding: 0 3px 4px;
                gap: 2px;
            }

            .slot-pill {
                font-size: 10px;
                padding: 2px 3px;
                gap: 3px;
                letter-spacing: 0;
            }

            .slot-pill i {
                font-size: 9px;
            }

            .cal-legend {
                gap: 10px 14px;
                font-size: 12px;
                margin-top: 12px;
                padding: 10px 12px;
            }

            /* Walang puwang na maipapamigay sa isang nakapirming hanay ng
               label sa telepono — bumabalik ito sa sariling sukat. */
            .legend-group-label {
                min-width: 0;
            }
        }

        @media(max-width:480px) {
            .prop-name {
                font-size: 26px;
            }

            .fc-wrap {
                margin-left: -16px;
                margin-right: -16px;
                padding: 12px 6px;
            }

            .slot-options {
                grid-template-columns: 1fr;
            }

            /* Isang explicit na hilera lang — ang pangunahing larawan.
               Ang mga pangalawa ay pumapasok sa mga IMPLICIT na hilera,
               kaya lumilitaw lang ang mga ito kung may larawan ngang
               ilalagay doon. Nakapirming tatlong hilera dati, kaya ang
               property na may iisang litrato ay may ~200px na blangko sa
               ilalim nito sa telepono. */
            .gallery {
                grid-template-columns: 1fr;
                grid-template-rows: clamp(180px, 50vw, 260px);
                grid-auto-rows: clamp(100px, 27vw, 150px);
                gap: 8px;
                border-radius: 14px;
                margin-bottom: 24px;
            }

            .gallery-main {
                grid-row: auto;
            }
        }

        /* Pinakamaliliit na telepono (~360px): hindi na sabay kasya ang
           icon at ang salita sa loob ng ~48px na cell. Ang salita ang
           nananatili — ito ang hindi kailangang hulaan. */
        @media(max-width:380px) {
            .slot-pill i {
                display: none;
            }

            .slot-pill {
                font-size: 9.5px;
                padding: 2px;
                justify-content: center;
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

    {{-- Header muna, tapos gallery: dapat mabasa ng bisita kung ano ang
         property bago siya salubungin ng mga larawan. --}}
    <div class="prop-header">
        <div class="prop-type">{{ ucfirst($property->type) }}</div>
        <div class="prop-name">{{ $property->property_name }}</div>
        <div class="prop-meta">
            <span><i class="bi bi-people"></i> Up to {{ $property->max_capacity }} guests</span>
            @if ($property->floor_area)
                <span><i class="bi bi-arrows-angle-expand"></i> {{ $property->floor_area }} sqm</span>
            @endif
            <span><i class="bi bi-geo-alt"></i> Villa Elena Resort</span>
        </div>
    </div>

    <div class="gallery">
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

    <div class="detail-grid">

        {{-- Left: Property Info --}}
        <div>
            @if ($property->description)
                <div class="section-title">About This Property</div>
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
                $canBookOnline = $property->status !== 'maintenance' && $allowOnlineBooking;
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

            {{-- Availability --}}
            <div class="section-title">Availability</div>
            <p class="section-sub">
                @if ($canBookOnline)
                    Every date has two slots. Pick an open one and it fills in your booking.
                @else
                    Every date has two slots. Open slots are shown below.
                @endif
            </p>
            <div class="fc-wrap">
                <div id="availabilityCalendar"></div>
            </div>
            @php
                // Ang mga oras ay galing sa Booking::SLOTS, hindi nakasulat
                // nang paulit-ulit sa view: kung magbago ang slot, hindi
                // puwedeng magsinungaling ang legend tungkol dito.
                $legendSlots = \App\Models\Booking::SLOTS;
                $slotTime = fn($t) => \Carbon\Carbon::parse($t)->format('g:i A');
            @endphp
            <div class="cal-legend">
                <div class="legend-group">
                    <span class="legend-group-label">Slots</span>
                    @foreach ($legendSlots as $key => $def)
                        <span class="legend-slot">
                            <i class="bi {{ $key === 'night' ? 'bi-moon-stars' : 'bi-sun' }}"></i>
                            <b>{{ ucfirst($key) }}</b>
                            {{ $slotTime($def['check_in']) }} – {{ $slotTime($def['check_out']) }}{{ $def['overnight'] ? ' next day' : '' }}
                        </span>
                    @endforeach
                </div>
                <div class="legend-group">
                    <span class="legend-group-label">Availability</span>
                    <span class="legend-state"><i class="legend-swatch is-open"></i> Open</span>
                    <span class="legend-state"><i class="legend-swatch is-taken"></i> Booked</span>
                    @if ($canBookOnline)
                        <span class="legend-state"><i class="legend-swatch is-selected"></i> Your pick</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Booking Card --}}
        <div>
            <div class="booking-card" id="bookingCard">
                @if ($canBookOnline)
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
                                Reserve Now →
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
        const TODAY_STR = @json(now()->format('Y-m-d'));
        const SLOT_DEFS = @json(\App\Models\Booking::SLOTS);
        const SLOT_KEYS = Object.keys(SLOT_DEFS);
        const SLOT_SHORT = {
            day: 'Day',
            night: 'Night'
        };
        // Kaparehong icon ng nasa legend — sa telepono, ito ang kumakapit
        // sa mata bago pa mabasa ang 10px na label.
        const SLOT_ICONS = {
            day: 'bi-sun',
            night: 'bi-moon-stars'
        };
        // Petsa → { slot: booking_id } ng mga SARADONG slot. Ito lang ang
        // state ng calendar; pinapatch ito ng Pusher updates sa ibaba.
        const bookedSlots = @json($slotAvailability);
        const BOOKING_ENABLED = @json($property->status !== 'maintenance' && $allowOnlineBooking);

        function getSelectedSlot() {
            const checked = document.querySelector('input[name="slot"]:checked');
            return checked ? checked.value : null;
        }

        function showSlotNote() {
            const note = document.getElementById('durationNote');
            if (!note) return;
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

        function setBookBtn(enabled, label) {
            const bookBtn = document.getElementById('bookBtn');
            if (!bookBtn) return;
            bookBtn.disabled = !enabled;
            bookBtn.textContent = label;
        }

        function showPreviewLoading() {
            const box = document.getElementById('pricePreview');
            box.style.display = 'block';
            document.getElementById('previewTag').innerHTML = '<i class="bi bi-hourglass-split"></i> Checking…';
            document.getElementById('previewNights').textContent = 'Checking availability…';
            document.getElementById('previewBase').textContent = '—';
            document.getElementById('previewTotal').textContent = '—';
        }

        function showPreviewError(message) {
            const box = document.getElementById('pricePreview');
            box.style.display = 'block';
            document.getElementById('previewTag').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Unavailable';
            document.getElementById('previewNights').textContent = message;
            document.getElementById('previewBase').textContent = '—';
            document.getElementById('previewTotal').textContent = '—';
            setBookBtn(false, 'Not Available');
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
                    setBookBtn(true, 'Reserve Now →');
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    showPreviewError('Unable to retrieve the price. Please try again.');
                });
        }

        function updatePreview() {
            const checkinEl = document.getElementById('checkin');
            if (!checkinEl) return;

            const ci = checkinEl.value;
            const slot = getSelectedSlot();
            showSlotNote();

            if (!ci || !slot) {
                document.getElementById('pricePreview').style.display = 'none';
                // Huwag iwang naka-disable ang button kapag binura ng guest
                // ang petsa — wala nang error na ipinapakita sa kanya.
                setBookBtn(true, 'Reserve Now →');
                return;
            }

            // Debounce: maghintay ng 350ms bago mag-request, para hindi
            // sumabog ang requests habang patuloy na nag-a-adjust ang user.
            clearTimeout(previewDebounceTimer);
            previewDebounceTimer = setTimeout(() => fetchServerPreview(ci, slot), 350);
        }

        // ── Availability Calendar (FullCalendar) ────────────────────────
        // Read-only ang lumang bersyon nito: kailangang basahin ng bisita
        // ang petsa rito at manu-manong i-type sa form. Ngayon, ang mga
        // pill na mismo ang input — pagkapindot, napupunan ang booking card.
        //
        // Ang time strings mula sa backend ay pwedeng "08:00" (24-hr) o
        // "8:00 AM" (12-hr) depende sa format function na ginagamit sa
        // Model/Controller — kaya kailangan ng flexible parser dito bago
        // gawing Date object, kung hindi, magiging "Invalid Date" ito at
        // tahimik na mabibigo ang live update nang walang anumang error.
        function parseTimeToHM(timeStr) {
            if (!timeStr) return null;
            const t = String(timeStr).trim();
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
            // 24-hour format: "08:00", "14:30", "08:00:00"
            m = t.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
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

        function toDateStr(date) {
            // Hindi toISOString(): UTC iyon, kaya nagiging off-by-one ang
            // petsa sa timezone ng Pilipinas.
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${date.getFullYear()}-${m}-${d}`;
        }

        function addDays(dateStr, n) {
            const [y, mo, d] = dateStr.split('-').map(Number);
            return toDateStr(new Date(y, mo - 1, d + n));
        }

        // Window ng isang slot sa isang petsa — kaparehong kalkulasyon ng
        // Booking::slotDateTimes() sa server.
        function slotWindow(dateStr, slotKey) {
            const def = SLOT_DEFS[slotKey];
            const start = buildDate(dateStr, def.check_in);
            const end = buildDate(def.overnight ? addDays(dateStr, 1) : dateStr, def.check_out);
            return [start, end];
        }

        // ── Selection state ──────────────────────────────────────────────
        let selected = {
            date: document.getElementById('checkin')?.value || null,
            slot: getSelectedSlot()
        };

        function isTaken(dateStr, slotKey) {
            return Object.prototype.hasOwnProperty.call(bookedSlots[dateStr] || {}, slotKey);
        }

        function openSlots(dateStr) {
            return SLOT_KEYS.filter(k => !isTaken(dateStr, k));
        }

        function paintDay(dateStr, frame) {
            frame.querySelectorAll('.slot-pills').forEach(n => n.remove());
            frame.classList.toggle('is-past', dateStr < TODAY_STR);
            if (dateStr < TODAY_STR) return;

            const wrap = document.createElement('div');
            wrap.className = 'slot-pills';

            SLOT_KEYS.forEach(key => {
                const taken = isTaken(dateStr, key);
                const canPick = !taken && BOOKING_ENABLED;
                const pill = document.createElement(canPick ? 'button' : 'span');

                if (canPick) {
                    pill.type = 'button';
                    pill.dataset.date = dateStr;
                    pill.dataset.slot = key;
                }

                const isSelected = !taken && selected.date === dateStr && selected.slot === key;
                pill.className = 'slot-pill ' + (taken ? 'is-taken' : 'is-open') +
                    (isSelected ? ' is-selected' : '');

                // Icon + label. Sa pinakamaliit na screen ay tinatago ng CSS
                // ang icon, kaya hindi puwedeng ito lang ang nagsasabi ng
                // slot — ang label ang laging naroon.
                const icon = document.createElement('i');
                icon.className = 'bi ' + (SLOT_ICONS[key] || 'bi-clock');
                icon.setAttribute('aria-hidden', 'true');
                const label = document.createElement('span');
                label.className = 'slot-pill-txt';
                label.textContent = SLOT_SHORT[key] || key;
                pill.append(icon, label);

                const state = taken ? ' — already booked' : ' — available';
                pill.title = SLOT_DEFS[key].label + state;
                pill.setAttribute('aria-label', dateStr + ' ' + (SLOT_SHORT[key] || key) + state);

                wrap.appendChild(pill);
            });

            frame.appendChild(wrap);
        }

        let availCalendar = null;

        // Ang unang hilera ng isang buwan ay madalas pawang lumipas na
        // (hinaharangan ng validRange) — walang pill, walang mapipindot,
        // puro guhit-guhit na kahon lang. Itago ito para ang nakikita agad
        // ng bisita ay ang mga petsang puwede pa niyang piliin.
        function hidePastWeekRows() {
            document.querySelectorAll('#availabilityCalendar .fc-daygrid-body tr')
                .forEach(function(row) {
                    const cells = row.querySelectorAll('.fc-daygrid-day');
                    if (!cells.length) return;
                    // Ang mga cell na labas sa validRange ay WALANG
                    // data-date — kaya hindi sapat ang "lahat ay nakaraan
                    // na"; wala silang petsang maikukumpara. Ang tanong ay:
                    // may kahit isa bang araw dito na mapipili pa?
                    const hasPickable = Array.from(cells).some(function(cell) {
                        const d = cell.getAttribute('data-date');
                        return d && d >= TODAY_STR;
                    });
                    row.classList.toggle('week-all-past', !hasPickable);
                });
        }

        function repaint(dateStr) {
            if (!dateStr || !availCalendar) return;
            const frame = document.querySelector(
                `#availabilityCalendar .fc-daygrid-day[data-date="${dateStr}"] .fc-daygrid-day-frame`);
            if (frame) paintDay(dateStr, frame);
        }

        function setSelection(dateStr, slotKey) {
            const prev = selected.date;
            selected = {
                date: dateStr,
                slot: slotKey
            };
            if (prev && prev !== dateStr) repaint(prev);
            repaint(dateStr);
        }

        // Pagpili mula sa calendar — ito ang pumupuno sa booking card.
        function pickSlot(dateStr, slotKey) {
            if (!BOOKING_ENABLED) return;
            const checkinEl = document.getElementById('checkin');
            if (!checkinEl) return;

            checkinEl.value = dateStr;
            const radio = document.getElementById('slot_' + slotKey);
            if (radio) radio.checked = true;

            setSelection(dateStr, slotKey);
            updatePreview();

            const card = document.getElementById('bookingCard');
            if (!card) return;
            card.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
            card.classList.add('is-flash');
            clearTimeout(card._flashTimer);
            card._flashTimer = setTimeout(() => card.classList.remove('is-flash'), 1400);
        }

        // ── Live availability updates ────────────────────────────────────
        // When someone else books (or cancels) this property while this page
        // is open, patch the slot map in place instead of leaving it stale
        // until the visitor manually refreshes.
        function applyBlocked(data) {
            const start = buildDate(data.check_in, data.check_in_time);
            const end = buildDate(data.check_out, data.check_out_time);
            const touched = [];

            // Kaparehong saklaw ng kandidatong petsa na sinusuri ng
            // PortalController::buildSlotAvailability().
            let cursor = addDays(data.check_in, -1);
            const last = addDays(data.check_out, 1);

            while (cursor <= last) {
                const date = cursor;
                SLOT_KEYS.forEach(key => {
                    const [slotStart, slotEnd] = slotWindow(date, key);
                    if (slotStart < end && slotEnd > start) {
                        (bookedSlots[date] = bookedSlots[date] || {})[key] = data.booking_id;
                        touched.push(date);
                    }
                });
                cursor = addDays(cursor, 1);
            }

            touched.forEach(repaint);
        }

        function applyFreed(bookingId) {
            const touched = [];
            Object.keys(bookedSlots).forEach(dateStr => {
                SLOT_KEYS.forEach(key => {
                    if (bookedSlots[dateStr][key] === bookingId) {
                        delete bookedSlots[dateStr][key];
                        touched.push(dateStr);
                    }
                });
                if (!Object.keys(bookedSlots[dateStr]).length) delete bookedSlots[dateStr];
            });
            touched.forEach(repaint);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('availabilityCalendar');
            availCalendar = new FullCalendar.Calendar(el, {
                plugins: [FullCalendar.dayGridPlugin],
                initialView: 'dayGridMonth',
                // Prev/next at pamagat lang. Wala nang "today" (hindi na
                // kailangan — hindi naman puwedeng bumalik sa nakaraan dahil
                // sa validRange) at wala nang list view (dalawang slot lang
                // kada araw, wala itong maidadagdag na impormasyon).
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                validRange: {
                    start: TODAY_STR
                },
                height: 'auto',
                // Huwag pilitin ang anim na hilera: ang ikaanim ay madalas
                // pawang susunod na buwan — isang hilerang walang sinasabi,
                // at sa telepono ay isang buong screenful ng pag-scroll.
                fixedWeekCount: false,
                editable: false,
                selectable: false,
                events: [],
                dayCellDidMount: function(info) {
                    const frame = info.el.querySelector('.fc-daygrid-day-frame');
                    if (frame) paintDay(toDateStr(info.date), frame);
                },
                // Tumatakbo pagkatapos maitayo ang grid ng bawat buwan.
                datesSet: hidePastWeekRows
            });
            availCalendar.render();

            // Isang delegated listener na lang (imbes na dateClick) para
            // hindi dumoble ang pagputok kapag ang pill mismo ang pinindot.
            el.addEventListener('click', function(e) {
                const pill = e.target.closest('.slot-pill.is-open[data-date]');
                if (pill) {
                    pickSlot(pill.dataset.date, pill.dataset.slot);
                    return;
                }

                const cell = e.target.closest('.fc-daygrid-day');
                if (!cell) return;
                const dateStr = cell.getAttribute('data-date');
                if (!dateStr || dateStr < TODAY_STR) return;

                // Pagpindot kahit saan sa cell: panatilihin ang kasalukuyang
                // slot kung bukas pa ito, kung hindi, ang unang bukas.
                const open = openSlots(dateStr);
                if (!open.length) return;
                pickSlot(dateStr, open.includes(selected.slot) ? selected.slot : open[0]);
            });

            // Manu-manong pagbabago sa form ay dapat ding masalamin sa
            // calendar — dalawang view lang sila ng iisang pinili.
            document.getElementById('checkin')?.addEventListener('change', function() {
                setSelection(this.value || null, getSelectedSlot());
                updatePreview();
            });
            document.querySelectorAll('input[name="slot"]').forEach(input => {
                input.addEventListener('change', function() {
                    setSelection(document.getElementById('checkin')?.value || null, this.value);
                    updatePreview();
                });
            });

            // Run on load if dates pre-filled
            updatePreview();

            // ── Live availability sync (Pusher) ────────────────────────────
            const PUSHER_KEY = '{{ env('PUSHER_APP_KEY') }}';
            if (PUSHER_KEY && window.Pusher) {
                const pusher = new Pusher(PUSHER_KEY, {
                    cluster: '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}'
                });
                const channel = pusher.subscribe('property-availability.{{ $property->id }}');

                channel.bind('availability.changed', function(data) {
                    if (data.action === 'blocked') {
                        applyBlocked(data);
                    } else if (data.action === 'freed') {
                        applyFreed(data.booking_id);
                    }
                });
            }
        });
    </script>
@endpush
