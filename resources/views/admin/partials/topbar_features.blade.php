{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- SAVE AS: resources/views/admin/partials/topbar_features.blade.php --}}
{{-- Then add @include('admin.partials.topbar_features')              --}}
{{-- just before </body> in your dashboard/index.blade.php            --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}

{{-- Also REPLACE your existing topbar bell and search divs with these: --}}

{{--
FIND this in your topbar:
    <div class="topbar-btn">
        <i class="bi bi-bell"></i>
        <span class="badge-dot"></span>
    </div>
    <div class="topbar-btn">
        <i class="bi bi-search"></i>
    </div>

REPLACE WITH:
    <div class="topbar-btn" id="notifBtn" onclick="toggleNotif()" style="position:relative;cursor:pointer;">
        <i class="bi bi-bell"></i>
        @if($unreadCount > 0)
            <span class="badge-dot" id="notifDot"></span>
        @endif
    </div>
    <div class="topbar-btn" onclick="openSearch()" style="cursor:pointer;">
        <i class="bi bi-search"></i>
    </div>
--}}

{{-- ── Pass data from controller (add to DashboardController) ───── --}}
{{-- In DashboardController@index(), add before return view():

use App\Models\Notification;

$notifications = Notification::where('user_id', auth()->id())
    ->latest()
    ->take(8)
    ->get();
$unreadCount = Notification::where('user_id', auth()->id())
    ->where('is_read', 0)
    ->count();

// Add to compact():
// compact('stats', 'notifications', 'unreadCount', ...)
--}}

<style>
/* ── Notification Dropdown ── */
.notif-dropdown {
    position: absolute; top: calc(100% + 12px); right: 0;
    width: 360px; background: #fff; border-radius: 16px;
    border: 1px solid #E2E8F0; box-shadow: 0 16px 48px rgba(13,27,42,.15);
    z-index: 9999; display: none; overflow: hidden;
    animation: fadeSlideDown .2s ease;
}
.notif-dropdown.open { display: block; }
@keyframes fadeSlideDown {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
}
.notif-header {
    padding: 14px 18px; border-bottom: 1px solid #E2E8F0;
    display: flex; align-items: center; justify-content: space-between;
}
.notif-header-title {
    font-family: 'Playfair Display', serif; font-size: 15px;
    font-weight: 600; color: #0D1B2A;
}
.notif-mark-read {
    font-size: 11px; color: #C9A84C; cursor: pointer;
    background: none; border: none; font-family: 'DM Sans', sans-serif;
    font-weight: 600; transition: color .2s;
}
.notif-mark-read:hover { color: #0D1B2A; }
.notif-list { max-height: 340px; overflow-y: auto; }
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }
.notif-item {
    display: flex; gap: 12px; padding: 13px 18px;
    border-bottom: 1px solid #f8fafc; transition: background .15s;
    text-decoration: none; color: inherit;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: rgba(201,168,76,.05); }
.notif-icon-wrap {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.notif-item-msg {
    font-size: 12px; color: #6B7A8D; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.notif-item-time { font-size: 11px; color: #94a3b8; margin-top: 3px; }
.notif-unread-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #C9A84C; flex-shrink: 0; margin-top: 5px;
}
.notif-footer {
    padding: 11px 18px; border-top: 1px solid #E2E8F0; text-align: center;
}
.notif-footer a {
    font-size: 12px; color: #C9A84C; text-decoration: none; font-weight: 600;
}
.notif-empty {
    padding: 40px; text-align: center; color: #94a3b8;
}
.notif-empty i { font-size: 32px; display: block; margin-bottom: 8px; opacity: .4; }

@media (max-width: 480px) {
    .notif-dropdown { width: calc(100vw - 24px); right: -12px; }
}

/* ── Search Modal ── */
.search-overlay {
    display: none; position: fixed; inset: 0; z-index: 9998;
    background: rgba(13,27,42,.6); backdrop-filter: blur(6px);
    align-items: flex-start; justify-content: center; padding-top: 80px;
}
.search-overlay.open { display: flex; }
.search-box {
    background: #fff; border-radius: 18px; width: 620px;
    max-width: calc(100vw - 32px); overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,.25);
    animation: fadeSlideDown .2s ease;
}
.search-input-row {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; border-bottom: 1px solid #E2E8F0;
}
.search-input-row i { font-size: 18px; color: #6B7A8D; flex-shrink: 0; }
.search-input {
    flex: 1; border: none; outline: none; font-size: 16px;
    font-family: 'DM Sans', sans-serif; color: #1e293b; background: transparent;
}
.search-input::placeholder { color: #94a3b8; }
.search-close {
    background: #f1f5f9; border: none; border-radius: 7px; padding: 5px 10px;
    font-size: 12px; color: #6B7A8D; cursor: pointer; font-family: 'DM Sans', sans-serif;
    flex-shrink: 0;
}
.search-results { max-height: 400px; overflow-y: auto; padding: 8px 0; }
.search-results::-webkit-scrollbar { width: 4px; }
.search-results::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }
.search-section-label {
    padding: 8px 20px 4px; font-size: 10px; font-weight: 700;
    color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;
}
.search-result-item {
    display: flex; align-items: center; gap: 12px; padding: 10px 20px;
    transition: background .15s; text-decoration: none; color: inherit; cursor: pointer;
}
.search-result-item:hover { background: #f8fafc; }
.result-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.result-main { flex: 1; min-width: 0; }
.result-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.result-sub { font-size: 12px; color: #6B7A8D; margin-top: 1px; }
.result-badge {
    font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px;
    flex-shrink: 0; text-transform: uppercase;
}
.search-empty { padding: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.search-hints { padding: 12px 20px; border-top: 1px solid #f1f5f9; display: flex; gap: 8px; flex-wrap: wrap; }
.search-hint {
    background: #f8fafc; border: 1px solid #E2E8F0; border-radius: 7px;
    padding: 5px 12px; font-size: 12px; color: #6B7A8D; cursor: pointer;
    transition: all .2s;
}
.search-hint:hover { background: #0D1B2A; color: #fff; border-color: #0D1B2A; }

/* Loading spinner */
.search-spinner { padding: 30px; text-align: center; color: #94a3b8; font-size: 13px; }
/* Add inside the <style> tag: */
@keyframes spin { to { transform: rotate(360deg); } }
</style>

{{-- Notification bell + dropdown now live in layouts/admin.blade.php's
     topbar-right (needs to be a DOM sibling of the bell button itself so
     the dropdown's `position:absolute` anchors correctly under it — this
     file is included at the bottom of <body>, too far away for that). --}}

{{-- ── Search Modal ── --}}
<div class="search-overlay" id="searchOverlay" onclick="handleOverlayClick(event)">
    <div class="search-box">
        <div class="search-input-row">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="searchInput"
                placeholder="Search bookings, guests, properties..."
                oninput="handleSearch(this.value)"
                autocomplete="off">
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
    fetch('{{ route("admin.notifications.markRead") }}', {
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
    const wrapper  = document.getElementById('notifWrapper');
    const btn      = document.getElementById('notifBtn');
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown && !wrapper?.contains(e.target) && !btn?.contains(e.target)) {
        dropdown.classList.remove('open');
    }
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => renderResults(data, query))
        .catch(() => {
            results.innerHTML = `<div class="search-empty">Search unavailable. Please try again.</div>`;
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
                pending:'#fef9c3;color:#a16207', confirmed:'#dcfce7;color:#15803d',
                checked_in:'#dbeafe;color:#1d4ed8', checked_out:'#f1f5f9;color:#475569',
                cancelled:'#fee2e2;color:#dc2626'
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
        e.preventDefault(); openSearch();
    }
    if (e.key === 'Escape') closeSearch();
});


</script>