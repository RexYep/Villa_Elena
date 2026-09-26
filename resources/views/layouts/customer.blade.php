<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Villa Elena Resort')</title>
    @include('partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/portal.js'])
    @stack('styles')
    <style>
        /* Toast kapag nagbago ang status ng ulat ng guest (issue.updated) */
        .issue-toast-wrap {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: calc(100vw - 32px);
        }

        .issue-toast {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 340px;
            max-width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #1d4ed8;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            color: #1f2937;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            animation: issueToastIn .25s ease-out;
            transition: opacity .3s, transform .3s;
        }

        .issue-toast:hover { color: #1f2937; }
        .issue-toast i { font-size: 18px; color: #1d4ed8; }
        .issue-toast.is-completed { border-left-color: #15803d; }
        .issue-toast.is-completed i { color: #15803d; }
        .issue-toast.is-cancelled { border-left-color: #64748b; }
        .issue-toast.is-cancelled i { color: #64748b; }
        .issue-toast.leaving { opacity: 0; transform: translateY(8px); }

        @keyframes issueToastIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .issue-toast { animation: none; transition: none; }
        }
    </style>
</head>
<body class="cust-shell">

@include('customer.partials.sidebar')

<main class="main">
@yield('content')
</main>

@stack('scripts')

{{-- Live notification bell: lights up the moment a new Notification row
     is created for this guest, no page refresh needed. --}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function () {
    const PUSHER_KEY     = '{{ config('broadcasting.connections.pusher.key') }}';
    const PUSHER_CLUSTER = '{{ config('broadcasting.connections.pusher.options.cluster', 'ap1') }}';
    const authUserId     = {{ Auth::id() ?? 'null' }};

    if (!PUSHER_KEY || !authUserId) return;

    const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        channelAuthorization: {
            endpoint: '{{ url('/broadcasting/auth') }}',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        },
    });

    const channel = pusher.subscribe('private-notifications.' + authUserId);

    // Nagbago ang status ng ulat ng guest (IssueReport::workStatusChanged).
    // Toast dito sa layout para lumabas saanmang page; ang listahan ay
    // kinukuha muli ng page mismo (customer:issues-changed), hindi mula
    // sa laman ng event.
    channel.bind('issue.updated', function (data) {
        showIssueToast(data);
        document.dispatchEvent(new CustomEvent('customer:issues-changed', { detail: data }));
    });

    const ISSUE_TOAST_ICON = { in_progress: 'bi-tools', completed: 'bi-check-circle-fill', cancelled: 'bi-x-circle' };
    const bookingUrlTemplate = @json(route('customer.bookings.show', '__ID__'));

    function showIssueToast(data) {
        let wrap = document.getElementById('issueToastWrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'issueToastWrap';
            wrap.className = 'issue-toast-wrap';
            wrap.setAttribute('role', 'status');
            wrap.setAttribute('aria-live', 'polite');
            document.body.appendChild(wrap);
        }

        const toast = document.createElement('a');
        toast.className = 'issue-toast is-' + String(data.status || '').replace('_', '-');
        toast.href = data.booking_id ? bookingUrlTemplate.replace('__ID__', data.booking_id) + '#report-issue' : '#';

        const icon = document.createElement('i');
        icon.className = 'bi ' + (ISSUE_TOAST_ICON[data.status] || 'bi-bell');
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.textContent = data.message || 'Your issue report was updated.';
        toast.append(icon, text);
        wrap.appendChild(toast);

        setTimeout(function () {
            toast.classList.add('leaving');
            setTimeout(function () { toast.remove(); }, 300);
        }, 8000);
    }

    channel.bind('notification.created', function () {
        // Ang kampana ay nasa bar ng telepono lang; ang bilang ay nasa
        // sidebar. Alinman sa dalawa ang nakikita, depende sa lapad —
        // kaya pareho silang ina-update, at wala ring reklamo kung wala
        // ang isa sa kanila.
        const link = document.getElementById('notifBellLink');
        if (link && !document.getElementById('notifDot')) {
            const dot = document.createElement('span');
            dot.className = 'notif-dot';
            dot.id = 'notifDot';
            link.appendChild(dot);
        }

        const count = document.getElementById('custNavUnread');
        if (count) {
            const current = parseInt(count.textContent, 10);
            const next = (count.hidden || isNaN(current)) ? 1 : current + 1;
            count.textContent = next > 99 ? '99+' : next;
            count.hidden = false;
        }
    });
})();
</script>
</body>
</html>
