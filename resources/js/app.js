import './bootstrap';

const toggle = document.querySelector('[data-menu-toggle]');
const sidebar = document.querySelector('#sidebar');
function closeMenu() {
    sidebar?.classList.remove('is-open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Buka navigasi');
}
toggle?.addEventListener('click', () => {
    const opened = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(opened));
    toggle.setAttribute('aria-label', opened ? 'Tutup navigasi' : 'Buka navigasi');
});
document.querySelector('[data-menu-close]')?.addEventListener('click', () => { closeMenu(); toggle?.focus(); });
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) { closeMenu(); toggle?.focus(); }
});
document.addEventListener('click', event => {
    if (sidebar?.classList.contains('is-open') && !sidebar.contains(event.target) && !toggle?.contains(event.target)) closeMenu();
});
// Keep the collapsed navigation out of the keyboard order.
const media = matchMedia('(max-width: 1023px)');
function syncSidebar() { if (sidebar) sidebar.inert = media.matches && !sidebar.classList.contains('is-open'); }
new MutationObserver(syncSidebar).observe(sidebar ?? document.createElement('div'), { attributes: true, attributeFilter: ['class'] });
media.addEventListener('change', syncSidebar);
syncSidebar();

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        const button = event.submitter;
        if (button?.dataset.loading) {
            button.setAttribute('aria-busy', 'true');
            button.textContent = button.dataset.loading;
            // Named review buttons must remain successful form controls.
            if (!button.name) button.disabled = true;
        }
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[aria-busy="true"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
});
