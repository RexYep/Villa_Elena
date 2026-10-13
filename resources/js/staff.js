import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/staff.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Page messages (staff/partials/flash.blade.php). Delegated, so a message
// added after load can be dismissed too.
document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-alert-close]');
    if (close) close.closest('.alert')?.remove();
});

// A form marked data-submit-once: its button says what is happening and a
// second click does nothing. The server is the real guard against a repeat
// (the slot lock for a walk-in, the look-alike check for a payment); this
// is so a slow response does not look like a click that was missed.
// Listens on `submit`, which the browser only fires once the form's own
// validation has passed.
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-submit-once')) return;

    if (form.dataset.submitting) {
        e.preventDefault();
        return;
    }
    form.dataset.submitting = '1';

    const button = form.querySelector('[type="submit"]');
    if (button) {
        button.setAttribute('aria-busy', 'true');
        if (button.dataset.busyLabel) {
            button.dataset.idleHtml = button.innerHTML;
            button.textContent = button.dataset.busyLabel;
        }
    }
});

// Back from the browser's page cache: the form is usable again.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        delete form.dataset.submitting;
        const button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.removeAttribute('aria-busy');
        if (button.dataset.idleHtml) button.innerHTML = button.dataset.idleHtml;
    });
});

document.addEventListener('DOMContentLoaded', () => {
    // Only a success message opts in to hiding itself; errors and warnings
    // stay until dismissed.
    const autohide = document.querySelectorAll('[data-alert-autohide]');
    if (autohide.length) {
        setTimeout(() => autohide.forEach((alert) => alert.remove()), 8000);
    }

    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !sidebar || !backdrop) return;

    const closeSidebar = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
    };

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('open');
    });

    backdrop.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('a.nav-item').forEach(link => {
        link.addEventListener('click', closeSidebar);
    });
});
