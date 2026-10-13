{{-- Live front-desk sync: lets concurrent staff see each other's
     check-in/check-out/payment/housekeeping actions instantly, so two
     people can't act on the same stale booking or task at once. --}}

{{-- The toast's styles are in resources/css/staff.css (REALTIME TOAST). --}}

<div id="rt-fd-container"></div>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function () {
    const PUSHER_KEY     = '{{ config('broadcasting.connections.pusher.key') }}';
    const PUSHER_CLUSTER = '{{ config('broadcasting.connections.pusher.options.cluster', 'ap1') }}';
    const currentStaff   = @json(Auth::user()->full_name ?? '');

    if (!PUSHER_KEY) { console.warn('Pusher key not set'); return; }

    // channelAuthorization is required now that the channel is private:
    // Pusher POSTs here first and routes/channels.php checks this user is
    // staff or admin. The frontdesk messages name guests and what was done
    // for them, and as a plain `staff-frontdesk` they were readable by
    // anyone holding the app key — a CLIENT credential that is rendered
    // into pages, including the public property page.
    const pusher  = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        channelAuthorization: {
            endpoint: '{{ url('/broadcasting/auth') }}',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
        },
    });
    const channel = pusher.subscribe('private-staff-frontdesk');

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

        // There is no "refresh to see the latest" banner any more. It dated
        // from when the page was drawn once; every part of the frontdesk now
        // refetches itself from the server on this same event (see the two
        // live blocks in staff/frontdesk.blade.php), so the banner was
        // asking for a reload of a page that had already updated.
    });

    // The count beside "Frontdesk" in the sidebar. The frontdesk page
    // updates it from its own Today refetch; every other staff page hears
    // the same two events and has nothing else to refetch, so it asks for
    // the number alone. No timer here: a missed event is corrected by the
    // next page load, which draws the count on the server.
    const navBadge = document.getElementById('fdNavBadge');
    if (navBadge && !document.getElementById('fdTodayLive')) {
        const BADGE_URL = @json(route('staff.frontdesk.badge'));
        let badgeTimer = null;
        const refreshBadge = function () {
            clearTimeout(badgeTimer);
            badgeTimer = setTimeout(function () {
                fetch(BADGE_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(res => { if (!res.ok) throw new Error(res.status); return res.json(); })
                    .then(data => {
                        navBadge.textContent = data.count;
                        navBadge.hidden = !data.count;
                    })
                    .catch(() => {});
            }, 400);
        };
        channel.bind('availability.changed', refreshBadge);
        channel.bind('frontdesk.updated', refreshBadge);
    }

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
})();
</script>
