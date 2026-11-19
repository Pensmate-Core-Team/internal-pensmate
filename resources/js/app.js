import './bootstrap';
import { copyText } from './copy-text';

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

// Captions are copied as plain text, preserving line breaks and Unicode.
document.querySelectorAll('[data-copy-target]').forEach(button => {
    button.addEventListener('click', async () => {
        const source = document.getElementById(button.dataset.copyTarget);
        const status = document.getElementById(button.dataset.copyStatus);
        if (!source || !status) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            await copyText(source.textContent, navigator.clipboard);
            status.textContent = button.dataset.copySuccess ?? 'Caption berhasil disalin.';
        } catch {
            const selection = window.getSelection();
            if (selection) {
                const range = document.createRange();
                range.selectNodeContents(source);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            status.textContent = button.dataset.copyFailure ?? 'Salin otomatis tidak tersedia. Pilih teks caption lalu gunakan menu Salin atau Ctrl+C.';
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
});

document.querySelectorAll('[data-media-files]').forEach(input => {
    input.addEventListener('change', () => {
        const list = document.getElementById(input.dataset.fileList);
        list?.replaceChildren();
        const files = Array.from(input.files ?? []);
        input.setCustomValidity(files.length > 10 ? 'Maksimal 10 file per konten.' : '');
        files.forEach((file, index) => {
            const item = document.createElement('li');
            item.textContent = `${index + 1}. ${file.name}`;
            list?.append(item);
        });
    });
});

document.querySelectorAll('[data-asset-order]').forEach(list => {
    function updateButtons() {
        const items = Array.from(list.children);
        items.forEach((item, index) => {
            item.querySelector('[data-move-asset="up"]').disabled = index === 0;
            item.querySelector('[data-move-asset="down"]').disabled = index === items.length - 1;
        });
    }
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-move-asset]');
        if (!button) return;
        const item = button.closest('[data-asset-item]');
        if (button.dataset.moveAsset === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.moveAsset === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
        updateButtons();
        if (!button.disabled) button.focus();
        list.parentElement.querySelector('[data-order-status]').textContent = 'Urutan diperbarui. Simpan draf untuk menerapkan.';
    });
    updateButtons();
});
