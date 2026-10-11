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
            color: var(--gold-text);
            margin-bottom: 8px;
        }

        .prop-name {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 12px;
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

        /* ── Gallery ──
           The height is set here, never left to the photo. With an
           indefinite grid height an image falls back to its own pixel size
           (a 798×600 photo once rendered 1240×932), and every tile's image
           is absolutely positioned for the same reason: a photo can never
           size the grid.

           The layout follows the number of photos:
           one    beside the booking card, at about its real size. Across
                  the full width a small photo was enlarged 1.55× and only
                  39% of it showed.
           two    side by side at full height. The 2×2 grid left a hole
                  under the second photo.
           three  one large and two stacked.
           Every tile opens the viewer (partials/photo_viewer), which is
           the only place a photo is shown whole. */
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

        .gallery.is-pair {
            grid-template-columns: 1.6fr 1fr;
            grid-template-rows: 1fr;
        }

        /* `width: 100%` is needed: a grid item with an aspect ratio is not
           stretched, so once `max-height` applied the width shrank to match
           it and left a gap beside the photo. */
        .gallery.is-single {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
            width: 100%;
            height: auto;
            aspect-ratio: 3 / 2;
            max-height: min(560px, 70vh);
        }

        .gallery.is-empty {
            aspect-ratio: auto;
            height: 220px;
        }

        .gallery-tile {
            position: relative;
            display: block;
            width: 100%;
            height: 100%;
            padding: 0;
            border: 0;
            background: var(--sand);
            overflow: hidden;
            cursor: zoom-in;
        }

        .gallery-main {
            grid-row: 1/-1;
        }

        .gallery-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }

        .gallery-tile:hover .gallery-img {
            transform: scale(1.03);
        }

        /* The gallery clips its children, so the shared outline (drawn
           outside the button) would be cut off. This one is drawn inside,
           dark then white, so it shows on any photo. */
        .gallery-tile:focus-visible {
            outline: none;
        }

        .gallery-tile:focus-visible::after {
            content: '';
            position: absolute;
            inset: 0;
            border: 3px solid var(--stone);
            outline: 3px solid #fff;
            outline-offset: -6px;
            pointer-events: none;
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
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(0, 0, 0, .7);
            backdrop-filter: blur(4px);
            color: #fff;
            padding: 7px 14px;
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 500;
            pointer-events: none;
        }

        /* ── Layout ──
           Gallery, details and booking card are three cells of one grid,
           so the gallery can run across the top or sit in the left column.
           Below 901px everything is one column in source order. */
        .detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 901px) {
            .detail-grid {
                grid-template-columns: minmax(0, 1fr) 360px;
                column-gap: 32px;
                align-items: start;
            }

            .gallery {
                grid-column: 1 / -1;
                grid-row: 1;
            }

            .detail-info {
                grid-column: 1;
                grid-row: 2;
            }

            /* Stretched, not `start`: the card is sticky inside this box, so
               the box has to be as tall as the row for the card to have
               anywhere to travel. At its own height the card never stuck. */
            .detail-side {
                grid-column: 2;
                grid-row: 2;
                align-self: stretch;
            }

            .detail-grid.has-one-photo .gallery {
                grid-column: 1;
            }

            .detail-grid.has-one-photo .detail-side {
                grid-row: 1 / span 2;
            }
        }

        /* The rule line belongs to the section it opens, so the space above
           it is the title's own margin. It used to rely on whatever the
           block before it happened to leave: 16px under a paragraph, none
           under the amenity tiles. */
        .section-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            line-height: 1.3;
            margin: 28px 0 6px;
            padding-top: 28px;
            border-top: 1px solid var(--border);
        }

        .section-title:first-of-type {
            border-top: none;
            margin-top: 0;
            padding-top: 0;
        }

        .section-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .description {
            font-size: 15px;
            line-height: 1.7;
            margin: 0;
            max-width: 68ch;
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

        /* --gold-text, not --gold: the icon now says which amenity it is. */
        .amenity-item i {
            flex: none;
            color: var(--gold-text);
            font-size: 15px;
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
            font-family: var(--font-body);
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
            font-family: var(--font-display);
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

        /* Dark text on the gold: white on --gold-light is 1.6:1. */
        .fc .fc-button:hover,
        .fc .fc-button-primary:not(:disabled):active {
            background: var(--gold-light);
            border-color: var(--gold);
            color: var(--stone);
        }

        .fc .fc-button-primary:focus,
        .fc .fc-button-primary:not(:disabled):active:focus {
            box-shadow: 0 0 0 3px rgba(44, 36, 22, .18);
        }

        .fc .fc-button-primary:disabled {
            background: var(--sand);
            border-color: var(--border);
            color: var(--muted);
            opacity: .5;
        }

        .fc .fc-daygrid-day.fc-day-today {
            background: color-mix(in srgb, var(--gold) 10%, transparent);
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
            font-family: var(--font-body);
            font-size: 12px;
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
            color: var(--stone);
        }

        .slot-pill.is-taken,
        .legend-swatch.is-taken {
            background: var(--tag-red-bg);
            border-color: color-mix(in srgb, var(--tag-red-fg) 20%, var(--tag-red-bg));
            color: var(--tag-red-fg);
        }

        .slot-pill.is-taken .slot-pill-txt {
            text-decoration: line-through;
            text-decoration-thickness: 1px;
        }

        /* Ipinasara ng resort ang petsa — IBA sa "Booked". Walang kumuha
           nito, kaya walang pula at walang guhit: kulay-abo at putol-putol
           na gilid, para hindi mabasa ng bisita na may nakauna sa kanya. */
        .slot-pill.is-closed,
        .legend-swatch.is-closed {
            background: var(--tag-slate-bg);
            border-color: var(--border-strong);
            border-style: dashed;
            color: var(--tag-slate-fg);
        }

        /* "Not open" ay DALAWANG maikling salita at pinapayagang bumaba sa
           ikalawang linya, sa lahat ng lapad. Sinukat: ang "Not available"
           ay 75px at napuputol sa "Not availab" sa 52px na cell ng
           telepono, at kahit ang "Closed" (40px) ay sobra ng 1px doon. Ang
           bawat salita rito ay mas maikli pa sa "Night", kaya kasya ito
           saanman kasya ang Night — walang breakpoint na kailangang hulaan. */
        .slot-pill.is-closed .slot-pill-txt {
            white-space: normal;
            line-height: 1.25;
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
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
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
            /* --card-top is set by placeBookingCard() below; the fallback is
               the plain "under the nav" position. */
            top: var(--card-top, calc(var(--nav-h) + 20px));
            box-shadow: 0 8px 32px rgba(44, 36, 22, .08);
            transition: box-shadow .3s, border-color .3s;
        }

        /* Kapag may pinili sa calendar, kailangang makita ng bisita na may
           nangyari sa card — lalo na sa mobile kung saan hindi ito sticky
           at nasa ibaba pa ng calendar. */
        .booking-card.is-flash {
            border-color: var(--gold);
            box-shadow: 0 8px 32px color-mix(in srgb, var(--gold) 40%, transparent);
        }

        .booking-price {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .booking-price span {
            font-size: 15px;
            font-weight: 400;
            color: var(--muted);
            font-family: var(--font-body);
        }

        /* The rates behind the "From" figure. A real table: two prices for
           each kind of stay is tabular data, and a screen reader then reads
           "Peak, ₱6,000" instead of a row of bare numbers. */
        .rate-table {
            width: 100%;
            margin: 10px 0 8px;
            border-collapse: collapse;
            font-size: 13px;
        }

        .rate-table th,
        .rate-table td {
            padding: 7px 0 7px 12px;
            text-align: right;
            vertical-align: top;
        }

        .rate-table th:first-child,
        .rate-table td:first-child {
            padding-left: 0;
            text-align: left;
        }

        .rate-table thead th {
            padding-top: 0;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
        }

        .rate-table tbody th {
            font-weight: 600;
            color: var(--stone);
        }

        .rate-table tbody tr {
            border-top: 1px solid var(--border);
        }

        .rate-table td {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .rate-note {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: var(--muted);
        }

        .rate-foot {
            font-size: 13px;
            line-height: 1.5;
            color: var(--muted);
            margin: 0 0 18px;
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
            color: var(--tag-green-fg);
            font-weight: 600;
        }

        .price-deposit {
            font-size: 14px;
            color: var(--terracotta);
            text-align: center;
            margin-top: 8px;
        }

        .btn-book-now {
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--font-body);
            width: 100%;
            cursor: pointer;
            transition: all .2s;
            margin-top: 8px;
        }

        a.btn-book-link {
            display: block;
            text-align: center;
            text-decoration: none;
        }

        .btn-book-now:hover {
            background: var(--btn-primary-hover);
            color: #fff;
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
            color: var(--gold-text);
            font-weight: 600;
            text-decoration: none;
        }

        .unavail-banner {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
        }

        .unavail-banner a {
            color: inherit;
            font-weight: 600;
        }

        .is-invalid {
            border-color: var(--tag-red-fg) !important;
        }

        .invalid-feedback {
            font-size: 14px;
            color: var(--tag-red-fg);
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
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .duration-note.warn {
            display: block;
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
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
            background: color-mix(in srgb, var(--gold) 8%, transparent);
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

            /* Two photos share one row here, so the row needs a height of
               its own: `height: auto` above leaves `1fr` with nothing to
               divide. */
            .gallery.is-pair {
                grid-template-rows: min(clamp(190px, 40vw, 320px), 50vh);
            }

            .gallery-main {
                grid-row: 1/-1;
            }

            .detail-side {
                margin-top: 32px;
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
                font-size: 12px;
                letter-spacing: 0;
                padding: 6px 2px;
            }

            .fc .fc-daygrid-day-number {
                font-size: 12px;
                padding: 4px 5px 2px;
            }

            .fc .fc-daygrid-day-frame {
                min-height: 58px;
            }

            .slot-pills {
                padding: 0 3px 4px;
                gap: 2px;
            }

            /* Ang pill ang pinipindot para pumili ng petsa at slot, kaya may
               pinakamababang taas ito sa telepono: 22px lang dati, masyadong
               maliit para sa daliri. */
            .slot-pill {
                font-size: 12px;
                padding: 2px 3px;
                gap: 3px;
                letter-spacing: 0;
                min-height: 30px;
                justify-content: center;
            }

            /* "22 Hours" ay mas malapad sa ~40px na cell at napuputol sa
               "22 Hc". Hinahayaan itong bumaba sa pangalawang linya: sa
               petsang 22-oras ay ito lang ang pill, kaya may lugar. Ang
               "Day" at "Night" ay iisang salita at hindi apektado. */
            .slot-pill-txt {
                white-space: normal;
                text-align: center;
                line-height: 1.2;
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

            /* The main photo takes the first row across both columns; the
               other two share the row under it. One column gave each of
               them a 343×105 strip, which showed 23% of a portrait photo.
               Only the first row is explicit, so the second appears only
               when there is a photo to put in it. */
            .gallery {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: clamp(180px, 50vw, 260px);
                grid-auto-rows: clamp(120px, 36vw, 170px);
                gap: 8px;
                border-radius: 14px;
                margin-bottom: 24px;
            }

            .gallery-main {
                grid-column: 1 / -1;
                grid-row: auto;
            }

            /* Two photos stack at the same height: neither is a thumbnail. */
            .gallery.is-pair {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(2, clamp(170px, 50vw, 260px));
            }

            .gallery-count {
                bottom: 10px;
                right: 10px;
            }
        }

        /* Telepono: hindi na sabay kasya ang icon at ang 12px na salita sa
           loob ng ~45–55px na cell ("Night" ay napuputol sa "Nigh" sa
           390px). Ang salita ang nananatili — ito ang hindi kailangang
           hulaan. */
        @media(max-width:480px) {
            .slot-pill i {
                display: none;
            }

            .slot-pill {
                padding: 2px;
            }
        }

        /* Daliri, hindi mouse: mas mataas na pill at mas malaking pindutan
           ng buwan, anuman ang lapad ng screen (kasama ang tablet). */
        @media (pointer: coarse) {
            .slot-pill {
                min-height: 30px;
            }

            .fc .fc-button {
                min-width: 44px;
                min-height: 40px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a>
        <span>›</span>
        <a href="{{ route('home') }}#properties">The Villa</a>
        <span>›</span>
        <span aria-current="page">{{ $property->property_name }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>{{ $errors->first() }}
        </div>
    @endif

    {{-- Header muna, tapos gallery: dapat mabasa ng bisita kung ano ang
         property bago siya salubungin ng mga larawan. --}}
    <div class="prop-header">
        <h1 class="prop-name">{{ $property->property_name }}</h1>
        <div class="prop-meta">
            <span><i class="bi bi-people" aria-hidden="true"></i> Up to {{ $property->max_capacity }} guests</span>
            @if ($property->floor_area)
                <span><i class="bi bi-arrows-angle-expand" aria-hidden="true"></i> {{ $property->floor_area }} sqm</span>
            @endif
            <span><i class="bi bi-geo-alt" aria-hidden="true"></i> Villa Elena Resort</span>
        </div>
    </div>

    @php
        // The primary photo first, then the rest in display order. This one
        // list feeds the tiles and the viewer, so a tile's number is always
        // the photo the viewer opens on.
        $photos = $property->images->sortByDesc('is_primary')->values();
        $photoCount = $photos->count();
        $viewerPhotos = $photos
            ->map(fn ($photo) => ['src' => $photo->url, 'alt' => $photo->alt_text ?: $property->property_name])
            ->all();
    @endphp

    <div class="detail-grid{{ $photoCount <= 1 ? ' has-one-photo' : '' }}">

        <div class="gallery{{ $photoCount <= 1 ? ' is-single' : '' }}{{ $photoCount === 0 ? ' is-empty' : '' }}{{ $photoCount === 2 ? ' is-pair' : '' }}">
            @forelse ($photos->take(3) as $i => $photo)
                <button type="button" class="gallery-tile{{ $i === 0 ? ' gallery-main' : '' }}"
                    data-photo-index="{{ $i }}"
                    aria-label="{{ $photoCount > 1 ? 'View photo ' . ($i + 1) . ' of ' . $photoCount . ' full size' : 'View the photo full size' }}">
                    <img src="{{ $photo->url }}" class="gallery-img" alt=""
                        @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @if ($i === 0)
                        <span class="gallery-count">
                            <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                            {{ $photoCount > 1 ? 'View all ' . $photoCount . ' photos' : 'View full photo' }}
                        </span>
                    @endif
                </button>
            @empty
                <div class="gallery-placeholder"><i class="bi bi-house" aria-hidden="true"></i></div>
            @endforelse
        </div>

        {{-- Left: Property Info --}}
        <div class="detail-info">
            @if ($property->description)
                <h2 class="section-title">About the Villa</h2>
                <p class="description">{{ $property->description }}</p>
            @endif

            {{-- Amenities --}}
            @php
                $amenities = is_array($property->amenities)
                    ? $property->amenities
                    : json_decode($property->amenities ?? '[]', true);
                $canBookOnline = $property->status !== 'maintenance' && $allowOnlineBooking;
            @endphp
            @if (count($amenities ?? []))
                <h2 class="section-title">Amenities</h2>
                <div class="amenities-grid">
                    @foreach ($amenities as $amenity)
                        <div class="amenity-item">
                            <i class="bi {{ \App\Models\Property::amenityIcon($amenity) }}" aria-hidden="true"></i>
                            {{ ucwords(str_replace('_', ' ', $amenity)) }}
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Availability --}}
            @php
                // Iisang listahan para sa pabalat na teksto, sa legend at sa
                // calendar JS sa ibaba — kung tatlo ang pinagkukunan, may isa
                // sa kanilang magsisinungaling. Ang mga slot na wala pang
                // presyo ay wala rito (Property::isSlotPriced()).
                $calSlots = \App\Models\Booking::bookableSlotKeys($property);

                // What the sentence above the calendar may claim. A date
                // never offers every slot: it has the everyday ones, or one
                // long stay the owner opened on it. Counting the slots said
                // "Every date has 4 slots". The long stays are named only
                // when a date for them is actually coming up.
                $windowedSlots = \App\Models\Booking::windowedSlotKeys();
                $slotWindowDates = \App\Models\Booking::slotWindowDates($property);
                $slotName = fn ($k) => \App\Models\Booking::SLOTS[$k]['name'];
                $everydaySlotNames = collect($calSlots)
                    ->reject(fn ($k) => in_array($k, $windowedSlots, true))
                    ->map($slotName)
                    ->join(', ', ' and ');
                $longStayNames = collect($slotWindowDates)
                    ->filter(fn ($dates) => count($dates) > 0)
                    ->keys()
                    ->map($slotName)
                    ->join(', ', ' or ');
            @endphp
            <h2 class="section-title">Availability</h2>
            <p class="section-sub">
                @if ($everydaySlotNames !== '')
                    Each date offers {{ $everydaySlotNames }}.
                @endif
                @if ($longStayNames !== '')
                    Some dates are offered as one long stay instead: {{ $longStayNames }}.
                @endif
                @if ($canBookOnline)
                    Pick an open slot and it fills in your booking.
                @else
                    Open slots are shown below.
                @endif
            </p>
            <div class="fc-wrap">
                <div id="availabilityCalendar"></div>
            </div>
            @php
                // Ang mga oras ay galing sa Booking::SLOTS, hindi nakasulat
                // nang paulit-ulit sa view: kung magbago ang slot, hindi
                // puwedeng magsinungaling ang legend tungkol dito.
                $legendSlots = collect($calSlots)
                    ->mapWithKeys(fn ($k) => [$k => \App\Models\Booking::SLOTS[$k]])
                    ->all();
                $slotTime = fn($t) => \Carbon\Carbon::parse($t)->format('g:i A');
            @endphp
            <div class="cal-legend">
                <div class="legend-group">
                    <span class="legend-group-label">Slots</span>
                    @foreach ($legendSlots as $key => $def)
                        <span class="legend-slot">
                            {{-- Buwan para sa anumang tumatawid ng hatinggabi, hindi
                                 `$key === 'night'` — dalawa na ang overnight na slot. --}}
                            <i class="bi {{ $def['overnight'] ? 'bi-moon-stars' : 'bi-sun' }}"></i>
                            <b>{{ $def['name'] }}</b>
                            {{ $slotTime($def['check_in']) }} – {{ $slotTime($def['check_out']) }}{{ $def['overnight'] ? ' next day' : '' }}
                        </span>
                    @endforeach
                </div>
                <div class="legend-group">
                    <span class="legend-group-label">Availability</span>
                    <span class="legend-state"><i class="legend-swatch is-open"></i> Open</span>
                    <span class="legend-state"><i class="legend-swatch is-taken"></i> Booked</span>
                    {{-- Ipinapakita lang kapag may naka-block na petsa: kung
                         wala, isa itong susi para sa isang bagay na hindi
                         makikita ng bisita sa calendar. --}}
                    @if (! empty($blockedSlots))
                        <span class="legend-state"><i class="legend-swatch is-closed"></i> Not open</span>
                    @endif
                    @if ($canBookOnline)
                        <span class="legend-state"><i class="legend-swatch is-selected"></i> Your pick</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Booking Card --}}
        <div class="detail-side">
            <div class="booking-card" id="bookingCard">
                @if ($canBookOnline)
                    {{-- The lowest list price, then every rate behind it. The
                         card used to print `base_price` alone, so a guest
                         first met the peak and 22-hour rates in the preview. --}}
                    @php
                        $rateGroups = $property->rateGroups();
                        $lowestRate = collect($rateGroups)->min('regular');
                        $hasPeak = collect($rateGroups)->contains(fn ($g) => $g['peak'] != $g['regular']);
                    @endphp
                    <div class="booking-price">
                        @if (count($rateGroups) > 1 || $hasPeak)<span>From</span>@endif
                        ₱{{ number_format($lowestRate ?? $property->base_price, 0) }} <span>/ package</span>
                    </div>
                    @if (count($rateGroups) > 1 || $hasPeak)
                        <table class="rate-table">
                            <caption class="visually-hidden">Package rates</caption>
                            <thead>
                                <tr>
                                    <th scope="col"><span class="visually-hidden">Stay</span></th>
                                    <th scope="col">{{ $hasPeak ? 'Regular' : 'Rate' }}</th>
                                    @if ($hasPeak)
                                        <th scope="col">Peak</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rateGroups as $group)
                                    <tr>
                                        <th scope="row">
                                            {{ collect($group['names'])->join(', ', ' or ') }}
                                            @if ($group['selected_dates'])
                                                <span class="rate-note">Selected dates only</span>
                                            @endif
                                        </th>
                                        <td>₱{{ number_format($group['regular'], 0) }}</td>
                                        @if ($hasPeak)
                                            <td>₱{{ number_format($group['peak'], 0) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($hasPeak)
                            <p class="rate-foot">
                                Peak applies when your stay starts on Friday or Saturday, or on Sunday before 6:00 PM.
                            </p>
                        @endif
                    @endif

                    <form method="GET" action="{{ route('portal.book', $property) }}" id="bookingForm">
                        <div class="mb-12">
                            <label for="checkin" class="form-label">Check-in Date</label>
                            <input type="date" name="checkin" id="checkin" class="form-control"
                                value="{{ $checkin }}" min="{{ date('Y-m-d') }}"
                                max="{{ \App\Models\Booking::latestBookableDate()->toDateString() }}" required>
                        </div>
                        <div class="mb-12">
                            <label class="form-label">Choose Your Slot</label>
                            {{-- Iginuguhit ang LAHAT ng slot na may presyo, at ang JS
                                 ang nagtatago ng hindi inaalok sa piniling PETSA
                                 (`refreshSlotOptions()` sa ibaba). Hindi per-date ang
                                 markup dahil nagbabago ang petsa nang walang reload —
                                 pero ang naunang pagpili sa server ang nagtatakda ng
                                 unang estado, kaya tama na ito kahit patay ang JS. --}}
                            @php
                                $bookableSlots = \App\Models\Booking::bookableSlotKeys($property);
                                $offeredNow    = $checkin
                                    ? \App\Models\Booking::slotsOfferedOn($checkin, $property)
                                    : $bookableSlots;
                                $selectedSlot  = in_array($slot ?? null, $offeredNow, true)
                                    ? $slot
                                    : ($offeredNow[0] ?? null);
                            @endphp
                            <div class="slot-options">
                                @foreach ($bookableSlots as $slotKey)
                                    @php $def = \App\Models\Booking::SLOTS[$slotKey]; @endphp
                                    <label class="slot-option" data-slot-option="{{ $slotKey }}"
                                        @if (! in_array($slotKey, $offeredNow, true)) hidden @endif>
                                        <input type="radio" name="slot" value="{{ $slotKey }}" id="slot_{{ $slotKey }}"
                                            {{ $selectedSlot === $slotKey ? 'checked' : '' }}>
                                        <span class="slot-option-label">
                                            <strong>{{ $def['name'] }}</strong>
                                            <small>{{ $def['times'] }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            {{-- Ipinapaliwanag kung bakit nawala ang Day/Night sa isang
                                 22-oras na petsa. Kung wala ito, mukhang sira ang form. --}}
                            <div id="slotOnlyNote" class="duration-note" hidden></div>
                            <div id="durationNote" class="duration-note"></div>
                        </div>
                        <div class="mb-3">
                            <label for="f_guests" class="form-label">Guests</label>
                            <select id="f_guests" name="guests" class="form-select">
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
                            {{-- Admin at staff: walang Reserve button. Ang
                                 PortalController ang tunay na harang; ito ay
                                 para hindi sila alukin ng hindi nila magagamit. --}}
                            @if ($staffBookingTool = auth()->user()->bookingToolRouteName())
                                <a href="{{ route($staffBookingTool) }}" class="btn-book-now btn-book-link">
                                    {{ auth()->user()->bookingToolLabel() }}
                                </a>
                                <div class="login-prompt">
                                    Guest bookings can only be made from a guest account.
                                </div>
                            @else
                                <button type="submit" class="btn-book-now" id="bookBtn">
                                    Reserve Now →
                                </button>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn-book-now btn-book-link">
                                Sign In to Book
                            </a>
                            <div class="login-prompt">
                                Don't have an account?
                                <a href="{{ route('register') }}">Create one free →</a>
                            </div>
                        @endauth
                    </form>
                @elseif ($property->status === 'maintenance')
                    <div class="unavail-banner">
                        <i class="bi bi-x-circle me-2" aria-hidden="true"></i>
                        The villa is currently unavailable.<br>
                        <a href="{{ route('home') }}">Back to home</a>
                    </div>
                @else
                    <div class="unavail-banner">
                        <i class="bi bi-telephone me-2" aria-hidden="true"></i>
                        Online booking is temporarily unavailable.<br>
                        Please contact us directly to reserve your stay.
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    @include('partials.photo_viewer', ['photos' => $viewerPhotos])
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        const PRICE_PREVIEW_URL = "{{ route('portal.price-preview', $property) }}";
        const TODAY_STR = @json(now()->format('Y-m-d'));
        // Ang mga BOOKABLE na slot lang — kapareho ng listahang pinagbatayan
        // ng legend at ng radio cards sa itaas. Ang isang slot na wala pang
        // presyo ay nasa Booking::SLOTS pero hindi dapat mag-anyong pill na
        // mapipindot dito.
        const SLOT_DEFS = @json($legendSlots);
        const SLOT_KEYS = Object.keys(SLOT_DEFS);
        const SLOT_SHORT = Object.fromEntries(
            SLOT_KEYS.map(k => [k, SLOT_DEFS[k].name])
        );
        // Kaparehong icon ng nasa legend — sa telepono, ito ang kumakapit
        // sa mata bago pa mabasa ang 10px na label.
        const SLOT_ICONS = Object.fromEntries(
            SLOT_KEYS.map(k => [k, SLOT_DEFS[k].overnight ? 'bi-moon-stars' : 'bi-sun'])
        );

        // ── Aling slot ang inaalok sa isang PETSA ─────────────────────────
        //
        // Ito ang katumbas sa kliyente ng Booking::slotsOfferedOn(), at
        // kailangang manatiling EKSAKTONG pareho ang panuntunan: window muna
        // at EKSKLUSIBO, at kung wala, ang mga hindi-windowed na slot. Kapag
        // naghiwalay sila, may mapipiling slot sa form na tatanggihan ng
        // server (o mas masahol: may nakatagong slot na bukas naman).
        //
        // Walang presyo = wala sa listahan: pareho nang nasala ng server ang
        // dalawang payload na ito bago ipadala.
        const WINDOWED_SLOTS = @json($windowedSlots);
        const SLOT_WINDOW_DATES = @json($slotWindowDates);

        function slotsOfferedOn(dateStr) {
            for (const s of WINDOWED_SLOTS) {
                if ((SLOT_WINDOW_DATES[s] || []).includes(dateStr)) return [s];
            }
            return SLOT_KEYS.filter(k => !WINDOWED_SLOTS.includes(k));
        }

        // Itinatago ang mga radio na hindi inaalok sa piniling petsa, at
        // inililipat ang pinili kung ito ang nawala.
        function refreshSlotOptions() {
            const dateStr = document.getElementById('checkin')?.value;
            if (!dateStr) return;

            const offered = slotsOfferedOn(dateStr);
            let checkedStillOffered = false;

            document.querySelectorAll('[data-slot-option]').forEach(label => {
                const key = label.dataset.slotOption;
                const on = offered.includes(key);
                label.hidden = !on;
                const input = label.querySelector('input[name="slot"]');
                if (input) {
                    input.disabled = !on;
                    if (input.checked && on) checkedStillOffered = true;
                    if (input.checked && !on) input.checked = false;
                }
            });

            if (!checkedStillOffered && offered.length) {
                const first = document.getElementById('slot_' + offered[0]);
                if (first) first.checked = true;
            }

            // Sabihin kung bakit iisa lang ang pagpipilian.
            const note = document.getElementById('slotOnlyNote');
            if (note) {
                const only = offered.length === 1 && WINDOWED_SLOTS.includes(offered[0]);
                note.hidden = !only;
                if (only) {
                    note.textContent = 'This date is offered as the ' +
                        (SLOT_DEFS[offered[0]]?.name || offered[0]) +
                        ' stay only — the Day and Night slots are not available on it.';
                }
            }
        }
        // Petsa → { slot: booking_id } ng mga SARADONG slot. Ito lang ang
        // state ng calendar; pinapatch ito ng Pusher updates sa ibaba.
        const bookedSlots = @json($slotAvailability);
        // Petsa → [slot] na ipinasara ng admin (Booking::blockedSlotMap).
        // Hiwalay sa bookedSlots at sinasadya: hindi ito pinapatch ng
        // Pusher updates (walang booking id ang block), at hindi "already
        // booked" ang dapat sabihin tungkol dito. Object ang pilit na hugis
        // — ang walang-lamang PHP array ay nagiging `[]` sa JSON.
        const blockedSlots = @json((object) ($blockedSlots ?? []));
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
            // Mula sa SLOT_DEFS, hindi `slot === 'day' ? … : …` — ang
            // ternary na iyon ay nagsasabing "Night slot, check-out 6:00 AM"
            // sa bisitang pumili ng 22 oras.
            const def = SLOT_DEFS[slot];
            if (!def) {
                note.className = 'duration-note';
                note.textContent = '';
                return;
            }
            const [checkIn, checkOut] = def.times.split(' – ');
            note.className = 'duration-note ok';
            note.innerHTML = '<i class="bi bi-check-circle me-1"></i>' + def.name +
                ' — check-in ' + checkIn + ', check-out ' + checkOut + '.';
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

        function isBlocked(dateStr, slotKey) {
            return (blockedSlots[dateStr] || []).includes(slotKey);
        }

        function openSlots(dateStr) {
            // Ang inaalok sa petsang iyon, bawas ang mga nakuha na at ang
            // mga ipinasara ng resort.
            return slotsOfferedOn(dateStr).filter(k => !isTaken(dateStr, k) && !isBlocked(dateStr, k));
        }

        function paintDay(dateStr, frame) {
            frame.querySelectorAll('.slot-pills').forEach(n => n.remove());
            frame.classList.toggle('is-past', dateStr < TODAY_STR);
            if (dateStr < TODAY_STR) return;

            const wrap = document.createElement('div');
            wrap.className = 'slot-pills';

            const offered = slotsOfferedOn(dateStr);

            // Sarado ng resort ang LAHAT ng inaalok sa petsang ito: iisang
            // "Not open" sa halip na dalawang pill na parehong sarado.
            // Dati ay "Open" ang mga ito at ang pagpindot lang ang
            // nagsasabing hindi puwede.
            if (offered.length && offered.every(k => isBlocked(dateStr, k))) {
                const closed = document.createElement('span');
                closed.className = 'slot-pill is-closed';
                const icon = document.createElement('i');
                icon.className = 'bi bi-slash-circle';
                icon.setAttribute('aria-hidden', 'true');
                const label = document.createElement('span');
                label.className = 'slot-pill-txt';
                label.textContent = 'Not open';
                closed.append(icon, label);
                closed.title = 'Not open for booking on this date';
                closed.setAttribute('aria-label', dateStr + ' — not open for booking');
                wrap.appendChild(closed);
                frame.appendChild(wrap);
                return;
            }

            // Ang mga slot na INAALOK sa petsang ito, hindi lahat ng slot: sa
            // isang 22-oras na petsa ay isang pill lang, at sa ordinaryong
            // petsa ay hindi lumilitaw ang 22-oras.
            offered.forEach(key => {
                const blocked = isBlocked(dateStr, key);
                const taken = blocked || isTaken(dateStr, key);
                const canPick = !taken && BOOKING_ENABLED;
                const pill = document.createElement(canPick ? 'button' : 'span');

                if (canPick) {
                    pill.type = 'button';
                    pill.dataset.date = dateStr;
                    pill.dataset.slot = key;
                }

                const isSelected = !taken && selected.date === dateStr && selected.slot === key;
                pill.className = 'slot-pill ' + (blocked ? 'is-closed' : (taken ? 'is-taken' : 'is-open')) +
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

                const state = blocked ? ' — not open for booking' : (taken ? ' — already booked' : ' — available');
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

            // Ipinapakita muna ang tamang mga radio para sa bagong petsa —
            // kung hindi, ang pagpindot sa 22-oras na pill ay nagtatakda ng
            // radio na nakatago pa (o naka-disable) at bumabalik ang form sa
            // Day sa sandaling tumakbo ang refresh.
            refreshSlotOptions();

            const radio = document.getElementById('slot_' + slotKey);
            if (radio) radio.checked = true;

            setSelection(dateStr, slotKey);
            updatePreview();

            const card = document.getElementById('bookingCard');
            if (!card) return;
            // On a phone the card is below the calendar and has to be brought
            // into view. On desktop it is sticky and already there; scrolling
            // to it would only nudge the page on every pick.
            if (getComputedStyle(card).position !== 'sticky') {
                card.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }
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
            // ── Booking card that stays in view (desktop) ──────────────────
            // A card that fits under the nav sticks there. A card taller than
            // the window cannot: stuck by its top, its lower half (the total
            // and the Reserve button) would stay below the screen until the
            // page ended. So its bottom is pinned instead, and the top slides
            // under the nav. The card grows and shrinks (rates, price
            // preview, slot note), hence the observer. First in this handler
            // on purpose: it must not depend on the calendar below loading.
            const bookingCard = document.getElementById('bookingCard');
            if (bookingCard && 'ResizeObserver' in window) {
                const navH = parseFloat(getComputedStyle(document.documentElement)
                    .getPropertyValue('--nav-h')) || 72;

                const placeBookingCard = function() {
                    const cardH = bookingCard.offsetHeight;
                    let top = navH + 20;

                    if (cardH > window.innerHeight - navH - 40) {
                        // Clear of the chat button when the two share a
                        // column, so it never sits on the Reserve button.
                        let gap = 20;
                        const chat = document.getElementById('chat-bubble');
                        if (chat) {
                            const c = chat.getBoundingClientRect();
                            const b = bookingCard.getBoundingClientRect();
                            if (c.width && c.left < b.right && c.right > b.left) {
                                gap = window.innerHeight - c.top + 12;
                            }
                        }
                        top = window.innerHeight - cardH - gap;
                    }

                    bookingCard.style.setProperty('--card-top', top + 'px');
                };

                new ResizeObserver(placeBookingCard).observe(bookingCard);
                window.addEventListener('resize', placeBookingCard);
                placeBookingCard();
            }

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
                // Muna ang mga pagpipilian: baka hindi na inaalok ang naka-check
                // na slot sa bagong petsa, at ang setSelection() ay dapat makita
                // ang pinili PAGKATAPOS ilipat.
                refreshSlotOptions();
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
            refreshSlotOptions();
            updatePreview();

            // ── Live availability sync (Pusher) ────────────────────────────
            const PUSHER_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
            if (PUSHER_KEY && window.Pusher) {
                const pusher = new Pusher(PUSHER_KEY, {
                    cluster: '{{ config('broadcasting.connections.pusher.options.cluster', 'ap1') }}'
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
