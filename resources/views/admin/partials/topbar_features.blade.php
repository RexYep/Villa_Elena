<style>
    /* ── Notification Dropdown ── */
    /* Anchored to the bell, but the bell is NOT at the right edge of the
       topbar — the logout button follows it — so the panel grows leftwards
       from a point ~120px in from the screen edge. Any width cap therefore
       has to be measured against the viewport, not against the wrapper.
       Below 640px the anchoring is dropped entirely (media query at the
       end of this block). */
    .notif-dropdown {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: 360px;
        max-width: calc(100vw - 24px);
        max-height: calc(100vh - var(--topbar-h) - 24px);
        background: #fff;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 16px 48px rgba(13, 27, 42, .15);
        z-index: 9999;
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: fadeSlideDown .2s ease;
    }

    /* flex, not block: the header and footer stay pinned while .notif-list
       takes the leftover height and scrolls, so "View all notifications"
       stays reachable however short the screen is. */
    .notif-dropdown.open {
        display: flex;
    }

    @keyframes fadeSlideDown {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notif-header {
        flex-shrink: 0;
        padding: 14px 18px;
        border-bottom: 1px solid #E2E8F0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .notif-header-title {
        font-family: 'Cormorant Garamond', serif;
        font-size: 15px;
        font-weight: 600;
        color: #0D1B2A;
    }

    .notif-mark-read {
        font-size: 13px;
        color: #C9A84C;
        cursor: pointer;
        background: none;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        transition: color .2s;
    }

    .notif-mark-read:hover {
        color: #0D1B2A;
    }

    .notif-list {
        flex: 1 1 auto;
        min-height: 0;
        max-height: 340px;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    .notif-list::-webkit-scrollbar {
        width: 4px;
    }

    .notif-list::-webkit-scrollbar-thumb {
        background: #E2E8F0;
        border-radius: 4px;
    }

    .notif-item {
        display: flex;
        gap: 12px;
        padding: 13px 18px;
        border-bottom: 1px solid #f8fafc;
        transition: background .15s;
        text-decoration: none;
        color: inherit;
    }

    .notif-item:last-child {
        border-bottom: none;
    }

    .notif-item:hover {
        background: #f8fafc;
    }

    .notif-item.unread {
        background: rgba(201, 168, 76, .05);
    }

    .notif-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }

    .notif-item-body {
        flex: 1;
        min-width: 0;
    }

    .notif-item-title {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        overflow-wrap: anywhere;
    }

    /* Two clamped lines instead of one nowrap line. These messages lead with
       a booking ref, so a single ellipsised line on a phone showed the ref
       and none of what actually happened to it. Also drops below the title's
       13px — the message was rendering larger than its own heading. */
    .notif-item-msg {
        font-size: 13px;
        line-height: 1.45;
        color: #6B7A8D;
        margin-top: 2px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        overflow-wrap: anywhere;
    }

    .notif-item-time {
        font-size: 13px;
        color: #94a3b8;
        margin-top: 3px;
    }

    .notif-unread-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #C9A84C;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .notif-footer {
        flex-shrink: 0;
        padding: 11px 18px;
        border-top: 1px solid #E2E8F0;
        text-align: center;
    }

    .notif-footer a {
        font-size: 14px;
        color: #C9A84C;
        text-decoration: none;
        font-weight: 600;
    }

    .notif-empty {
        padding: 40px;
        text-align: center;
        color: #94a3b8;
    }

    .notif-empty i {
        font-size: 32px;
        display: block;
        margin-bottom: 8px;
        opacity: .4;
    }

    /* Phones: stop anchoring to the bell. `right: -12px` on an absolutely
       positioned panel is measured from the bell, which sits ~120px in from
       the screen edge, so a calc(100vw - 24px) panel started off the left of
       the viewport — the whole icon column and the first characters of every
       line were clipped away, and the page picked up a horizontal scrollbar.
       Fixed positioning pins it to the viewport instead, where these 12px
       gutters actually mean 12px. */
    @media (max-width: 640px) {
        .notif-dropdown {
            position: fixed;
            top: calc(var(--topbar-h) + 8px);
            left: 12px;
            right: 12px;
            width: auto;
            max-width: none;
            max-height: calc(100vh - var(--topbar-h) - 20px);
            border-radius: 14px;
        }

        /* the panel's own max-height is the limit now, not a fixed 340px */
        .notif-list {
            max-height: none;
        }

        .notif-item {
            gap: 10px;
            padding: 12px 14px;
        }

        .notif-header,
        .notif-footer {
            padding-left: 14px;
            padding-right: 14px;
        }

        .notif-empty {
            padding: 32px 20px;
        }
    }

    /* ── Search Modal ── */
    .search-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9998;
        background: rgba(13, 27, 42, .6);
        backdrop-filter: blur(6px);
        align-items: flex-start;
        justify-content: center;
        padding-top: 80px;
    }

    .search-overlay.open {
        display: flex;
    }

    .search-box {
        background: #fff;
        border-radius: 18px;
        width: 620px;
        max-width: calc(100vw - 32px);
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0, 0, 0, .25);
        animation: fadeSlideDown .2s ease;
    }

    .search-input-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #E2E8F0;
    }

    .search-input-row i {
        font-size: 18px;
        color: #6B7A8D;
        flex-shrink: 0;
    }

    .search-input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 16px;
        font-family: 'DM Sans', sans-serif;
        color: #1e293b;
        background: transparent;
    }

    .search-input::placeholder {
        color: #94a3b8;
    }

    .search-close {
        background: #f1f5f9;
        border: none;
        border-radius: 7px;
        padding: 5px 10px;
        font-size: 14px;
        color: #6B7A8D;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        flex-shrink: 0;
    }

    .search-results {
        max-height: 400px;
        overflow-y: auto;
        padding: 8px 0;
    }

    .search-results::-webkit-scrollbar {
        width: 4px;
    }

    .search-results::-webkit-scrollbar-thumb {
        background: #E2E8F0;
        border-radius: 4px;
    }

    .search-section-label {
        padding: 8px 20px 4px;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .search-result-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        transition: background .15s;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }

    .search-result-item:hover {
        background: #f8fafc;
    }

    .result-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .result-main {
        flex: 1;
        min-width: 0;
    }

    .result-title {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
    }

    .result-sub {
        font-size: 14px;
        color: #6B7A8D;
        margin-top: 1px;
    }

    .result-badge {
        font-size: 12px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        flex-shrink: 0;
        text-transform: uppercase;
    }

    .search-empty {
        padding: 40px;
        text-align: center;
        color: #94a3b8;
        font-size: 14px;
    }

    .search-hints {
        padding: 12px 20px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .search-hint {
        background: #f8fafc;
        border: 1px solid #E2E8F0;
        border-radius: 7px;
        padding: 5px 12px;
        font-size: 14px;
        color: #6B7A8D;
        cursor: pointer;
        transition: all .2s;
    }

    .search-hint:hover {
        background: #0D1B2A;
        color: #fff;
        border-color: #0D1B2A;
    }

    /* Loading spinner */
    .search-spinner {
        padding: 30px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    /* Add inside the <style> tag: */
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>



{{-- ── Search Modal ── --}}
<div class="search-overlay" id="searchOverlay" onclick="handleOverlayClick(event)">
    <div class="search-box">
        <div class="search-input-row">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search bookings, guests, properties..."
                oninput="handleSearch(this.value)" autocomplete="off">
            <button class="search-close" onclick="closeSearch()">ESC</button>
        </div>
        <div class="search-results" id="searchResults">
            <div class="search-hints">
                <span class="search-hint" onclick="quickSearch('pending')">⏳ Pending bookings</span>
                <span class="search-hint" onclick="quickSearch('checked_in')">🏠 Checked in</span>
                <span class="search-hint" onclick="quickSearch('VE-')">🔖 Booking ref</span>
            </div>
        </div>
    </div>
</div>

<script>
    // ── NOTIFICATIONS ──────────────────────────────────────────────
    function toggleNotif() {
        const dropdown = document.getElementById('notifDropdown');
        dropdown.classList.toggle('open');
    }

    function markAllRead() {
        fetch('{{ route('admin.notifications.markRead') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(() => {
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
            document.querySelectorAll('.notif-unread-dot').forEach(el => el.remove());
            const dot = document.getElementById('notifDot');
            if (dot) dot.remove();
        });
    }

    // Close notif dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notifWrapper');
        const btn = document.getElementById('notifBtn');
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown && !wrapper?.contains(e.target) && !btn?.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // The topbar is z-index 900 and the mobile sidebar backdrop is 999, so an
    // open panel would be buried behind the drawer rather than closed by it —
    // the drawer's own toggle lives in admin.js and knows nothing about this.
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('notifDropdown')?.classList.remove('open');
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') document.getElementById('notifDropdown')?.classList.remove('open');
    });

    // ── SEARCH ─────────────────────────────────────────────────────
    function openSearch() {
        document.getElementById('searchOverlay').classList.add('open');
        setTimeout(() => document.getElementById('searchInput').focus(), 100);
    }

    function closeSearch() {
        document.getElementById('searchOverlay').classList.remove('open');
        document.getElementById('searchInput').value = '';
        document.getElementById('searchResults').innerHTML = `
        <div class="search-hints">
            <span class="search-hint" onclick="quickSearch('pending')">⏳ Pending bookings</span>
            <span class="search-hint" onclick="quickSearch('checked_in')">🏠 Checked in</span>
            <span class="search-hint" onclick="quickSearch('VE-')">🔖 Booking ref</span>
        </div>`;
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('searchOverlay')) closeSearch();
    }

    function quickSearch(term) {
        document.getElementById('searchInput').value = term;
        handleSearch(term);
    }

    let searchTimer;

    function handleSearch(query) {
        clearTimeout(searchTimer);
        const results = document.getElementById('searchResults');
        if (!query || query.length < 2) {
            results.innerHTML = `<div class="search-hints">
            <span class="search-hint" onclick="quickSearch('pending')">⏳ Pending bookings</span>
            <span class="search-hint" onclick="quickSearch('checked_in')">🏠 Checked in</span>
            <span class="search-hint" onclick="quickSearch('VE-')">🔖 Booking ref</span>
        </div>`;
            return;
        }
        results.innerHTML = `<div class="search-spinner">⏳ Searching...</div>`;
        searchTimer = setTimeout(() => {
            fetch(`{{ route('admin.search') }}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(data => renderResults(data, query))
                .catch(() => {
                    results.innerHTML =
                        `<div class="search-empty">Search unavailable. Please try again.</div>`;
                });
        }, 350);
    }

    function renderResults(data, query) {
        const baseUrl = '{{ url('') }}';
        const results = document.getElementById('searchResults');
        let html = '';

        if (data.bookings?.length) {
            html += `<div class="search-section-label">📅 Bookings</div>`;
            data.bookings.forEach(b => {
                const statusColors = {
                    pending: '#fef9c3;color:#a16207',
                    confirmed: '#dcfce7;color:#15803d',
                    checked_in: '#dbeafe;color:#1d4ed8',
                    checked_out: '#f1f5f9;color:#475569',
                    cancelled: '#fee2e2;color:#dc2626'
                };
                const sc = statusColors[b.status] || '#f1f5f9;color:#475569';
                html += `<a href="${baseUrl}/admin/bookings/${b.id}" class="search-result-item">
                <div class="result-icon" style="background:#f0f4ff;color:#4f46e5;"><i class="bi bi-calendar3"></i></div>
                <div class="result-main">
                    <div class="result-title">${b.booking_ref}</div>
                    <div class="result-sub">${b.guest} · ${b.property}</div>
                </div>
                <span class="result-badge" style="background:${sc};">${b.status.replace('_',' ')}</span>
            </a>`;
            });
        }

        if (data.guests?.length) {
            html += `<div class="search-section-label">👤 Guests</div>`;
            data.guests.forEach(g => {
                html += `<a href="${baseUrl}/admin/users/${g.id}" class="search-result-item">
                <div class="result-icon tag-amber"><i class="bi bi-person"></i></div>
                <div class="result-main">
                    <div class="result-title">${g.name}</div>
                    <div class="result-sub">${g.email}</div>
                </div>
                <span class="result-badge" style="background:#f1f5f9;color:#475569;">${g.bookings} booking${g.bookings!=1?'s':''}</span>
            </a>`;
            });
        }

        if (data.properties?.length) {
            html += `<div class="search-section-label">🏠 Properties</div>`;
            data.properties.forEach(p => {
                const sc = p.status === 'available' ? '#dcfce7;color:#15803d' : '#fee2e2;color:#dc2626';
                html += `<a href="${baseUrl}/admin/properties/${p.id}/edit"class="search-result-item">
                <div class="result-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-house"></i></div>
                <div class="result-main">
                    <div class="result-title">${p.name}</div>
                    <div class="result-sub">${p.type} · Max ${p.capacity} guests</div>
                </div>
                <span class="result-badge" style="background:${sc};">${p.status}</span>
            </a>`;
            });
        }

        if (!html) {
            html = `<div class="search-empty">No results found for "<strong>${query}</strong>"</div>`;
        }

        results.innerHTML = html;
    }

    // Keyboard shortcut: Ctrl+K or / to open search
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && document.activeElement.tagName !== 'INPUT')) {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape') closeSearch();
    });
</script>
