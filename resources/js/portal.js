import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/portal.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('accountNavToggle');
    const links = document.getElementById('accountNavLinks');
    if (!toggle || !links) return;

    toggle.addEventListener('click', () => {
        const open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('i').className = open ? 'bi bi-x-lg' : 'bi bi-list';
    });

    links.querySelectorAll('a, button').forEach(el => {
        el.addEventListener('click', () => {
            links.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.querySelector('i').className = 'bi bi-list';
        });
    });
});
