{{-- Live front-desk sync: lets concurrent staff see each other's
     check-in/check-out/payment/housekeeping actions instantly, so two
     people can't act on the same stale booking or task at once. --}}

<style>
#rt-fd-container {
    position: fixed; bottom: 24px; right: 24px; z-index: 99999;
    display: flex; flex-direction: column-reverse; gap: 10px; max-width: 360px;
}
.rt-fd-toast {
    background: #0d1b2a; color: #fff; border-radius: 14px; padding: 14px 16px;
    display: flex; align-items: flex-start; gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,.25);
    animation: rtFdSlideIn .35s cubic-bezier(.34,1.56,.64,1);
    border-left: 4px solid #c9a84c; min-width: 300px;
    transition: opacity .3s, transform .3s;
}
.rt-fd-toast.removing { opacity: 0; transform: translateX(40px); }
.rt-fd-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; background: rgba(201,168,76,.2); color: #c9a84c;
}
.rt-fd-body { flex: 1; min-width: 0; font-size: 12.5px; line-height: 1.5; }
@keyframes rtFdSlideIn {
    from { opacity: 0; transform: translateX(60px) scale(.9); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}

#rt-fd-banner {
    display: none; position: sticky; top: 0; z-index: 500;
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
    border-radius: 10px; padding: 10px 16px; margin-bottom: 16px;
    font-size: 12.5px; font-weight: 500;
    align-items: center; justify-content: space-between; gap: 12px;
}
#rt-fd-banner button {
    background: #d97706; color: #fff; border: none; border-radius: 7px;
    padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}
.rt-fd-handled {
    font-size: 11px; color: #6B7A8D; font-style: italic;
}
</style>

<div id="rt-fd-container"></div>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function () {
    const PUSHER_KEY     = '{{ env('PUSHER_APP_KEY') }}';
    const PUSHER_CLUSTER = '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}';
    const currentStaff   = @json(auth()->user()->full_name ?? '');

    if (!PUSHER_KEY) { console.warn('Pusher key not set'); return; }

    const pusher  = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
    const channel = pusher.subscribe('staff-frontdesk');

    channel.bind('frontdesk.updated', function (data) {
        showFdToast(data);

        // Prevent a second staff member from acting on a booking/task
        // someone else just handled.
        if (data.booking_id && (data.action === 'checkin' || data.action === 'checkout')) {
            disableForms('.checkout-form-' + data.booking_id, data.actor);
            if (data.action === 'checkin') {
                disableForm('#checkinForm_' + data.booking_id, data.actor);
            }
        }
        if (data.task_id && (data.action === 'task_started' || data.action === 'task_completed')) {
            disableForms('.task-form-' + data.task_id, data.actor);
        }

        // Full row/stat data (counts, new pending bookings, property grid)
        // isn't patched live to avoid duplicating the whole page's Blade
        // logic in JS — surface a lightweight prompt instead.
        showRefreshBanner();
    });

    function showFdToast(data) {
        const container = document.getElementById('rt-fd-container');
        const toast = document.createElement('div');
        toast.className = 'rt-fd-toast';
        toast.innerHTML = `
            <div class="rt-fd-icon"><i class="bi bi-lightning-charge"></i></div>
            <div class="rt-fd-body">${escapeHtml(data.message)}</div>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 6000);
    }

    function disableForm(selector, actor) {
        const form = document.querySelector(selector);
        if (!form || form.dataset.rtHandled) return;
        markHandled(form, actor);
    }

    function disableForms(selector, actor) {
        document.querySelectorAll(selector).forEach(form => {
            if (form.dataset.rtHandled) return;
            markHandled(form, actor);
        });
    }

    function markHandled(form, actor) {
        form.dataset.rtHandled = '1';
        const btn = form.querySelector('button');
        if (btn) btn.disabled = true;
        const note = document.createElement('div');
        note.className = 'rt-fd-handled';
        note.textContent = 'Handled by ' + (actor || 'another staff');
        form.after(note);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    let bannerShown = false;
    function showRefreshBanner() {
        const banner = document.getElementById('rt-fd-banner');
        if (!banner || bannerShown) return;
        bannerShown = true;
        banner.style.display = 'flex';
    }
    window.rtFdRefresh = function () { window.location.reload(); };
})();
</script>
