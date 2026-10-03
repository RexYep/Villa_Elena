@extends('layouts.admin')

@section('title', 'Calendar — Villa Elena Admin')
@section('page-title', 'Calendar')
@section('page-subtitle', 'Bookings, availability & blocked dates')

@section('topbar-right')
    <button class="btn-navy" onclick="openBlockModal()">
        <i class="bi bi-calendar-x"></i> Block Dates
    </button>
    {{-- Ipinapakita lang kapag may presyo na ang 22-oras na slot: kung wala,
         ang window ay walang bisa (tingnan ang Booking::slotsOfferedOn()) at
         ang pindutan ay nangangako ng isang bagay na hindi mangyayari. --}}
    @if (in_array('stay22', \App\Models\Booking::bookableSlotKeys($villa), true))
        <button class="btn-navy" style="background:#0f766e;" onclick="openSlotWindowModal()">
            <i class="bi bi-house-door"></i> 22-Hour Date
        </button>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
@endsection

@push('styles')
    {{-- FullCalendar --}}
    @vite(['resources/js/admin-calendar.js'])
    <style>
        .btn-navy {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .btn-navy:hover {
            background: #b55a31;
            color: #fff;
        }

        /* LEGEND */
        .legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 18px;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: var(--muted);
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* FILTER BAR */
        .filter-bar {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 18px;
        }

        .filter-bar>div {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-bar label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .filter-bar select {
            border: 1.5px solid var(--border);
            background: white;
            color: var(--stone);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            min-width: 180px;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        /* CALENDAR */
        .calendar-wrap {
            background: var(--cream);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 22px;
        }

        /* FULLCALENDAR */
        .fc {
            font-family: 'DM Sans', sans-serif;
        }

        .fc .fc-toolbar-title {
            font-family: 'Cormorant Garamond', serif;
            color: var(--stone);
            font-size: 26px;
        }

        .fc .fc-button {
            background: var(--terracotta) !important;
            border-color: var(--terracotta) !important;
            border-radius: 9px !important;
        }

        .fc .fc-button:hover {
            background: var(--gold) !important;
            border-color: var(--gold) !important;
            color: #fff !important;
        }

        .fc .fc-button-active {
            background: var(--gold) !important;
            border-color: var(--gold) !important;
        }

        .fc .fc-col-header-cell {
            background: #faf7f2;
            color: var(--muted);
        }

        .fc .fc-daygrid-day-number {
            color: var(--stone);
        }

        .fc .fc-daygrid-day.fc-day-today {
            background: #fdf5e6;
        }

        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: var(--border);
        }

        .fc .fc-scrollgrid {
            border-color: var(--border);
        }

        /* MODALS (page-specific: rounder corners, sticky head, own max-width) */
        .modal-overlay {
            background: rgba(44, 36, 22, .55);
            z-index: 2000;
            padding: 20px;
        }

        .modal-box {
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        }

        .modal-head {
            border-radius: 20px 20px 0 0;
            position: sticky;
            top: 0;
        }

        .modal-title {
            font-size: 20px;
        }

        .modal-close {
            background: rgba(255, 255, 255, .15);
            width: 30px;
            height: 30px;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, .3);
        }

        .modal-body {
            padding: 20px 22px 24px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
        }

        .detail-row:last-of-type {
            border-bottom: none;
        }

        .detail-label {
            color: var(--muted);
            font-weight: 500;
        }

        .detail-val {
            color: var(--stone);
            font-weight: 600;
            text-align: right;
        }

        .two-btn {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .mb-12 {
            margin-bottom: 12px;
        }

        /* Was an inline grid-template-columns:1fr 1fr on the element itself, which
           no media query could have overridden. 1fr is minmax(auto, 1fr), so each
           track's floor is the content's min-content — and a native date input
           refuses to render below 144px. Two of them plus the gap need 300px, but
           the modal body is only 236px at a 320px viewport, so the End Date field
           sat 22px past the right edge, clipped away by body{overflow-x:hidden}
           with no scrollbar to recover it. */
        .date-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 420px) {
            .date-pair {
                grid-template-columns: 1fr;
            }
        }

        .form-label-sm {
            display: block;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .form-control-sm2 {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 13px;
            width: 100%;
            background: #fff;
            color: var(--stone);
        }

        .form-control-sm2:focus {
            border-color: var(--terracotta);
            outline: none;
        }

        .btn-submit {
            border-radius: 10px;
            padding: 11px 20px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-view {
            background: #f9f2e8;
            color: var(--terracotta);
            border: 1px solid #edd8bf;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
            transition: .2s;
        }

        .btn-view:hover {
            background: #edd8bf;
            color: var(--terracotta);
        }

        .btn-danger-sm {
            background: #fff2f2;
            border: 1px solid #f5c7c7;
            color: #dc2626;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
        }

        .btn-danger-sm:hover {
            background: #dc2626;
            color: #fff;
        }

        /* STATUS PILL */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .s-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .s-confirmed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .s-checked_in {
            background: #d1fae5;
            color: #047857;
        }

        .s-checked_out {
            background: #e5e7eb;
            color: #374151;
        }

        .s-no_show {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* TOAST */
        .toast-msg {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--terracotta);
            color: #fff;
            padding: 14px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 500;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            z-index: 3000;
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
        }

        .toast-msg.show {
            opacity: 1;
            transform: translateY(0);
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

        @media (max-width: 600px) {
            .calendar-wrap {
                padding: 12px;
            }

            /* Full-width rather than a min-width floor: the groups already stack
               here, so a 140px control just leaves dead space beside itself. */
            .filter-bar {
                gap: 12px;
                padding: 14px;
            }

            /* A basis, not 100%: the two groups still sit side by side wherever
               they fit (they do from ~420px up) and only stack when they don't.
               Forcing 100% made the bar taller at 480-600px than it was before. */
            .filter-bar>div {
                flex: 1 1 180px;
            }

            .filter-bar select {
                min-width: 0;
                width: 100%;
            }

            /* "Drag bookings to reschedule" is advice for a mouse, and below this
               width the calendar is in list view, where there is nothing to drag. */
            .filter-bar>.text-muted-theme {
                display: none;
            }

            .legend {
                gap: 10px 14px;
                padding: 10px 12px;
            }

            /* 26px Cormorant pushes the wrapped toolbar to three tall rows. */
            .fc .fc-toolbar-title {
                font-size: 20px;
            }
        }

        /* The "Block Dates" button label wraps to two lines at 360px and below,
           making the button 57px tall inside a topbar that admin.css fixes at
           68px. Same icon-only treatment as the properties detail page. */
        @media (max-width: 560px) {
            .topbar-right .btn-navy {
                font-size: 0;
                padding: 9px 12px;
                gap: 0;
            }

            .topbar-right .btn-navy i {
                font-size: 15px;
            }
        }

        /* A toast anchored only by right:24px grows leftward until it hits the
           viewport edge, so on a 320px screen it ends up flush at L0 with no
           gutter on that side. Anchor both edges and let the text wrap. */
        @media (max-width: 520px) {
            .toast-msg {
                left: 16px;
                right: 16px;
                bottom: 16px;
            }
        }

        /* Remove Block deletes a blocked period and the FullCalendar toolbar
           drives navigation, so both need finger-sized targets. Keyed on pointer
           type, not width: a tablet at 768px and a desktop window dragged to
           768px need different hit areas, and only the first is a finger. */
        @media (hover: none) and (pointer: coarse) {

            .fc .fc-button,
            .btn-navy,
            .logout-btn,
            .btn-view,
            .btn-danger-sm,
            .btn-submit {
                min-height: 44px;
            }

            .modal-close {
                width: 44px;
                height: 44px;
            }

            /* Icon-only below 560px, so it needs the width set too. */
            .topbar-right .btn-navy {
                min-width: 44px;
            }

            .filter-bar select,
            .form-control-sm2 {
                min-height: 44px;
            }
        }
    </style>
@endpush

@section('content')

    {{-- LEGEND --}}
    <div class="legend">
        <div class="legend-item">
            <div class="legend-dot" style="background:#f59e0b;"></div> Pending
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:#3b82f6;"></div> Confirmed
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:#10b981;"></div> Checked In
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:#6b7280;"></div> Checked Out
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:#ef4444;"></div> No Show
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:#fca5a5; border:1px solid #fca5a5;"></div> Blocked
        </div>
    </div>

    {{-- FILTER BAR --}}
    {{-- Walang Property filter dito. Iisa ang listing na puwedeng i-book, kaya
         ang tanging magagawa ng dropdown na iyon ay salain ang calendar
         pababa sa isang room na permanenteng walang laman. --}}
    <div class="filter-bar">
        <div>
            <label>Status</label>
            <select id="filterStatus" onchange="refreshCalendar()">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="checked_in">Checked In</option>
                <option value="checked_out">Checked Out</option>
            </select>
        </div>
        <div class="text-muted-theme" style="margin-left:auto; font-size: 14px; align-self:center;">
            <i class="bi bi-info-circle me-1"></i> Drag bookings to reschedule
        </div>
    </div>

    {{-- CALENDAR --}}
    <div class="calendar-wrap">
        <div id="calendar"></div>
    </div>

@endsection

@section('modals')
    {{-- BOOKING DETAIL MODAL --}}
    <div class="modal-overlay" id="bookingModal">
        <div class="modal-box">
            <div class="modal-head">
                <div class="modal-title" id="modalBookingRef">Booking Details</div>
                <button class="modal-close" onclick="closeModal('bookingModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="detail-label">Guest</span>
                    <span class="detail-val" id="modalGuest">—</span>
                </div>
                {{-- Slot, hindi Property. Iisa ang property, kaya "Villa Elena
                     (Whole Villa)" ang nakasulat dito sa BAWAT booking — wala
                     itong sinasagot na tanong. Ang slot naman ang kailangang
                     malaman ng admin at hindi makita kahit saan sa modal na
                     ito noon. --}}
                <div class="detail-row">
                    <span class="detail-label">Slot</span>
                    <span class="detail-val" id="modalSlot">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-in</span>
                    <span class="detail-val" id="modalCheckin">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out</span>
                    <span class="detail-val" id="modalCheckout">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guests</span>
                    <span class="detail-val" id="modalGuests">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount</span>
                    <span class="detail-val" id="modalAmount">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span id="modalStatus">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment</span>
                    <span class="detail-val" id="modalPayment">—</span>
                </div>
                <div class="two-btn">
                    <a href="#" id="modalViewLink" class="btn-view"><i class="bi bi-eye"></i> View Booking</a>
                </div>
            </div>
        </div>
    </div>

    {{-- BLOCK DATES MODAL --}}
    <div class="modal-overlay" id="blockModal">
        <div class="modal-box">
            <div class="modal-head" style="background:#374151;">
                <div class="modal-title">Block Dates</div>
                <button class="modal-close" onclick="closeModal('blockModal')">✕</button>
            </div>
            <div class="modal-body">
                {{-- Ipinapakita, hindi pinipili. Tinutukoy ng quickBlock() ang
                     villa sa server; nandito ito para malaman ng admin kung ano
                     ang isasara, hindi para magpasya. --}}
                <div class="mb-12">
                    <label class="form-label-sm">Property</label>
                    <div class="form-control-sm2 text-muted-theme"
                        style="display:flex; align-items:center; background:transparent;">
                        {{ $villa->property_name }}
                    </div>
                </div>
                <div class="date-pair mb-12">
                    <div>
                        <label class="form-label-sm">Start Date</label>
                        <input type="date" id="blockStart" class="form-control-sm2" required>
                    </div>
                    <div>
                        <label class="form-label-sm">End Date</label>
                        <input type="date" id="blockEnd" class="form-control-sm2" required>
                    </div>
                </div>
                <div class="mb-12">
                    <label class="form-label-sm">Reason</label>
                    <select id="blockReason" class="form-control-sm2" required>
                        <option value="maintenance">Maintenance</option>
                        <option value="owner_use">Owner Use</option>
                        <option value="private_event">Private Event</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-12">
                    <label class="form-label-sm">Notes <span class="text-muted-theme"
                            style="font-weight:400; text-transform:none;">(optional)</span></label>
                    <input type="text" id="blockNotes" class="form-control-sm2"
                        placeholder="e.g. Annual maintenance">
                </div>
                <button class="btn-submit" onclick="submitBlock()">
                    <i class="bi bi-calendar-x"></i> Block These Dates
                </button>
            </div>
        </div>
    </div>

    {{-- 22-HOUR SLOT WINDOW MODAL --}}
    {{-- Kabaligtaran ng Block Dates: ito ay NAGBUBUKAS ng slot na kung hindi
         ay hindi inaalok. Iisang petsa ang tinatanggap — isang alok na stay
         kada entry — at ipinapakita ang buong saklaw pabalik sa admin bago
         siya mag-submit, para walang duda kung ano ang ginawa niya. --}}
    <div class="modal-overlay" id="slotWindowModal">
        <div class="modal-box">
            <div class="modal-head" style="background:#0f766e;">
                <div class="modal-title">Open a 22-Hour Date</div>
                <button class="modal-close" onclick="closeModal('slotWindowModal')">✕</button>
            </div>
            <div class="modal-body">
                <p class="text-muted-theme" style="font-size:13px; margin:0 0 12px;">
                    Pick the <strong>check-in date</strong>. That date will offer the
                    22-hour stay <strong>only</strong> — Day and Night are hidden on it.
                    Every other date keeps the regular slots.
                </p>
                <div class="mb-12">
                    <label class="form-label-sm">Check-in Date</label>
                    <input type="date" id="swDate" class="form-control-sm2"
                        min="{{ now()->format('Y-m-d') }}" required>
                    <div id="swSpan" class="text-muted-theme"
                        style="font-size:12.5px; margin-top:6px; min-height:18px;"></div>
                </div>
                <div class="mb-12">
                    <label class="form-label-sm">Notes <span class="text-muted-theme"
                            style="font-weight:400; text-transform:none;">(optional)</span></label>
                    <input type="text" id="swNotes" class="form-control-sm2"
                        placeholder="e.g. Reunion package">
                </div>
                <div id="swWarn" hidden
                    style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px;
                           padding:10px 12px; font-size:13px; margin-bottom:12px;"></div>
                <button class="btn-submit" style="background:#0f766e;" onclick="submitSlotWindow()">
                    <i class="bi bi-house-door"></i> Open This Date
                </button>
            </div>
        </div>
    </div>

    {{-- 22-HOUR WINDOW DETAIL / DELETE --}}
    <div class="modal-overlay" id="slotWindowDetailModal">
        <div class="modal-box">
            <div class="modal-head" style="background:#0f766e;">
                <div class="modal-title">22-Hour Date</div>
                <button class="modal-close" onclick="closeModal('slotWindowDetailModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="detail-label">Stay</span>
                    <span class="detail-value" id="swdSpan">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Offered</span>
                    <span class="detail-value" id="swdSlot">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value" id="swdNotes">—</span>
                </div>
                <p class="text-muted-theme" style="font-size:12.5px; margin:12px 0 0;">
                    Closing this returns the date to the regular Day and Night slots.
                    Bookings already made on it are not touched.
                </p>
                <button class="btn-submit" style="background:#dc2626; margin-top:14px;"
                    onclick="deleteSlotWindow()">
                    <i class="bi bi-trash"></i> Close This Date
                </button>
            </div>
        </div>
    </div>

    {{-- BLOCK DETAIL MODAL --}}
    <div class="modal-overlay" id="blockDetailModal">
        <div class="modal-box">
            <div class="modal-head" style="background:#374151;">
                <div class="modal-title">Blocked Period</div>
                <button class="modal-close" onclick="closeModal('blockDetailModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="detail-label">Property</span>
                    <span class="detail-val" id="bdProperty">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">From</span>
                    <span class="detail-val" id="bdStart">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">To</span>
                    <span class="detail-val" id="bdEnd">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reason</span>
                    <span class="detail-val" id="bdReason">—</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Notes</span>
                    <span class="detail-val" id="bdNotes">—</span>
                </div>
                <div class="two-btn">
                    <button class="btn-danger-sm" onclick="deleteBlock()">
                        <i class="bi bi-trash"></i> Remove Block
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- TOAST --}}
    <div class="toast-msg" id="toast">
        <i class="bi bi-check-circle-fill"></i>
        <span id="toastText">Done!</span>
    </div>
@endsection

@push('scripts')
    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const bookingShowUrlTemplate = '{{ route('admin.bookings.show', ['booking' => '__ID__']) }}';
        let calendar;
        let activeBlockId = null;

        // A month grid needs seven columns. At a 320px viewport that is a 37px day
        // cell, and an event title — "<property> — <guest>" — renders 31px of the
        // 266px it wants, i.e. about three characters. listWeek gives each booking
        // a full row instead. Matches the 600px breakpoint the stylesheet above
        // already uses for this page.
        const phoneMQ = window.matchMedia('(max-width: 600px)');

        // Petsa bilang 'YYYY-MM-DD' mula sa LOKAL na mga getter.
        //
        // HINDI `.toISOString().split('T')[0]`. Ginagawa ng FullCalendar ang
        // petsa ng all-day event sa hatinggabi na LOKAL, at ang toISOString()
        // ay nagko-convert patungong UTC — kaya sa UTC+8 ang hatinggabi ng
        // Ago 10 ay 16:00 ng Ago 9 sa UTC, at ang hinihiwang petsa ay
        // NAUUNA NANG ISANG ARAW.
        //
        // Ito ang dahilan ng "must be a date after or equal to check in date":
        // tama ang `startStr` (sariling lokal na string ng FullCalendar) pero
        // ang dulo ay dumaraan sa UTC, kaya dalawang araw ang nababawas imbes
        // na isa, at naipapadala ang checkout bago pa ang check-in.
        function localDateStr(d) {
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }

        // ── Init FullCalendar ──────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(el, {
                plugins: [FullCalendar.dayGridPlugin, FullCalendar.listPlugin,
                    FullCalendar.interactionPlugin
                ],
                initialView: phoneMQ.matches ? 'listWeek' : 'dayGridMonth',

                // Dalawang tab lang: Month at Week.
                //
                // WALA nang `timeGridWeek`. Petsa lamang ang ipinapadala ng
                // events() (`check_in_date->format('Y-m-d')`), kaya all-day ang
                // turing ng FullCalendar sa bawat booking — nasa manipis na
                // guhit sa itaas ang lahat at WALANG LAMAN HABANG-BUHAY ang
                // 24-oras na grid sa ilalim. Isang mataas na blangkong ruler
                // ang buong tab.
                //
                // Kayang buhayin iyon sa pamamagitan ng tunay na datetime mula
                // sa slotDateTimes(), at maganda sana ang hitsura — pero ang
                // time grid ay nag-aanyaya ng patayong pag-drag, at DATES
                // LAMANG ang binabasa ng moveBooking() (sinasadyang ginagamit
                // muli ang nakaimbak na check_in_time, dahil walang
                // free-choice na oras kahit saan). Mukhang tumatalab ang
                // paglipat ng oras at saka babalik sa dati sa refetch.
                //
                // Ang dating `listWeek` ang tunay na week schedule, kaya
                // "Week" na ang pangalan nito. Dati ay month / week / list,
                // kung saan walang ipinapakita ang "week" at ang "list" ang
                // hinahanap — at sa telepono ay awtomatikong napupunta ang
                // admin sa tab na dating nakasulat na "list".
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek'
                },
                buttonText: {
                    listWeek: 'Week'
                },
                height: 'auto',
                editable: true, // enables drag & drop
                eventResizableFromStart: false,
                selectable: true,

                // ── Fetch events from API ──────────────────────────────
                events: function(info, successCallback, failureCallback) {
                    const status = document.getElementById('filterStatus').value;

                    fetch(`{{ route('admin.calendar.events') }}?start=${info.startStr}&end=${info.endStr}&status=${status}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            // Kliyente pa rin ang sumasala ng status — hindi ito
                            // binabasa ng events() sa server.
                            let filtered = data;
                            if (status) {
                                filtered = filtered.filter(e =>
                                    e.extendedProps.type === 'block' ||
                                    e.extendedProps.type === 'slot_window' ||
                                    e.extendedProps.status === status
                                );
                            }
                            successCallback(filtered);
                        })
                        .catch(failureCallback);
                },

                // ── Click event ────────────────────────────────────────
                eventClick: function(info) {
                    const p = info.event.extendedProps;
                    if (p.type === 'booking') {
                        showBookingModal(info.event);
                    } else if (p.type === 'block') {
                        showBlockDetailModal(info.event);
                    } else if (p.type === 'slot_window') {
                        showSlotWindowDetailModal(info.event);
                    }
                },

                // ── Drag & Drop ────────────────────────────────────────
                eventDrop: function(info) {
                    const p = info.event.extendedProps;
                    if (p.type !== 'booking') {
                        info.revert();
                        return;
                    }

                    const newStart = info.event.startStr;
                    // end in FullCalendar is exclusive, so subtract 1 day for check_out
                    const endDate = new Date(info.event.end);
                    endDate.setDate(endDate.getDate() - 1);
                    const newEnd = localDateStr(endDate);

                    if (!confirm(`Move ${p.booking_ref} to ${newStart} – ${newEnd}?`)) {
                        info.revert();
                        return;
                    }

                    // 'Accept: application/json' — ito ang nawawala noon.
                    //
                    // Ang `Content-Type` ay nagsasabi kung ANO ang ipinapadala;
                    // ang `Accept` ang nagsasabi kung ano ang tinatanggap. Kung
                    // wala ito ay hindi itinuturing ni Laravel na humihingi ng
                    // JSON ang request, kaya ang pagbagsak ng validation ay
                    // nagre-redirect (302 → HTML) imbes na magbalik ng 422 JSON.
                    // Sumasabog ang r.json() sa HTML na iyon, kaya ang .catch()
                    // ang tumatakbo at "Error moving booking" ang lumalabas —
                    // habang nasa response mismo ang tunay na dahilan.
                    fetch(`{{ url('admin/calendar/bookings') }}/${p.booking_id}/move`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': CSRF
                            },
                            // check_in_date lang. Ang slot ang nagtatakda ng
                            // dulo at ang server ang kumukuha nito sa
                            // slotDateTimes() — tingnan ang moveBooking().
                            // Ginagamit pa rin ang `newEnd` sa itaas para sa
                            // tanong ng confirm dialog.
                            body: JSON.stringify({
                                check_in_date: newStart
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                // Hindi "${data.nights} nights". Ang num_nights
                                // ay 1 sa BAWAT row sa database — isang slot ang
                                // booking, hindi isang bilang ng gabi — kaya ang
                                // tanging sinasabi ng "1 nights" ay isang lumang
                                // modelo at maling gramatika. Ang slot at ang
                                // bagong petsa ang aktuwal na nabago.
                                const slot = p.slot ? p.slot.charAt(0).toUpperCase() + p.slot.slice(1) : 'Booking';
                                showToast(`${p.booking_ref} moved — ${slot} on ${newStart}`);
                            } else {
                                info.revert();
                                // Ang server ang nagsasabi kung BAKIT — halos
                                // palaging dahil may ibang booking nang humahawak
                                // sa petsa/slot na iyon.
                                showToast(data.message || 'Could not move booking', true);
                            }
                        })
                        .catch(() => {
                            info.revert();
                            showToast('Error moving booking', true);
                        });
                },

                // ── Select date range → pre-fill block modal ──────────
                select: function(info) {
                    document.getElementById('blockStart').value = info.startStr;
                    // end is exclusive in FC, so subtract 1 day
                    // Parehong depekto ang nandito: prinipi-fill nito ang End
                    // Date ng Block modal nang isang araw na maaga, kaya ang
                    // pagpili ng Ago 10–12 ay nagsusulat ng Ago 10 sa dulo.
                    const endDate = new Date(info.end);
                    endDate.setDate(endDate.getDate() - 1);
                    document.getElementById('blockEnd').value = localDateStr(endDate);
                    openBlockModal();
                    calendar.unselect();
                },

                eventDidMount: function(info) {
                    // Tooltip via title attr
                    const p = info.event.extendedProps;
                    if (p.type === 'booking') {
                        info.el.title =
                        `${p.booking_ref} · ${p.guest} · ${p.check_in} – ${p.check_out}`;
                    }

                    // ── Hanay ng oras sa Week (list) view ──────────────
                    //
                    // Petsa lang ang ipinapadala ng events(), kaya all-day ang
                    // turing ng FullCalendar sa bawat booking at "all-day" ang
                    // isinusulat nito sa hanay ng oras — walang saysay sa isang
                    // sistemang may dalawang nakapirming slot, at siya pang
                    // tanging bagay na magkakaiba sana sa isang Day at isang
                    // Night sa parehong petsa.
                    //
                    // HINDI ito kayang ayusin ng `allDayText`: iisang string
                    // lang iyon para sa lahat ng event. Kailangang kada-event,
                    // kaya dito.
                    if (info.view.type !== 'listWeek') return;

                    const timeCell = info.el.querySelector('.fc-list-event-time');
                    if (!timeCell) return;

                    if (p.type === 'block') {
                        timeCell.textContent = 'Blocked';
                        return;
                    }

                    if (p.type === 'slot_window') {
                        timeCell.textContent = p.slot_name;
                        return;
                    }

                    timeCell.textContent = p.slot_display;

                    // May sariling hanay na ang slot, kaya doble na ang "Day · "
                    // na unlapi sa pamagat dito. Pangalan na lang ng bisita.
                    // Nananatili ito sa Month view, kung saan ito ang tanging
                    // nagkakaiba sa dalawang booking sa iisang cell.
                    const titleCell = info.el.querySelector('.fc-list-event-title');
                    if (titleCell) {
                        const link = titleCell.querySelector('a');
                        (link || titleCell).textContent = p.guest;
                    }
                }
            });
            calendar.render();

            // Only on an actual crossing of the breakpoint, not on every resize:
            // an admin who picks month view on a phone keeps it until they cross
            // back, instead of being yanked to list on the next scroll-resize.
            phoneMQ.addEventListener('change', function(e) {
                calendar.changeView(e.matches ? 'listWeek' : 'dayGridMonth');
            });

            // Live sync: if another admin creates, moves, or changes the
            // status of a booking while this calendar is open, refetch so it
            // doesn't show stale dates/availability. Reuses the Pusher
            // connection already opened by admin/partials/realtime.blade.php.
            if (window.rtChannel) {
                window.rtChannel.bind('booking.created', () => calendar.refetchEvents());
                window.rtChannel.bind('booking.updated', () => calendar.refetchEvents());
            }
        });

        function refreshCalendar() {
            calendar.refetchEvents();
        }

        // ── Booking Modal ──────────────────────────────────────────────
        function showBookingModal(event) {
            const p = event.extendedProps;
            document.getElementById('modalBookingRef').textContent = p.booking_ref;
            document.getElementById('modalGuest').textContent = p.guest;
            document.getElementById('modalSlot').textContent = p.slot_display;
            document.getElementById('modalCheckin').textContent = p.check_in;
            document.getElementById('modalCheckout').textContent = p.check_out;
            document.getElementById('modalGuests').textContent = p.num_guests + ' guest(s)';
            document.getElementById('modalAmount').textContent = '₱' + parseFloat(p.total_amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('modalPayment').textContent = ucFirst(p.payment_status);
            document.getElementById('modalViewLink').href = bookingShowUrlTemplate.replace('__ID__', p.booking_id);

            const statusEl = document.getElementById('modalStatus');
            statusEl.innerHTML = `<span class="status-pill s-${p.status}">${ucFirst(p.status.replace('_',' '))}</span>`;

            document.getElementById('bookingModal').classList.add('open');
        }

        // ── Block Detail Modal ─────────────────────────────────────────
        function showBlockDetailModal(event) {
            const p = event.extendedProps;
            activeBlockId = p.block_id;
            document.getElementById('bdProperty').textContent = p.property;
            document.getElementById('bdStart').textContent = p.start_date;
            document.getElementById('bdEnd').textContent = p.end_date;
            document.getElementById('bdReason').textContent = p.reason;
            document.getElementById('bdNotes').textContent = p.notes || '—';
            document.getElementById('blockDetailModal').classList.add('open');
        }

        // ── 22-Hour Slot Windows ───────────────────────────────────────
        let activeSlotWindowId = null;

        function openSlotWindowModal() {
            document.getElementById('swDate').value = '';
            document.getElementById('swNotes').value = '';
            document.getElementById('swSpan').textContent = '';
            const warn = document.getElementById('swWarn');
            warn.hidden = true;
            warn.textContent = '';
            // Ang confirm ay umaabot lang sa PARTIKULAR na petsang binalaan.
            // Kung hindi, ang isang "oo" sa Okt 2 ay tahimik na magpapatuloy
            // sa Okt 9 sa susunod na pagbukas ng modal.
            confirmedFor = null;
            document.getElementById('slotWindowModal').classList.add('open');
        }

        // Ipinapakita ang buong saklaw ng stay habang pumipili ng petsa, para
        // hindi na kailangang hulaan ng admin kung saan ito nagtatapos. 19:00
        // → 17:00 kinabukasan; nakasulat dito dahil ang input ay petsa lang.
        document.getElementById('swDate')?.addEventListener('change', function () {
            const el = document.getElementById('swSpan');
            if (!this.value) { el.textContent = ''; return; }
            const start = new Date(this.value + 'T19:00:00');
            const end = new Date(start.getTime() + 22 * 3600 * 1000);
            const f = d => d.toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
            });
            el.textContent = 'Stay: ' + f(start) + ' → ' + f(end);
        });

        let confirmedFor = null;

        function submitSlotWindow() {
            const date = document.getElementById('swDate').value;
            const notes = document.getElementById('swNotes').value;
            const warn = document.getElementById('swWarn');

            if (!date) {
                warn.hidden = false;
                warn.textContent = 'Pick a check-in date first.';
                return;
            }

            fetch('{{ route('admin.calendar.addSlotWindow') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify({
                        slot: 'stay22',
                        check_in_date: date,
                        notes,
                        // Ipinapadala lang ang kumpirmasyon para sa EKSAKTONG
                        // petsang binalaan tungkol sa server.
                        confirm_conflict: confirmedFor === date
                    })
                })
                .then(r => r.json().then(d => ({ ok: r.ok, status: r.status, d })))
                .then(({ status, d }) => {
                    if (d.success) {
                        closeModal('slotWindowModal');
                        calendar.refetchEvents();
                        showToast('Opened ' + (d.span_label || 'that date') + ' as 22-hour only');
                        return;
                    }

                    warn.hidden = false;
                    warn.textContent = d.message || 'Could not open that date.';

                    // 409 = kaduda-duda pero pinapayagan kung sinasadya. Ang
                    // susunod na pindot sa parehong petsa ang magpapatuloy.
                    if (status === 409 && d.needs_confirmation) {
                        confirmedFor = date;
                        warn.textContent += ' — press “Open This Date” again to continue.';
                    } else {
                        confirmedFor = null;
                    }
                })
                .catch(() => showToast('Error opening that date', true));
        }

        function showSlotWindowDetailModal(event) {
            const p = event.extendedProps;
            activeSlotWindowId = p.window_id;
            document.getElementById('swdSpan').textContent = p.span_label || '—';
            document.getElementById('swdSlot').textContent = (p.slot_name || '—') + ' only';
            document.getElementById('swdNotes').textContent = p.notes || '—';
            document.getElementById('slotWindowDetailModal').classList.add('open');
        }

        function deleteSlotWindow() {
            if (!activeSlotWindowId) return;
            fetch(`{{ url('admin/calendar/slot-windows') }}/${activeSlotWindowId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal('slotWindowDetailModal');
                        calendar.refetchEvents();
                        showToast('That date is back to the regular slots');
                    }
                })
                .catch(() => showToast('Error closing that date', true));
        }

        // ── Block Modal ────────────────────────────────────────────────
        function openBlockModal() {
            document.getElementById('blockModal').classList.add('open');
        }

        function submitBlock() {
            const start = document.getElementById('blockStart').value;
            const end = document.getElementById('blockEnd').value;
            const reason = document.getElementById('blockReason').value;
            const notes = document.getElementById('blockNotes').value;

            if (!start || !end) {
                alert('Please fill in all required fields.');
                return;
            }

            fetch('{{ route('admin.calendar.block') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify({
                        start_date: start,
                        end_date: end,
                        reason,
                        notes
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal('blockModal');
                        calendar.refetchEvents();
                        showToast('Dates blocked successfully');
                        document.getElementById('blockStart').value = '';
                        document.getElementById('blockEnd').value = '';
                        document.getElementById('blockNotes').value = '';
                    }
                })
                .catch(() => showToast('Error blocking dates', true));
        }

        function deleteBlock() {
            if (!activeBlockId || !confirm('Remove this blocked period?')) return;
            fetch(`{{ url('admin/calendar/blocks') }}/${activeBlockId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal('blockDetailModal');
                        calendar.refetchEvents();
                        showToast('Block removed');
                    }
                })
                .catch(() => showToast('Error removing block', true));
        }

        // ── Helpers ────────────────────────────────────────────────────
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function ucFirst(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
        }

        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            document.getElementById('toastText').textContent = msg;
            toast.style.background = isError ? '#dc2626' : 'var(--terracotta)';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // Close modals on backdrop click
        ['bookingModal', 'blockModal', 'blockDetailModal',
         'slotWindowModal', 'slotWindowDetailModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeModal(id);
            });
        });
    </script>
@endpush

