import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/portal.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Sidebar ng customer portal. Sa desktop ay laging nakikita ito at
// walang ginagawa ang code na ito — ang hamburger lang ang lumalabas sa
// ilalim ng 992px, kasama ang backdrop.
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('custSidebarToggle');
    const sidebar = document.getElementById('custSidebar');
    const backdrop = document.getElementById('custBackdrop');
    if (!toggle || !sidebar || !backdrop) return;

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('i').className = open ? 'bi bi-x-lg' : 'bi bi-list';
    }

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
    backdrop.addEventListener('click', () => setOpen(false));

    // Ang pag-alis ng pahina ay hindi laging nangyayari kapag pinindot ang
    // isang link (may `#hash` ang Settings), kaya isinasara natin ito.
    sidebar.querySelectorAll('a').forEach(el => el.addEventListener('click', () => setOpen(false)));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) setOpen(false);
    });
});
