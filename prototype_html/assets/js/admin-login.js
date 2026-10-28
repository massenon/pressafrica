/* Login page (mock). No real authentication: any password of 8+ characters is accepted for a known demo account. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $ = AGP.$;
  AGP.page('login', function () {
    var form = $('#loginForm'), email = $('#email'), pass = $('#password'), err = $('#loginErr'), show = $('#showPass');
    if (AGP.user()) { /* already signed in */ }
    function fail(m, el) { err.hidden = false; err.textContent = m; if (el) { el.setAttribute('aria-invalid', 'true'); el.focus(); } }
    show.addEventListener('change', function () { pass.type = show.checked ? 'text' : 'password'; });
    var S = AGP.store.get('settings', {});
    form.addEventListener('submit', function (e) {
      e.preventDefault(); err.hidden = true; email.removeAttribute('aria-invalid'); pass.removeAttribute('aria-invalid');
      var lock = AGP.store.get('lock', { n: 0, until: 0 });
      if (lock.until > Date.now()) return fail('Too many attempts. Account locked for ' + Math.ceil((lock.until - Date.now()) / 60000) + ' minute(s) (brute-force protection, mock).');
      var u = AGP.users().filter(function (x) { return x.email.toLowerCase() === email.value.trim().toLowerCase(); })[0];
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) return fail('Enter a valid email address.', email);
      if (pass.value.length < 8) return fail('Password must be at least 8 characters.', pass);
      if (!u || u.status !== 'active') {
        lock.n++; if (lock.n >= (S.maxAttempts || 5)) { lock = { n: 0, until: Date.now() + (S.lockMinutes || 15) * 60000 }; }
        AGP.store.set('lock', lock); AGP.audit('login_failed', 'auth', email.value.trim(), 'unknown or inactive account', { id: '', name: 'Anonymous', role: 'anonymous' });
        return fail('Unknown account. Use one of the demo accounts below.', email);
      }
      AGP.store.set('lock', { n: 0, until: 0 });
      AGP.store.set('session', { userId: u.id, at: Date.now() });
      AGP.audit('login', 'auth', u.email, '', u);
      location.href = 'index.html';
    });
    var demo = $('#demo');
    AGP.users().forEach(function (u) {
      demo.appendChild(h('button', { type: 'button', class: 'a-btn a-btn-sm', on: { click: function () { email.value = u.email; pass.value = 'demo-password'; form.requestSubmit(); } } }, u.name.split(' ')[0] + ' (' + u.role + ')'));
    });
    email.focus();
  });
})();
