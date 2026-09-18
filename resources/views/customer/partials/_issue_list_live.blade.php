<script>
    // Live na status ng mga ulat ng guest. Ang server ang nagre-render
    // (customer.bookings.issues.index, parehong partial ng page) — walang
    // kopya ng listahan dito. Signal: `customer:issues-changed` mula sa
    // customer layout (`issue.updated` sa private channel ng guest);
    // salo: bawat 60s.
    (function() {
        const el = document.getElementById('issueListLive');
        if (!el) return;

        const URL = el.dataset.url;
        let timer = null;
        let inFlight = false;

        function refresh() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                if (inFlight) return;
                inFlight = true;
                fetch(URL, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                    .then(res => { if (!res.ok) throw new Error(res.status); return res.json(); })
                    .then(data => {
                        if (el.innerHTML.trim() !== data.html.trim()) el.innerHTML = data.html;
                        // Ang card sa dashboard ay nakatago kapag wala pang ulat.
                        el.hidden = !data.count;
                    })
                    .catch(() => {})
                    .finally(() => { inFlight = false; });
            }, 300);
        }

        document.addEventListener('customer:issues-changed', refresh);
        setInterval(() => { if (!document.hidden) refresh(); }, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    })();
</script>
