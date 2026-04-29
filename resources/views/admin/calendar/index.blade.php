{{-- SAVE AS: resources/views/admin/calendar/index.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Calendar — Villa Elena Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- FullCalendar --}}
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

    <style>
        :root {
            --navy:       #0d1b2a;
            --navy-mid:   #1a2f45;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dim:   rgba(201,168,76,0.15);
            --white:      #ffffff;
            --off-white:  #f4f6f9;
            --text-main:  #1a2f45;
            --text-muted: #6b7a8d;
            --border:     #e2e8f0;
            --sidebar-w:  260px;
            --topbar-h:   68px;
        }
        * { box-sizing: border-box; margin:0; padding:0; }
        body { font-family:'DM Sans',sans-serif; background:var(--off-white); color:var(--text-main); overflow-x:hidden; }

        /* SIDEBAR */
        .sidebar { position:fixed; top:0; left:0; width:var(--sidebar-w); height:100vh; background:var(--navy); display:flex; flex-direction:column; z-index:1000; overflow-y:auto; }
        .sidebar-brand { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.07); }
        .sidebar-brand h1 { font-family:'Cormorant Garamond',serif; color:var(--gold-light); font-size:22px; font-weight:700; line-height:1.2; }
        .sidebar-brand p { color:rgba(255,255,255,0.35); font-size:11px; letter-spacing:1.5px; text-transform:uppercase; margin-top:3px; }
        .sidebar-section { padding:20px 16px 8px; }
        .sidebar-section-label { font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:rgba(255,255,255,0.25); padding:0 8px; margin-bottom:6px; }
        .nav-item-custom { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:8px; color:rgba(255,255,255,0.6); text-decoration:none; font-size:14px; transition:all .2s; margin-bottom:2px; }
        .nav-item-custom:hover { background:rgba(255,255,255,0.07); color:var(--white); }
        .nav-item-custom.active { background:var(--gold-dim); color:var(--gold-light); font-weight:500; }
        .nav-item-custom .nav-icon { width:32px; height:32px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; background:rgba(255,255,255,0.05); }
        .nav-item-custom.active .nav-icon { background:var(--gold-dim); color:var(--gold); }
        .sidebar-footer { margin-top:auto; padding:16px; border-top:1px solid rgba(255,255,255,0.07); }
        .user-card { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,0.05); }
        .user-avatar { width:36px; height:36px; border-radius:50%; background:var(--gold-dim); color:var(--gold); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:600; flex-shrink:0; }
        .user-info .name { color:var(--white); font-size:13px; font-weight:500; }
        .user-info .role-badge { font-size:10px; color:var(--gold); letter-spacing:0.5px; text-transform:uppercase; }

        /* TOPBAR */
        .topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:var(--white); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 32px; z-index:900; }
        .topbar-left h2 { font-family:'Cormorant Garamond',serif; font-size:22px; font-weight:600; }
        .topbar-left p { font-size:12px; color:var(--text-muted); margin-top:1px; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .btn-navy { background:var(--navy); color:#fff; border:none; border-radius:9px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; display:inline-flex; align-items:center; gap:6px; transition:all .2s; text-decoration:none; }
        .btn-navy:hover { background:var(--gold); color:var(--navy); }
        .logout-btn { display:flex; align-items:center; gap:7px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:9px; padding:7px 14px; font-size:13px; font-weight:500; cursor:pointer; transition:all .2s; text-decoration:none; }
        .logout-btn:hover { background:#ef4444; color:white; border-color:#ef4444; }

        /* MAIN */
        .main-content { margin-left:var(--sidebar-w); margin-top:var(--topbar-h); padding:24px 32px; min-height:calc(100vh - var(--topbar-h)); }

        /* LEGEND */
        .legend { display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
        .legend-item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
        .legend-dot { width:12px; height:12px; border-radius:3px; flex-shrink:0; }

        /* FILTER BAR */
        .filter-bar { background:var(--white); border-radius:12px; border:1px solid var(--border); padding:14px 18px; margin-bottom:16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .filter-bar label { font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin-right:4px; }
        .filter-bar select { border:1.5px solid var(--border); border-radius:8px; padding:7px 12px; font-size:13px; font-family:'DM Sans',sans-serif; color:var(--text-main); background:#fff; cursor:pointer; }
        .filter-bar select:focus { outline:none; border-color:var(--navy); }

        /* CALENDAR WRAPPER */
        .calendar-wrap { background:var(--white); border-radius:14px; border:1px solid var(--border); padding:20px; }

        /* FullCalendar Overrides */
        .fc { font-family:'DM Sans',sans-serif; }
        .fc .fc-toolbar-title { font-family:'Cormorant Garamond',serif; font-size:22px; font-weight:600; color:var(--navy); }
        .fc .fc-button { background:var(--navy) !important; border-color:var(--navy) !important; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:500; border-radius:8px !important; padding:6px 14px !important; }
        .fc .fc-button:hover { background:var(--gold) !important; border-color:var(--gold) !important; color:var(--navy) !important; }
        .fc .fc-button-active { background:var(--gold) !important; border-color:var(--gold) !important; color:var(--navy) !important; }
        .fc .fc-col-header-cell { background:#f8fafc; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); }
        .fc .fc-daygrid-day-number { font-size:12px; color:var(--text-main); padding:6px 8px; }
        .fc .fc-daygrid-day.fc-day-today { background:#fffbeb; }
        .fc .fc-event { border-radius:5px; font-size:11px; font-weight:500; padding:2px 5px; cursor:pointer; }
        .fc .fc-event:hover { opacity:.85; }
        .fc .fc-daygrid-event-dot { display:none; }
        .fc-theme-standard td, .fc-theme-standard th { border-color:var(--border); }
        .fc .fc-scrollgrid { border-color:var(--border); border-radius:10px; overflow:hidden; }

        /* MODAL */
        .modal-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:18px; width:440px; max-width:calc(100vw - 32px); overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.2); animation:slideUp .25s ease; }
        @keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        .modal-head { background:var(--navy); padding:18px 22px; display:flex; align-items:center; justify-content:space-between; }
        .modal-title { font-family:'Cormorant Garamond',serif; color:#fff; font-size:18px; font-weight:600; }
        .modal-close { background:rgba(255,255,255,.1); border:none; color:#fff; width:30px; height:30px; border-radius:7px; cursor:pointer; font-size:15px; display:flex; align-items:center; justify-content:center; }
        .modal-body { padding:22px; }
        .detail-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:var(--text-muted); font-size:12px; }
        .detail-val { font-weight:500; }
        .status-pill { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .s-pending    { background:#fef9c3; color:#a16207; }
        .s-confirmed  { background:#dbeafe; color:#1d4ed8; }
        .s-checked_in { background:#dcfce7; color:#15803d; }
        .s-checked_out{ background:#f1f5f9; color:#475569; }
        .s-cancelled  { background:#fee2e2; color:#dc2626; }
        .s-no_show    { background:#f1f5f9; color:#374151; }
        .form-label-sm { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:5px; text-transform:uppercase; letter-spacing:.3px; }
        .form-control-sm2 { width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif; background:#fff; transition:border-color .2s; }
        .form-control-sm2:focus { outline:none; border-color:var(--navy); }
        .mb-12 { margin-bottom:12px; }
        .btn-submit { background:var(--navy); color:#fff; border:none; border-radius:9px; padding:11px; width:100%; font-size:13px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; margin-top:6px; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:6px; }
        .btn-submit:hover { background:var(--gold); color:var(--navy); }
        .btn-danger-sm { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:8px 14px; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
        .btn-danger-sm:hover { background:#dc2626; color:#fff; }
        .btn-view { background:var(--gold-dim); color:var(--navy); border:1px solid rgba(201,168,76,.3); border-radius:8px; padding:8px 14px; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
        .two-btn { display:flex; gap:8px; margin-top:14px; }
        .toast-msg { position:fixed; bottom:24px; right:24px; background:var(--navy); color:#fff; border-radius:10px; padding:12px 20px; font-size:13px; font-weight:500; z-index:99999; transform:translateY(80px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:8px; }
        .toast-msg.show { transform:translateY(0); opacity:1; }
    </style>
</head>
<body>

@include('admin.partials.sidebar')

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <h2>Calendar</h2>
        <p>Bookings, availability & blocked dates</p>
    </div>
    <div class="topbar-right">
        <button class="btn-navy" onclick="openBlockModal()">
            <i class="bi bi-calendar-x"></i> Block Dates
        </button>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</div>

<div class="main-content">

    {{-- LEGEND --}}
    <div class="legend">
        <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div> Pending</div>
        <div class="legend-item"><div class="legend-dot" style="background:#3b82f6;"></div> Confirmed</div>
        <div class="legend-item"><div class="legend-dot" style="background:#10b981;"></div> Checked In</div>
        <div class="legend-item"><div class="legend-dot" style="background:#6b7280;"></div> Checked Out</div>
        <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> No Show</div>
        <div class="legend-item"><div class="legend-dot" style="background:#fca5a5; border:1px solid #fca5a5;"></div> Blocked</div>
    </div>

    {{-- FILTER BAR --}}
    <div class="filter-bar">
        <div>
            <label>Property</label>
            <select id="filterProperty" onchange="refreshCalendar()">
                <option value="">All Properties</option>
                @foreach($properties as $property)
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
        <div style="margin-left:auto; font-size:12px; color:var(--text-muted);">
            <i class="bi bi-info-circle me-1"></i> Drag bookings to reschedule
        </div>
    </div>

    {{-- CALENDAR --}}
    <div class="calendar-wrap">
        <div id="calendar"></div>
    </div>

</div>

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
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->property_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="mb-12">
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
                <label class="form-label-sm">Notes <span style="font-weight:400; color:var(--text-muted); text-transform:none;">(optional)</span></label>
                <input type="text" id="blockNotes" class="form-control-sm2" placeholder="e.g. Annual maintenance">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
    const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
    let calendar;
    let activeBlockId = null;

    // ── Init FullCalendar ──────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,timeGridWeek,listWeek'
            },
            height: 'auto',
            editable: true,      // enables drag & drop
            eventResizableFromStart: false,
            selectable: true,
            nowIndicator: true,

            // ── Fetch events from API ──────────────────────────────
            events: function (info, successCallback, failureCallback) {
                const propId  = document.getElementById('filterProperty').value;
                const status  = document.getElementById('filterStatus').value;

                fetch(`{{ route('admin.calendar.events') }}?start=${info.startStr}&end=${info.endStr}&property_id=${propId}&status=${status}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
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
            eventClick: function (info) {
                const p = info.event.extendedProps;
                if (p.type === 'booking') {
                    showBookingModal(info.event);
                } else if (p.type === 'block') {
                    showBlockDetailModal(info.event);
                }
            },

            // ── Drag & Drop ────────────────────────────────────────
            eventDrop: function (info) {
                const p = info.event.extendedProps;
                if (p.type !== 'booking') { info.revert(); return; }

                const newStart = info.event.startStr;
                // end in FullCalendar is exclusive, so subtract 1 day for check_out
                const endDate  = new Date(info.event.end);
                endDate.setDate(endDate.getDate() - 1);
                const newEnd   = endDate.toISOString().split('T')[0];

                if (!confirm(`Move ${p.booking_ref} to ${newStart} – ${newEnd}?`)) {
                    info.revert(); return;
                }

                fetch(`{{ url('admin/calendar/bookings') }}/${p.booking_id}/move`, {
                    method: 'PATCH',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ check_in_date: newStart, check_out_date: newEnd })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { showToast(`${p.booking_ref} moved — ${data.nights} nights`); }
                    else { info.revert(); showToast('Could not move booking', true); }
                })
                .catch(() => { info.revert(); showToast('Error moving booking', true); });
            },

            // ── Select date range → pre-fill block modal ──────────
            select: function (info) {
                document.getElementById('blockStart').value = info.startStr;
                // end is exclusive in FC, so subtract 1 day
                const endDate = new Date(info.end);
                endDate.setDate(endDate.getDate() - 1);
                document.getElementById('blockEnd').value = endDate.toISOString().split('T')[0];
                openBlockModal();
                calendar.unselect();
            },

            eventDidMount: function (info) {
                // Tooltip via title attr
                const p = info.event.extendedProps;
                if (p.type === 'booking') {
                    info.el.title = `${p.booking_ref} · ${p.guest} · ${p.check_in} – ${p.check_out}`;
                }
            }
        });
        calendar.render();
    });

    function refreshCalendar() { calendar.refetchEvents(); }

    // ── Booking Modal ──────────────────────────────────────────────
    function showBookingModal(event) {
        const p = event.extendedProps;
        document.getElementById('modalBookingRef').textContent = p.booking_ref;
        document.getElementById('modalGuest').textContent      = p.guest;
        document.getElementById('modalProperty').textContent   = p.property;
        document.getElementById('modalCheckin').textContent    = p.check_in;
        document.getElementById('modalCheckout').textContent   = p.check_out;
        document.getElementById('modalGuests').textContent     = p.num_guests + ' guest(s)';
        document.getElementById('modalAmount').textContent     = '₱' + parseFloat(p.total_amount).toLocaleString('en-PH', {minimumFractionDigits:2});
        document.getElementById('modalPayment').textContent    = ucFirst(p.payment_status);
        document.getElementById('modalViewLink').href          = `/admin/bookings/${p.booking_id}`;

        const statusEl = document.getElementById('modalStatus');
        statusEl.innerHTML = `<span class="status-pill s-${p.status}">${ucFirst(p.status.replace('_',' '))}</span>`;

        document.getElementById('bookingModal').classList.add('open');
    }

    // ── Block Detail Modal ─────────────────────────────────────────
    function showBlockDetailModal(event) {
        const p = event.extendedProps;
        activeBlockId = p.block_id;
        document.getElementById('bdProperty').textContent = p.property;
        document.getElementById('bdStart').textContent    = p.start_date;
        document.getElementById('bdEnd').textContent      = p.end_date;
        document.getElementById('bdReason').textContent   = p.reason;
        document.getElementById('bdNotes').textContent    = p.notes || '—';
        document.getElementById('blockDetailModal').classList.add('open');
    }

    // ── Block Modal ────────────────────────────────────────────────
    function openBlockModal() {
        document.getElementById('blockModal').classList.add('open');
    }

    function submitBlock() {
        const propertyId = document.getElementById('blockProperty').value;
        const start      = document.getElementById('blockStart').value;
        const end        = document.getElementById('blockEnd').value;
        const reason     = document.getElementById('blockReason').value;
        const notes      = document.getElementById('blockNotes').value;

        if (!propertyId || !start || !end) { alert('Please fill in all required fields.'); return; }

        fetch('{{ route('admin.calendar.block') }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ property_id: propertyId, start_date: start, end_date: end, reason, notes })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('blockModal');
                calendar.refetchEvents();
                showToast('Dates blocked successfully');
                document.getElementById('blockProperty').value = '';
                document.getElementById('blockStart').value    = '';
                document.getElementById('blockEnd').value      = '';
                document.getElementById('blockNotes').value    = '';
            }
        })
        .catch(() => showToast('Error blocking dates', true));
    }

    function deleteBlock() {
        if (!activeBlockId || !confirm('Remove this blocked period?')) return;
        fetch(`{{ url('admin/calendar/blocks') }}/${activeBlockId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF }
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
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function ucFirst(str)   { return str ? str.charAt(0).toUpperCase() + str.slice(1) : str; }

    function showToast(msg, isError = false) {
        const toast = document.getElementById('toast');
        document.getElementById('toastText').textContent = msg;
        toast.style.background = isError ? '#dc2626' : 'var(--navy)';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Close modals on backdrop click
    ['bookingModal','blockModal','blockDetailModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) closeModal(id);
        });
    });
</script>
@include('admin.partials.realtime') 
</body>
</html>