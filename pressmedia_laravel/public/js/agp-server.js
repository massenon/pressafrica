/* Server-backed forms for the Laravel site. The prototype's app.js keeps [data-comment] forms in the browser;
   Laravel pages mark theirs [data-comment-server] instead and this posts them, with the same error/success UI. */
(function () {
  'use strict';
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  function token() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-comment-server]');
    if (!f) return;
    e.preventDefault();
    var el = f.elements, err = f.querySelector('.form-err'), ok = f.querySelector('.form-ok');
    var bad = [el.comment, el.author, el.email].filter(function (x) { return !x.value.trim(); });
    if (el.email.value.trim() && !EMAIL.test(el.email.value.trim())) bad.push(el.email);
    [el.comment, el.author, el.email].forEach(function (x) { x.toggleAttribute('aria-invalid', bad.indexOf(x) >= 0); });
    if (bad.length || !el.consent.checked) { err.hidden = false; ok.hidden = true; (bad[0] || el.consent).focus(); return; }
    err.hidden = true;
    fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { err.hidden = false; return; }
        ok.textContent = res.j.message || ok.textContent; ok.hidden = false; f.reset();
      })
      .catch(function () { err.hidden = false; });
  });
})();
