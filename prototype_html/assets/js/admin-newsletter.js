/* Newsletter: subscribers table, add, CSV/JSON import (file or paste), CSV/JSON export, email blacklist. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var RX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  AGP.page('newsletter', function (root) {
    var canCreate = AGP.can('newsletter', 'create'), canDel = AGP.can('newsletter', 'delete'), canEdit = AGP.can('newsletter', 'edit');
    var tEl = h('section', { 'aria-label': 'Subscribers' });
    function all() { return AGP.store.get('subscribers', []); }
    function bl() { return AGP.store.get('blacklist', []); }
    function addMany(list) {
      var cur = all(), have = {}, black = bl().map(function (x) { return x.toLowerCase(); }); cur.forEach(function (s) { have[s.email.toLowerCase()] = 1; });
      var r = { added: 0, dup: 0, bad: 0, blocked: 0 };
      list.forEach(function (x) {
        var e = String(x.email || '').trim().toLowerCase();
        if (!RX.test(e)) r.bad++; else if (black.indexOf(e) > -1 || black.indexOf(e.split('@')[1]) > -1) r.blocked++; else if (have[e]) r.dup++;
        else { have[e] = 1; r.added++; cur.push({ id: AGP.uid('s'), email: e, name: String(x.name || '').slice(0, 80), lang: x.lang === 'en' ? 'en' : 'fr', status: ['active', 'pending', 'unsubscribed'].indexOf(x.status) > -1 ? x.status : 'active', date: new Date().toISOString().slice(0, 10) }); }
      });
      AGP.store.set('subscribers', cur); t.refresh(); return r;
    }
    function parseText(text) {
      text = text.trim(); if (!text) return [];
      if (/^[\[{]/.test(text)) { var j = JSON.parse(text); j = Array.isArray(j) ? j : (j.subscribers || []); return j.map(function (x) { return typeof x === 'string' ? { email: x } : x; }); }
      var rows = AGP.parseCSV(text), head = rows[0].map(function (x) { return x.trim().toLowerCase(); });
      var hasHead = head.indexOf('email') > -1; var idx = function (k) { return hasHead ? head.indexOf(k) : -1; };
      return (hasHead ? rows.slice(1) : rows).map(function (r) { return hasHead ? { email: r[idx('email')], name: idx('name') > -1 ? r[idx('name')] : '', lang: idx('lang') > -1 ? r[idx('lang')] : '', status: idx('status') > -1 ? r[idx('status')] : '' } : { email: r[0], name: r[1] }; });
    }
    function importModal() {
      var file = h('input', { type: 'file', class: 'a-input', accept: '.csv,.json,text/csv,application/json', id: 'impF' }), ta = h('textarea', { class: 'a-textarea', rows: 6, id: 'impT', placeholder: 'email,name,lang,status\nreader@example.com,Reader,fr,active' }), res = h('p', { class: 'a-hint', role: 'status' });
      file.addEventListener('change', function () { var f = file.files[0]; if (!f) return; if (f.size > 2000000) { AGP.toast('File too large (2 MB max).', 'err'); return; } AGP.readFile(f, 'text').then(function (t) { ta.value = t; res.textContent = 'Loaded ' + f.name + '. Review and press Import.'; }); });
      AGP.modal({ title: 'Import subscribers', size: 'lg', body: h('div', {}, h('p', { class: 'a-muted', text: 'CSV (header: email,name,lang,status) or JSON (array of objects or emails). Invalid, duplicate and blacklisted addresses are skipped.' }),
        h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'impF', text: 'Choose a file' }), file), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'impT', text: 'Or paste data' }), ta), res),
        actions: [{ label: 'Cancel', cls: 'a-btn-ghost' }, { label: 'Import', cls: 'a-btn-primary', keep: true, run: function (m) { try { var r = addMany(parseText(ta.value)); AGP.toast('Imported ' + r.added + '. Skipped: ' + r.dup + ' duplicate, ' + r.bad + ' invalid, ' + r.blocked + ' blacklisted.', r.added ? 'ok' : 'warn'); m.close(); } catch (e) { res.textContent = 'Could not parse the data: ' + e.message; res.className = 'a-err'; } return false; } }] });
    }
    function exportAs(fmt, rows) {
      rows = rows || all();
      if (fmt === 'csv') AGP.download('subscribers.csv', AGP.toCSV(rows, ['email', 'name', 'lang', 'status', 'date']), 'text/csv'); else AGP.download('subscribers.json', JSON.stringify(rows.map(function (r) { return { email: r.email, name: r.name, lang: r.lang, status: r.status, date: r.date }; }), null, 2), 'application/json');
      AGP.toast('Exported ' + rows.length + ' subscribers (' + fmt.toUpperCase() + ').', 'ok');
    }
    var t = AGP.table(tEl, {
      emptyState: { icon: 'mail', title: 'No subscribers yet', text: 'Import a CSV or JSON list to start your audience.', action: canCreate ? { label: 'Import subscribers', icon: 'upload', run: function () { importModal(); } } : null }, id: 'id', label: 'Subscribers', rows: all, rowLabel: function (r) { return r.email; }, search: function (r) { return r.email + ' ' + r.name; }, sort: { key: 'date', dir: 'desc' }, pageSize: 10,
      filters: [{ key: 'status', label: 'Status', options: [['active', 'Active'], ['pending', 'Pending'], ['unsubscribed', 'Unsubscribed']], test: function (r, v) { return r.status === v; } }, { key: 'lang', label: 'Language', options: [['fr', 'Français'], ['en', 'English']], test: function (r, v) { return r.lang === v; } }],
      columns: [{ key: 'email', label: 'Email', sort: true, render: function (r) { return h('b', { text: r.email }); } }, { key: 'name', label: 'Name', sort: true }, { key: 'lang', label: 'Lang', sort: true, render: function (r) { return h('span', { class: 'a-badge', text: r.lang.toUpperCase() }); } }, { key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status); } }, { key: 'date', label: 'Subscribed', sort: true, render: function (r) { return AGP.fmtDate(r.date); } }],
      actions: function () { return canDel ? [{ label: 'Remove', icon: 'trash', run: function (s) { AGP.confirm({ title: 'Remove ' + s.email + '?', text: 'The subscriber is deleted from the list.', ok: 'Remove', danger: true }).then(function (ok) { if (ok) { AGP.store.set('subscribers', all().filter(function (x) { return x.id !== s.id; })); t.refresh(); AGP.toast('Removed.', 'warn'); } }); } }] : []; },
      bulk: [{ label: 'Export selection (CSV)', icon: 'download', run: function (l) { exportAs('csv', l); } }].concat(canDel ? [{ label: 'Remove', icon: 'trash', cls: 'a-btn-danger', run: function (l) { var ids = l.map(function (x) { return x.id; }); AGP.store.set('subscribers', all().filter(function (x) { return ids.indexOf(x.id) < 0; })); AGP.toast(ids.length + ' removed.', 'warn'); } }] : [])
    });
    var blTa = h('textarea', { class: 'a-textarea', rows: 5, id: 'blT', 'aria-describedby': 'blH' }); blTa.value = bl().join('\n');
    var blCard = h('section', { class: 'a-card mt-4', 'aria-labelledby': 'blHd' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'blHd', text: 'Email blacklist' })),
      h('div', { class: 'a-card-b' }, h('label', { class: 'a-label', for: 'blT', text: 'Blocked addresses or domains (one per line)' }), blTa, h('p', { class: 'a-hint', id: 'blH', text: 'Blocked emails cannot subscribe, register or use contact forms.' }),
        canEdit ? AGP.btn('Save blacklist', { icon: 'check', cls: 'a-btn-sm mt-2', on: { click: function () { var l = blTa.value.split('\n').map(function (x) { return x.trim().toLowerCase(); }).filter(Boolean); AGP.store.set('blacklist', l); AGP.toast(l.length + ' entries saved.', 'ok'); } } }) : null));
    var acts = [];
    if (canCreate) acts.push(AGP.btn('Add', { icon: 'plus', on: { click: function () { AGP.formModal({ title: 'Add subscriber', submit: 'Add', fields: [{ name: 'email', label: 'Email', type: 'email', required: true }, { name: 'name', label: 'Name' }, { name: 'lang', label: 'Language', type: 'select', options: [{ v: 'fr', l: 'Français' }, { v: 'en', l: 'English' }] }], onSubmit: function (v) { var r = addMany([v]); if (!r.added) { AGP.toast(r.dup ? 'Already subscribed.' : r.blocked ? 'That address is blacklisted.' : 'Invalid email.', 'err'); return false; } AGP.toast('Subscriber added.', 'ok'); } }); } } }), AGP.btn('Import', { icon: 'upload', on: { click: importModal } }));
    acts.push(AGP.btn('Export CSV', { icon: 'download', on: { click: function () { exportAs('csv'); } } }), AGP.btn('Export JSON', { icon: 'download', on: { click: function () { exportAs('json'); } } }));
    AGP.mount(root, AGP.head({ eyebrow: 'Audience', title: 'Newsletter', sub: 'Subscriber list with CSV / JSON import and export.', actions: acts }), tEl, blCard);
  });
})();
