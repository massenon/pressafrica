/**
 * Light by default (INV-07): the server renders data-theme from the "theme" cookie, never from prefers-color-scheme.
 * The cookie (not localStorage) lets the server paint the right theme on first byte, with no flash.
 */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    document.cookie = `theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
    btn.setAttribute('aria-pressed', String(next === 'dark'));
});
