/* Audit log: append-only journal of who did what and when (sign-ins, content writes, exports, backups). Read, filter, search and export only: nothing here can edit or delete an entry.
   Entries are written by AGP.audit() in admin.js; the demo history below is seeded once so the screen is reviewable on first run. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var CLS = { create: 'ok', update: 'info', delete: 'bad', login: '', logout: '', login_failed: 'warn', export: '', backup_manual: 'ok', backup_auto: 'ok', backup_restore: 'warn', backup_pre_restore: 'warn', backup_delete: 'bad' };

  function seedHistory() {
    if (AGP.store.get('audit', null)) return;
    var users = AGP.users(), mods = ['posts', 'comments', 'categories', 'media', 'newsletter', 'ads', 'podcasts', 'settings'], acts = ['update', 'create', 'update', 'delete', 'update'];
    var out = [], t = Date.now() - 9 * 86400000;
    for (var i = 0; i < 48; i++) {
      var u = users[i % users.length] || { id: '', name: 'System', role: 'system' }, m = mods[(i * 3) % mods.length], a = i % 9 === 0 ? 'login' : acts[i % acts.length];
      t += 86400000 * 9 / 48 + (i % 5) * 600000;
      out.push({ id: 'seed' + i, at: new Date(t).toISOString(), userId: u.id, user: u.name, role: u.role, action: a, module: a === 'login' ? 'auth' : m, target: a === 'login' ? u.email : m, detail: a === 'login' ? '' : (a === 'create' ? '+1 added' : a === 'delete' ? '1 removed' : '1 changed'), ip: '192.0.2.' + (1 + AGP.hue(u.id || 'sys') % 200), demo: true });
    }
    AGP.store.set('audit', out);
  }

  AGP.page('audit', function (root) {
    seedHistory();
    var tEl = h('section', { 'aria-label': 'Audit log' });
    function rows() { return AGP.store.get('audit', []); }
    var uniq = function (k) { var o = {}; rows().forEach(function (r) { o[r[k]] = 1; }); return Object.keys(o).sort(); };
    var t = AGP.table(tEl, {
      emptyState: { icon: 'shield', title: 'The journal is empty', text: 'Actions will be recorded here as they happen.' },
      id: 'id', label: 'Audit entries', rows: rows, rowLabel: function (r) { return r.action + ' by ' + r.user; }, sort: { key: 'at', dir: 'desc' }, pageSize: 25,
      search: function (r) { return [r.user, r.action, r.module, r.target, r.detail, r.ip].join(' '); }, searchPlaceholder: 'Search user, action, module, target…',
      filters: [{ key: 'module', label: 'Module', options: uniq('module').map(function (x) { return [x, x]; }), test: function (r, v) { return r.module === v; } },
        { key: 'action', label: 'Action', options: uniq('action').map(function (x) { return [x, x.replace(/_/g, ' ')]; }), test: function (r, v) { return r.action === v; } },
        { key: 'user', label: 'User', options: uniq('user').map(function (x) { return [x, x]; }), test: function (r, v) { return r.user === v; } }],
      columns: [
        { key: 'at', label: 'When', sort: true, render: function (r) { return AGP.fmtDate(r.at, true); } },
        { key: 'user', label: 'User', sort: true, render: function (r) { return h('div', {}, h('b', { text: r.user }), h('div', { class: 'a-muted', text: r.role })); } },
        { key: 'action', label: 'Action', sort: true, render: function (r) { return h('span', { class: 'a-badge ' + (CLS[r.action] || ''), text: r.action.replace(/_/g, ' ') }); } },
        { key: 'module', label: 'Module', sort: true }, { key: 'target', label: 'Target', render: function (r) { return h('span', { class: 'a-mono', text: String(r.target || '') }); } },
        { key: 'detail', label: 'Detail' }, { key: 'ip', label: 'IP (mock)', render: function (r) { return h('span', { class: 'a-mono a-muted', text: r.ip || '' }); } }]
    });
    var exp = AGP.btn('Export CSV', { icon: 'download', on: { click: function () {
      var cols = ['at', 'user', 'role', 'action', 'module', 'target', 'detail', 'ip'];
      AGP.download('agp-audit-log.csv', '﻿' + AGP.toCSV(rows(), cols), 'text/csv;charset=utf-8'); AGP.audit('export', 'audit', 'audit-log', 'CSV, ' + rows().length + ' entries');
    } } });
    AGP.mount(root, AGP.head({ eyebrow: 'System', title: 'Audit log', sub: 'Append-only journal of sign-ins, content changes, exports and backups. Entries cannot be edited or deleted from the console.', actions: [exp] }),
      h('p', { class: 'a-note info mb-3', role: 'note', text: 'Entries marked as history at the start of the list are demo data. In production the journal is a database table with no update or delete route.' }), tEl);
  });
})();
