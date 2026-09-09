@extends('layouts.admin')

@section('title', 'Calendar — Villa Elena Admin')
@section('page-title', 'Calendar')
@section('page-subtitle', 'Bookings, availability & blocked dates')

@section('topbar-right')
    <button class="btn-navy" onclick="openBlockModal()">
        <i class="bi bi-calendar-x"></i> Block Dates
    </button>
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
    <div class="filter-bar">
        <div>
            <label>Property</label>
            <select id="filterProperty" onchange="refreshCalendar()">
                <option value="">All Properties</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->property_name }}</option>
                @endforeach
            </select>
        </div>
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
                <div class="detail-row">
                    <span class="detail-label">Property</span>
                    <span class="detail-val" id="modalProperty">—</span>
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
                <div class="mb-12">
                    <label class="form-label-sm">Property</label>
                    <select id="blockProperty" class="form-control-sm2" required>
                        <option value="">Select property...</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->property_name }}</option>
                        @endforeach
                    </select>
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

        // ── Init FullCalendar ──────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(el, {
                plugins: [FullCalendar.dayGridPlugin, FullCalendar.timeGridPlugin, FullCalendar.listPlugin,
                    FullCalendar.interactionPlugin
                ],
                initialView: phoneMQ.matches ? 'listWeek' : 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                height: 'auto',
                editable: true, // enables drag & drop
                eventResizableFromStart: false,
                selectable: true,
                nowIndicator: true,

                // ── Fetch events from API ──────────────────────────────
                events: function(info, successCallback, failureCallback) {
                    const propId = document.getElementById('filterProperty').value;
                    const status = document.getElementById('filterStatus').value;

                    fetch(`{{ route('admin.calendar.events') }}?start=${info.startStr}&end=${info.endStr}&property_id=${propId}&status=${status}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            // Apply client-side filters
                            let filtered = data;
                            if (propId) {
                                filtered = filtered.filter(e =>
                                    e.extendedProps.type === 'block' ||
                                    String(e.extendedProps.property_id) === String(propId)
                                );
                            }
                            if (status) {
                                filtered = filtered.filter(e =>
                                    e.extendedProps.type === 'block' ||
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
                    const newEnd = endDate.toISOString().split('T')[0];

                    if (!confirm(`Move ${p.booking_ref} to ${newStart} – ${newEnd}?`)) {
                        info.revert();
                        return;
                    }

                    fetch(`{{ url('admin/calendar/bookings') }}/${p.booking_id}/move`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': CSRF
                            },
                            body: JSON.stringify({
                                check_in_date: newStart,
                                check_out_date: newEnd
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showToast(`${p.booking_ref} moved — ${data.nights} nights`);
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
                    const endDate = new Date(info.end);
                    endDate.setDate(endDate.getDate() - 1);
                    document.getElementById('blockEnd').value = endDate.toISOString().split('T')[0];
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
            document.getElementById('modalProperty').textContent = p.property;
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

        // ── Block Modal ────────────────────────────────────────────────
        function openBlockModal() {
            document.getElementById('blockModal').classList.add('open');
        }

        function submitBlock() {
            const propertyId = document.getElementById('blockProperty').value;
            const start = document.getElementById('blockStart').value;
            const end = document.getElementById('blockEnd').value;
            const reason = document.getElementById('blockReason').value;
            const notes = document.getElementById('blockNotes').value;

            if (!propertyId || !start || !end) {
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
                        property_id: propertyId,
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
                        document.getElementById('blockProperty').value = '';
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
        ['bookingModal', 'blockModal', 'blockDetailModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeModal(id);
            });
        });
    </script>
@endpush

