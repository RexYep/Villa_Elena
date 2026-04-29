{{-- ============================================================
     SAVE AS: resources/views/admin/partials/realtime.blade.php
     @include this at the BOTTOM of any admin blade (before </body>)
     ============================================================ --}}

{{-- Pusher + Laravel Echo --}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<style>
/* ── Notification Toast Container ── */
#rt-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    max-width: 360px;
}

.rt-toast {
    background: #0d1b2a;
    color: #fff;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,.25);
    animation: rtSlideIn .35s cubic-bezier(.34,1.56,.64,1);
    border-left: 4px solid #c9a84c;
    cursor: pointer;
    transition: opacity .3s, transform .3s;
    min-width: 300px;
}
.rt-toast:hover { opacity: .92; }
.rt-toast.removing { opacity: 0; transform: translateX(40px); }

.rt-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}
.rt-icon.booking  { background: rgba(59,130,246,.2);  color: #3b82f6; }
.rt-icon.payment  { background: rgba(16,185,129,.2);  color: #10b981; }
.rt-icon.property { background: rgba(201,168,76,.2);  color: #c9a84c; }

.rt-body { flex: 1; min-width: 0; }
.rt-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.rt-sub   { font-size: 11px; color: rgba(255,255,255,.55); line-height: 1.4; }
.rt-time  { font-size: 10px; color: rgba(255,255,255,.35); margin-top: 4px; }

.rt-close {
    background: none; border: none; color: rgba(255,255,255,.4);
    font-size: 16px; cursor: pointer; padding: 0; line-height: 1;
    flex-shrink: 0; margin-top: -2px;
}
.rt-close:hover { color: #fff; }

@keyframes rtSlideIn {
    from { opacity:0; transform: translateX(60px) scale(.9); }
    to   { opacity:1; transform: translateX(0) scale(1); }
}

/* ── Live Indicator Dot ── */
.live-dot {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #10b981;
    font-weight: 500;
}
.live-dot::before {
    content: '';
    width: 7px; height: 7px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
    display: inline-block;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .5; transform: scale(.85); }
}

/* ── Notification Bell Badge ── */
#rt-bell-badge {
    position: absolute;
    top: -4px; right: -4px;
    background: #ef4444;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    border-radius: 10px;
    min-width: 16px;
    height: 16px;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}
</style>

{{-- Toast Container --}}
<div id="rt-container"></div>

<script>
(function () {
    const PUSHER_KEY     = '{{ env('PUSHER_APP_KEY') }}';
    const PUSHER_CLUSTER = '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}';

    if (!PUSHER_KEY) { console.warn('Pusher key not set'); return; }

    // ── Init Pusher ──────────────────────────────────────────────
    const pusher  = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
    const channel = pusher.subscribe('admin-dashboard');

    // Show live dot somewhere if element exists
    const liveEl = document.getElementById('rt-live-indicator');
    pusher.connection.bind('connected', () => {
        if (liveEl) liveEl.style.display = 'inline-flex';
        console.log('✅ Pusher connected');
    });

    let unreadCount = 0;

    // ── New Booking ──────────────────────────────────────────────
    channel.bind('booking.created', function (data) {
        showToast({
            type:    'booking',
            icon:    'bi-calendar-check',
            title:   '🎉 New Booking — ' + data.booking_ref,
            sub:     data.guest + ' · ' + data.property + '<br>' + data.check_in + ' → ' + data.check_out,
            time:    data.created_at,
            link:    '/admin/bookings/' + data.booking_id,
            amount:  data.total_amount,
        });

        // Update dashboard stats if elements exist
        updateStat('rt-total-bookings', 1);
        updateStat('rt-pending-count',  data.status === 'pending' ? 1 : 0);
        flashElement('rt-bookings-card');
        bumpBell();
    });

    // ── Payment Received ─────────────────────────────────────────
    channel.bind('payment.received', function (data) {
        showToast({
            type:  'payment',
            icon:  'bi-cash-stack',
            title: '💳 Payment — ₱' + parseFloat(data.amount).toLocaleString('en-PH', {minimumFractionDigits:2}),
            sub:   data.guest + ' · ' + data.booking_ref + ' · ' + data.payment_method.toUpperCase(),
            time:  data.created_at,
            link:  '/admin/payments',
        });

        updateRevenue('rt-today-revenue', data.amount);
        flashElement('rt-revenue-card');
        bumpBell();
    });

    // ── Property Status Changed ───────────────────────────────────
    channel.bind('property.status.changed', function (data) {
        const statusLabels = {
            available:   '🟢 Available',
            occupied:    '🔴 Occupied',
            maintenance: '🟡 Maintenance'
        };
        showToast({
            type:  'property',
            icon:  'bi-house-door',
            title: data.property_name + ' status changed',
            sub:   (statusLabels[data.old_status] || data.old_status) + ' → ' + (statusLabels[data.new_status] || data.new_status),
            time:  data.updated_at,
            link:  '/admin/properties',
        });

        // Update property status pill if visible on page
        const pill = document.querySelector(`[data-property-id="${data.property_id}"] .property-status-pill`);
        if (pill) {
            pill.className = 'property-status-pill status-' + data.new_status;
            pill.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
        }
        bumpBell();
    });

    // ── Show Toast ───────────────────────────────────────────────
    function showToast({ type, icon, title, sub, time, link }) {
        const container = document.getElementById('rt-container');
        const toast = document.createElement('div');
        toast.className = 'rt-toast';
        toast.innerHTML = `
            <div class="rt-icon ${type}"><i class="bi ${icon}"></i></div>
            <div class="rt-body">
                <div class="rt-title">${title}</div>
                <div class="rt-sub">${sub}</div>
                <div class="rt-time">${time}</div>
            </div>
            <button class="rt-close" onclick="this.closest('.rt-toast').remove()">×</button>
        `;
        if (link) {
            toast.addEventListener('click', function(e) {
                if (e.target.classList.contains('rt-close')) return;
                window.location.href = link;
            });
        }
        container.appendChild(toast);

        // Auto-remove after 8 seconds
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 8000);
    }

    // ── Stat Helpers ─────────────────────────────────────────────
    function updateStat(id, increment) {
        const el = document.getElementById(id);
        if (!el) return;
        const current = parseInt(el.textContent.replace(/[^0-9]/g, '')) || 0;
        el.textContent = current + increment;
    }

    function updateRevenue(id, amount) {
        const el = document.getElementById(id);
        if (!el) return;
        const current = parseFloat(el.dataset.value || 0);
        const newVal  = current + parseFloat(amount);
        el.dataset.value = newVal;
        el.textContent = '₱' + newVal.toLocaleString('en-PH', {minimumFractionDigits:2});
    }

    function flashElement(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.transition = 'box-shadow .3s';
        el.style.boxShadow  = '0 0 0 3px rgba(201,168,76,.5)';
        setTimeout(() => el.style.boxShadow = '', 1500);
    }

    function bumpBell() {
        unreadCount++;
        const badge = document.getElementById('rt-bell-badge');
        if (badge) {
            badge.style.display = 'flex';
            badge.textContent   = unreadCount > 9 ? '9+' : unreadCount;
        }
    }

    // Expose reset for when user opens notification panel
    window.rtResetBell = function() {
        unreadCount = 0;
        const badge = document.getElementById('rt-bell-badge');
        if (badge) badge.style.display = 'none';
    };

})();
</script>