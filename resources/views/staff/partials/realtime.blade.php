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
.rt-fd-sticky { border-left-color: #ef4444; }
.rt-fd-sticky .rt-fd-icon { background: rgba(239,68,68,.2); color: #fca5a5; }
.rt-fd-dismiss {
    background: rgba(255,255,255,.1); border: none; color: #fff;
    width: 26px; height: 26px; border-radius: 7px; cursor: pointer; flex-shrink: 0;
}
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
    padding: 6px 14px; font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}
.rt-fd-handled {
    font-size: 13px; color: #6B7A8D; font-style: italic;
}
</style>

<div id="rt-fd-container"></div>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function () {
    const PUSHER_KEY     = '{{ env('PUSHER_APP_KEY') }}';
    const PUSHER_CLUSTER = '{{ env('PUSHER_APP_CLUSTER', 'ap1') }}';
    const currentStaff   = @json(Auth::user()->full_name ?? '');

    if (!PUSHER_KEY) { console.warn('Pusher key not set'); return; }

    const pusher  = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
    const channel = pusher.subscribe('staff-frontdesk');

    // Para sa mga page na kailangang makinig sa sariling event sa iisang
    // koneksiyon (hal. ang Availability grid) — hindi nagbubukas ng ikalawa.
    window.rtStaffChannel = channel;
    document.dispatchEvent(new CustomEvent('staff:realtime-ready', { detail: { channel } }));

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
        // Lang kapag isinara (done/fixed/cancelled): pagkatapos ng "Start"
        // ay dapat manatiling pwedeng pindutin ang "Done".
        const closesWork = /_(completed|cancelled|fixed)$/.test(data.action || '');
        if (data.task_id && closesWork) {
            disableForms('.task-form-' + data.task_id, data.actor);
        }
        if (data.report_id && closesWork) {
            disableForms('.report-form-' + data.report_id, data.actor);
        }

        // Ang mga ulat ng problema (issue_*) ay live na sa frontdesk — ang
        // listahan, banner at bilang ay kinukuha muli sa `issues.changed`
        // — kaya walang "refresh to see it" para sa mga iyon.
        if (/^issue_/.test(data.action || '')) return;

        // Full row/stat data (counts, new pending bookings, property grid)
        // isn't patched live to avoid duplicating the whole page's Blade
        // logic in JS — surface a lightweight prompt instead.
        showRefreshBanner(NEW_WORK[data.action]);
    });

    // Bagong gawain para sa staff (v7.11) na HINDI pa live sa page — kaya
    // may "refresh" na banner.
    const NEW_WORK = {
        task_assigned:  'The admin sent a new task — refresh to see it.',
    };

    // Ang toast ay nananatili hanggang isara — ang frontdesk ay isang
    // monitor na hindi laging tinitingnan, at ang 6 na segundong toast ay
    // madaling makaligtaan. Ang bagong ulat ay sticky pa rin kahit live
    // na ang listahan: ang toast ang pumupukaw ng pansin.
    const STICKY = ['issue_reported', 'task_assigned'];

    function showFdToast(data) {
        const sticky = STICKY.includes(data.action);
        const container = document.getElementById('rt-fd-container');
        const toast = document.createElement('div');
        toast.className = 'rt-fd-toast' + (sticky ? ' rt-fd-sticky' : '');
        toast.innerHTML = `
            <div class="rt-fd-icon"><i class="bi bi-${sticky ? 'bell-fill' : 'lightning-charge'}"></i></div>
            <div class="rt-fd-body">${escapeHtml(data.message)}</div>
            ${sticky ? '<button type="button" class="rt-fd-dismiss" aria-label="Dismiss">✕</button>' : ''}
        `;
        container.appendChild(toast);

        const remove = () => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        };
        if (sticky) {
            toast.querySelector('.rt-fd-dismiss').addEventListener('click', remove);
        } else {
            setTimeout(remove, 6000);
        }
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
    function showRefreshBanner(text) {
        const banner = document.getElementById('rt-fd-banner');
        if (!banner) return;
        // Ang mensahe tungkol sa bagong gawain ay pumapalit sa pangkalahatang
        // "Another staff member made changes" — hindi staff ang nagpadala.
        if (text) {
            const label = banner.querySelector('span');
            if (label) label.innerHTML = '<i class="bi bi-bell-fill me-1"></i> ' + escapeHtml(text);
        }
        if (bannerShown) return;
        bannerShown = true;
        banner.style.display = 'flex';
    }
    window.rtFdRefresh = function () { window.location.reload(); };
})();
</script>
