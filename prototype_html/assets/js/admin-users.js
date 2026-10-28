/* Authors (multi-author, per-author dashboard) and Roles & permissions (checkbox matrix). */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $$ = AGP.$$;
  AGP.page('authors', function (root) {
    var canEdit = AGP.can('users', 'edit'), canCreate = AGP.can('users', 'create'), canDel = AGP.can('users', 'delete');
    var tabs = h('div', { class: 'a-tabs', role: 'tablist', 'aria-label': 'Sections' }), pa = h('div', { role: 'tabpanel', id: 'tp-a' }), pr = h('div', { role: 'tabpanel', id: 'tp-r', hidden: true });
    [['Authors', pa], ['Roles & permissions', pr]].forEach(function (t, i) {
      var b = h('button', { type: 'button', role: 'tab', class: 'a-tab', 'aria-selected': String(i === 0), 'aria-controls': t[1].id, text: t[0], tabindex: i ? '-1' : '0' });
      b.addEventListener('click', function () { $$('.a-tab', tabs).forEach(function (x, j) { var on = x === b; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; [pa, pr][j].hidden = !on; }); });
      tabs.appendChild(b);
    });
    tabs.addEventListener('keydown', function (e) { if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return; var t = $$('.a-tab', tabs), i = t.indexOf(document.activeElement); if (i < 0) return; var n = t[(i + (e.key === 'ArrowRight' ? 1 : -1) + t.length) % t.length]; n.focus(); n.click(); });

    /* ----- authors ----- */
    var tEl = h('section', { 'aria-label': 'Authors' });
    function roleName(id) { var r = AGP.roles().filter(function (x) { return x.id === id; })[0]; return r ? r.name : id; }
    function postsOf(u) { return AGP.posts().filter(function (p) { return p.author === u.id; }); }
    function form(u) {
      var isNew = !u;
      AGP.formModal({
        title: isNew ? 'Add author' : 'Edit author', submit: isNew ? 'Add' : 'Save',
        fields: [{ name: 'name', label: 'Display name', required: true, value: u ? u.name : '' }, { name: 'email', label: 'Email', type: 'email', required: true, value: u ? u.email : '' },
          { name: 'role', label: 'Role', type: 'select', options: AGP.roles().map(function (r) { return { v: r.id, l: r.name }; }), value: u ? u.role : 'author' }, { name: 'status', label: 'Status', type: 'select', options: [{ v: 'active', l: 'Active' }, { v: 'banned', l: 'Banned' }], value: u ? u.status : 'active' },
          { name: 'bio', label: 'Public bio', type: 'textarea', rows: 3, value: u ? u.bio : '' }],
        onSubmit: function (v) {
          var all = AGP.users(), rec = isNew ? { id: AGP.uid('u'), joined: new Date().toISOString().slice(0, 10) } : all.filter(function (x) { return x.id === u.id; })[0];
          if (all.some(function (x) { return x.email.toLowerCase() === v.email.toLowerCase() && x.id !== rec.id; })) { AGP.toast('That email is already used.', 'err'); return false; }
          Object.assign(rec, v); if (isNew) all.push(rec); AGP.store.set('users', all); t.refresh(); AGP.toast('Author saved.', 'ok');
        }
      });
    }
    function dash(u) {
      var ps = postsOf(u), by = function (s) { return ps.filter(function (p) { return p.status === s; }).length; }, views = ps.reduce(function (a, p) { return a + (p.views || 0); }, 0);
      var k = [['Published', by('published')], ['Pending', by('pending')], ['Drafts', by('draft')], ['Views', views.toLocaleString('en-GB')]];
      AGP.modal({ title: u.name + ': dashboard', size: 'lg', body: h('div', {}, h('p', { class: 'a-muted', text: roleName(u.role) + ' • ' + u.email }),
        h('div', { class: 'a-kpis' }, k.map(function (x) { return h('div', { class: 'a-card a-kpi' }, h('span', { class: 'a-eyebrow', text: x[0] }), h('div', { class: 'v', text: String(x[1]) })); })),
        h('h3', { class: 'mb-2', text: 'Latest posts' }), h('ul', { class: 'a-list' }, ps.slice(0, 6).map(function (p) { return h('li', {}, h('span', { class: 'flex-1', text: p.title }), AGP.badge(p.status)); }))),
        actions: [{ label: 'Close', cls: 'a-btn-ghost' }, { label: 'Open "My posts" view', cls: 'a-btn-primary', run: function () { location.href = 'posts.html?author=' + u.id; return false; } }] });
    }
    var t = AGP.table(tEl, {
      id: 'id', label: 'Authors', rows: AGP.users, rowLabel: function (r) { return r.name; }, search: function (r) { return r.name + ' ' + r.email; }, sort: { key: 'name', dir: 'asc' }, pageSize: 10,
      filters: [{ key: 'role', label: 'Role', options: AGP.roles().map(function (r) { return [r.id, r.name]; }), test: function (r, v) { return r.role === v; } }],
      columns: [
        { key: 'name', label: 'Author', sort: true, render: function (r) { var a = h('span', { class: 'a-avatar', 'aria-hidden': 'true', text: AGP.initials(r.name) }); return h('div', { class: 'flex items-center gap-3' }, a, h('div', {}, h('b', { text: r.name }), h('span', { class: 'a-muted block text-xs', text: r.email }))); } },
        { key: 'role', label: 'Role', sort: true, render: function (r) { return roleName(r.role); } },
        { key: 'posts', label: 'Posts', sort: function (r) { return postsOf(r).length; }, render: function (r) { return h('a', { href: 'posts.html?author=' + r.id, text: String(postsOf(r).length) }); } },
        { key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status === 'active' ? 'active' : 'rejected', r.status === 'active' ? 'Active' : 'Banned'); } },
        { key: 'joined', label: 'Joined', sort: true, render: function (r) { return AGP.fmtDate(r.joined); } }],
      actions: function (r) { var a = [{ label: 'Author dashboard', icon: 'chart', run: dash }]; if (canEdit) a.push({ label: 'Edit', icon: 'edit', run: form }); if (canDel && r.id !== AGP.user().id) a.push({ label: 'Delete', icon: 'trash', run: function (u) { AGP.confirm({ title: 'Delete ' + u.name + '?', text: 'Their posts remain, attributed to the account name.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) { AGP.store.set('users', AGP.users().filter(function (x) { return x.id !== u.id; })); t.refresh(); AGP.toast('Author deleted.', 'warn'); } }); } }); return a; }
    });
    pa.appendChild(tEl);

    /* ----- roles ----- */
    var cur = AGP.roles()[0].id, matrixBox = h('div'), rsel = h('select', { class: 'a-select', id: 'roleSel' });
    function roles() { return AGP.roles(); }
    function drawRoleSel() { AGP.mount(rsel, roles().map(function (r) { return h('option', { value: r.id, text: r.name, selected: r.id === cur ? true : null }); })); }
    rsel.addEventListener('change', function () { cur = rsel.value; drawMatrix(); });
    function drawMatrix() {
      var r = roles().filter(function (x) { return x.id === cur; })[0], locked = r.system || !canEdit;
      var draft = JSON.parse(JSON.stringify(r.perms));
      var tbl = h('table', { class: 'a-table a-perm', 'aria-label': 'Permissions for ' + r.name });
      var hr = h('tr', {}, h('th', { scope: 'col', text: 'Module' }), AGP.ACTIONS.map(function (a) { return h('th', { scope: 'col', text: a[1] }); }), h('th', { scope: 'col', text: 'All' }));
      var body = h('tbody');
      AGP.MODULES.forEach(function (m) {
        var tr = h('tr', {}, h('th', { scope: 'row', text: m[1] }));
        var cbs = AGP.ACTIONS.map(function (a) {
          var cb = h('input', { type: 'checkbox', checked: draft[m[0]].indexOf(a[0]) > -1, disabled: locked, 'aria-label': r.name + ': ' + a[1] + ' ' + m[1] });
          cb.addEventListener('change', function () { var s = draft[m[0]]; if (cb.checked && s.indexOf(a[0]) < 0) s.push(a[0]); if (!cb.checked) draft[m[0]] = s.filter(function (x) { return x !== a[0]; }); if (cb.checked && a[0] !== 'view' && draft[m[0]].indexOf('view') < 0) { draft[m[0]].push('view'); cbs[0].checked = true; } if (!cb.checked && a[0] === 'view') { draft[m[0]] = []; cbs.forEach(function (c) { c.checked = false; }); } all.checked = cbs.every(function (c) { return c.checked; }); });
          tr.appendChild(h('td', {}, cb)); return cb;
        });
        var all = h('input', { type: 'checkbox', disabled: locked, 'aria-label': r.name + ': all permissions for ' + m[1], checked: cbs.every(function (c) { return c.checked; }) });
        all.addEventListener('change', function () { cbs.forEach(function (c, i) { c.checked = all.checked; }); draft[m[0]] = all.checked ? AGP.ACTIONS.map(function (a) { return a[0]; }) : []; });
        tr.appendChild(h('td', {}, all)); body.appendChild(tr);
      });
      tbl.appendChild(h('thead', {}, hr)); tbl.appendChild(body);
      var acts = h('div', { class: 'flex flex-wrap gap-2 mt-4' });
      if (!locked) acts.appendChild(AGP.btn('Save permissions', { icon: 'check', cls: 'a-btn-primary', on: { click: function () { var a = roles(); a.forEach(function (x) { if (x.id === r.id) x.perms = draft; }); AGP.store.set('roles', a); AGP.toast('Permissions saved for ' + r.name + '.', 'ok'); } } }));
      if (canEdit && !r.system) acts.appendChild(AGP.btn('Delete role', { icon: 'trash', cls: 'a-btn-danger', on: { click: function () { if (AGP.users().some(function (u) { return u.role === r.id; })) { AGP.toast('Reassign the users of this role first.', 'err'); return; } AGP.confirm({ title: 'Delete role ' + r.name + '?', ok: 'Delete', danger: true, text: 'This cannot be undone.' }).then(function (ok) { if (ok) { AGP.store.set('roles', roles().filter(function (x) { return x.id !== r.id; })); cur = roles()[0].id; drawRoleSel(); drawMatrix(); AGP.toast('Role deleted.', 'warn'); } }); } } }));
      AGP.mount(matrixBox, r.system ? h('p', { class: 'a-badge info', text: 'Administrator is a system role and always has full access.' }) : null, h('div', { class: 'a-tablewrap mt-2' }, tbl), acts);
    }
    drawRoleSel(); drawMatrix();
    pr.appendChild(h('div', { class: 'a-card' }, h('div', { class: 'a-tools' }, h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'roleSel', text: 'Role' }), rsel),
      canCreate ? AGP.btn('New role', { icon: 'plus', on: { click: function () { AGP.formModal({ title: 'New role', submit: 'Create', fields: [{ name: 'name', label: 'Role name', required: true }], onSubmit: function (v) { var a = roles(), id = AGP.slugify(v.name); if (a.some(function (x) { return x.id === id; })) { AGP.toast('Role exists.', 'err'); return false; } var perms = {}; AGP.MODULES.forEach(function (m) { perms[m[0]] = []; }); perms.posts = ['view']; a.push({ id: id, name: v.name, perms: perms }); AGP.store.set('roles', a); cur = id; drawRoleSel(); drawMatrix(); AGP.toast('Role created. Tick its permissions.', 'ok'); } }); } } }) : null),
      h('div', { class: 'a-card-b' }, matrixBox)));
    AGP.mount(root, AGP.head({ eyebrow: 'Audience', title: 'Authors & roles', sub: 'Multi-author newsroom: each author has a personal dashboard and a "my posts" view. Roles use a checkbox permission matrix.', actions: canCreate ? [AGP.btn('Add author', { icon: 'plus', cls: 'a-btn-primary', on: { click: function () { form(null); } } })] : [] }), tabs, pa, pr);
  });
})();
