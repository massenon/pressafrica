/* Admin console behaviours of the prototype shell (assets/js/admin.js): mobile sidebar, account menu, theme,
   confirmations. Plain script, re-bound after each Livewire navigation (wire:navigate swaps the page). */
(function () {
  'use strict';
  function bind() {
    // livewire:navigated also fires on the first load: bind once per page body.
    if (document.body.dataset.agpBound) return;
    document.body.dataset.agpBound = '1';
    var side = document.getElementById('sidebar'), scrim = document.querySelector('[data-scrim]'), menu = document.querySelector('[data-menu-btn]');
    var setNav = function (open) { if (!side) return; side.classList.toggle('open', open); scrim && scrim.classList.toggle('open', open); menu && menu.setAttribute('aria-expanded', String(open)); };
    menu && menu.addEventListener('click', function () { setNav(!side.classList.contains('open')); });
    scrim && scrim.addEventListener('click', function () { setNav(false); });
    var ub = document.querySelector('[data-user-btn]'), up = document.getElementById('userPanel');
    ub && ub.addEventListener('click', function () { up.hidden = !up.hidden; ub.setAttribute('aria-expanded', String(!up.hidden)); });
    var tb = document.querySelector('[data-theme-btn]');
    tb && tb.addEventListener('click', function () {
      // Light by default; the choice is kept in a cookie so the server paints the right theme first (INV-07).
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      document.cookie = 'theme=' + next + '; path=/; max-age=31536000; SameSite=Lax';
      tb.setAttribute('aria-pressed', String(next === 'dark'));
    });
  }
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var up = document.getElementById('userPanel'); if (up) up.hidden = true;
    var side = document.getElementById('sidebar'); if (side) side.classList.remove('open');
    var s = document.querySelector('[data-scrim]'); if (s) s.classList.remove('open');
  });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', bind) : bind();
  document.addEventListener('livewire:navigated', bind);
})();
