/* Server-backed forms for the Laravel site. The prototype's app.js keeps [data-comment] forms in the browser;
   Laravel pages mark theirs [data-comment-server] instead and this posts them, with the same error/success UI. */
(function () {
  'use strict';
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  /* "Reply" under a published comment: one level only (spec EX-45). Without JS the link reloads with ?reply=ID. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-reply-to], [data-reply-cancel]');
    if (!a) return;
    var f = document.querySelector('form[data-comment-server]');
    if (!f) return;
    e.preventDefault();
    var field = f.querySelector('[data-reply-field]'), label = f.querySelector('[data-reply-label]'), cancel = f.querySelector('[data-reply-cancel]');
    if (a.hasAttribute('data-reply-cancel')) { field.value = ''; label.hidden = true; return; }
    field.value = a.getAttribute('data-reply-to');
    label.querySelector('span').textContent = cancel.getAttribute('data-template').replace(':name', a.getAttribute('data-reply-name'));
    label.hidden = false;
    f.querySelector('textarea').focus();
  });
  /* Newsletter forms of the prototype (modal, article box): sent to the server (double opt-in) instead of the
     browser storage of the prototype. Capture phase + stopPropagation so app.js's own handler never runs. */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-newsletter]');
    if (!f) return;
    e.preventDefault(); e.stopPropagation();
    var em = f.elements.email, ok = f.querySelector('.form-ok'), errEm = em && document.getElementById(em.getAttribute('aria-describedby'));
    var cons = f.elements.consent, errCons = cons && document.getElementById(cons.id + '-err');
    if (!em || !EMAIL.test(em.value.trim())) { if (errEm) errEm.hidden = false; em && em.focus(); return; }
    if (errEm) errEm.hidden = true;
    if (cons && !cons.checked) { if (errCons) errCons.hidden = false; cons.focus(); return; }
    if (errCons) errCons.hidden = true;
    var lang = document.documentElement.lang === 'en' ? 'en' : 'fr', body = new FormData(f);
    fetch('/' + lang + '/newsletter', { method: 'POST', body: body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { if (errEm) errEm.hidden = false; return; }
        if (ok) { ok.textContent = res.j.message || ok.textContent; ok.hidden = false; }
        em.value = ''; if (cons) cons.checked = false;
      })
      .catch(function () { if (errEm) errEm.hidden = false; });
  }, true);
  /* Contact page: app.js keeps its field-by-field validation; a valid form is sent to the server instead of the
     prototype's browser storage (capture phase, so app.js's submit handler does not run for it). */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-contact]');
    if (!f) return;
    var valid = [].every.call(f.querySelectorAll('[required]'), function (x) { return x.type === 'checkbox' ? x.checked : x.value.trim() !== ''; })
      && EMAIL.test((f.elements.email || {}).value || '');
    if (!valid) return;   // let app.js show its messages
    e.preventDefault(); e.stopPropagation();
    var ok = f.querySelector('.form-ok'), sum = f.querySelector('.form-summary'), btn = f.querySelector('button[type=submit]');
    var lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
    btn.disabled = true;
    fetch('/' + lang + '/contact', { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { if (sum) sum.hidden = false; return; }
        f.reset(); if (sum) sum.hidden = true; if (ok) { ok.hidden = false; ok.focus && ok.focus(); }
      })
      .catch(function () { btn.disabled = false; if (sum) sum.hidden = false; });
  }, true);
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
        var lbl = f.querySelector('[data-reply-label]'); if (lbl) lbl.hidden = true;
      })
      .catch(function () { err.hidden = false; });
  });
})();
