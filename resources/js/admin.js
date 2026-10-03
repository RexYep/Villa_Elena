import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/admin.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !sidebar || !backdrop) return;

    // ── Sidebar scroll persistence ────────────────────────────────────────
    // The sidebar is overflow-y: auto, so the browser resets its scrollTop
    // to 0 on every full-page navigation (clicking Settings → new page load
    // → sidebar snaps back to Dashboard). We save the position before the
    // page unloads and restore it immediately on DOMContentLoaded so the
    // user's scroll context survives navigation.
    const SCROLL_KEY = 'admin_sidebar_scroll';

    const savedScroll = sessionStorage.getItem(SCROLL_KEY);
    if (savedScroll !== null) {
        sidebar.scrollTop = parseInt(savedScroll, 10);
    }

    sidebar.addEventListener('scroll', () => {
        sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop);
    });

    // ── Mobile toggle ─────────────────────────────────────────────────────
    const closeSidebar = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
    };

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('open');
    });

    backdrop.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('a.nav-item-custom').forEach(link => {
        link.addEventListener('click', closeSidebar);
    });
});
