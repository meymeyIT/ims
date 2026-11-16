/*
 * Light / dark theme toggle.
 *
 * The saved choice is applied before the page is drawn by a tiny inline script in
 * the <head> of layouts/app and layouts/guest (so there is no white flash). This file
 * wires up the toggle buttons: any element with [data-theme-toggle].
 *
 * Other code can react to a change with:
 *   window.addEventListener('ims:themechange', (e) => e.detail.theme)   // 'light' | 'dark'
 */
const STORAGE_KEY = 'ims-theme';

export const currentTheme = () => (document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light');

function save(theme) {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Private mode / storage disabled: the toggle still works for this page.
    }
}

function syncButtons(theme) {
    const next = theme === 'dark' ? 'light' : 'dark';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('i');
        icon?.classList.toggle('bi-moon-stars', theme === 'light');
        icon?.classList.toggle('bi-sun', theme === 'dark');
        button.setAttribute('aria-label', `Switch to ${next} mode`);
        button.setAttribute('title', `Switch to ${next} mode`);
        button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    });
}

export function applyTheme(theme, { persist = true } = {}) {
    const root = document.documentElement;

    root.classList.add('theme-switching');            // no colour animations mid-switch
    root.setAttribute('data-bs-theme', theme);
    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('theme-switching')));

    if (persist) {
        save(theme);
    }
    syncButtons(theme);
    window.dispatchEvent(new CustomEvent('ims:themechange', { detail: { theme } }));
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }
});

// Keep several open tabs in step: switching in one tab switches the others.
window.addEventListener('storage', (event) => {
    if (event.key === STORAGE_KEY && (event.newValue === 'dark' || event.newValue === 'light')) {
        applyTheme(event.newValue, { persist: false });
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => syncButtons(currentTheme()));
} else {
    syncButtons(currentTheme());
}
