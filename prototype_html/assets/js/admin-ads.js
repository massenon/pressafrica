/* Ad zones: unlimited zones with separate desktop / mobile configuration (AdSense or custom banner/HTML). Code is stored as text and never executed here. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var SIZES = ['970x250', '728x90', '468x60', '300x600', '300x250', '320x100', '320x50', '160x600', '640x200'];
  var TYPES = [{ v: 'adsense', l: 'Google AdSense' }, { v: 'banner', l: 'Custom banner (image + link)' }, { v: 'html', l: 'Custom HTML' }];
  AGP.page('ads', function (root) {
    var canCreate = AGP.can('ads', 'create'), canEdit = AGP.can('ads', 'edit'), canDel = AGP.can('ads', 'delete');
    var tEl = h('section', { 'aria-label': 'Ad zones' });
    function all() { return AGP.store.get('ads', []); }
    function save(a) { AGP.store.set('ads', a); ST = null; t.refresh(); }
    function box(cfg, label) {
      var p = (cfg.size || '300x250').split('x'), w = +p[0], hh = +p[1], scale = Math.min(1, 560 / w);
      var d = h('div', { class: 'a-ad-prev', role: 'img', 'aria-label': label + ' placeholder ' + cfg.size }, h('span', { text: label + ' • ' + cfg.size + (cfg.enabled ? '' : ' (off)') }));
      d.style.width = Math.round(w * scale) + 'px'; d.style.height = Math.round(hh * scale) + 'px'; d.style.opacity = cfg.enabled ? '1' : '.5'; return d;
    }
    function preview(z) {
      AGP.modal({ title: 'Preview: ' + z.name, size: 'lg', body: h('div', {}, h('p', { class: 'a-muted', text: 'Placement: ' + z.placement + '. Ad code is shown as text only; the prototype never executes it.' }),
        h('h3', { class: 'mt-3 mb-2', text: 'Desktop' }), box(z.desktop, 'Desktop'), h('pre', { class: 'a-pre a-mono mt-2', text: z.desktop.code || '(no code)' }),
        h('h3', { class: 'mt-4 mb-2', text: 'Mobile' }), box(z.mobile, 'Mobile'), h('pre', { class: 'a-pre a-mono mt-2', text: z.mobile.code || '(no code)' })), actions: [{ label: 'Close', cls: 'a-btn-ghost' }] });
    }
    function form(z) {
      var isNew = !z; z = z || { name: '', key: '', placement: '', active: true, desktop: { type: 'adsense', size: '728x90', code: '', enabled: true }, mobile: { type: 'adsense', size: '320x100', code: '', enabled: true } };
      var f = [{ name: 'name', label: 'Zone name', required: true, value: z.name }, { name: 'key', label: 'Zone key', value: z.key, hint: 'Used in templates, e.g. header_top.' }, { name: 'placement', label: 'Placement', value: z.placement, full: true }, { name: 'active', label: 'Zone active', type: 'checkbox', value: z.active }];
      ['desktop', 'mobile'].forEach(function (dv) {
        var c = z[dv], P = dv === 'desktop' ? 'd_' : 'm_', L = dv === 'desktop' ? 'Desktop' : 'Mobile';
        f.push({ name: P + 'enabled', label: L + ': enabled', type: 'checkbox', value: c.enabled }, { name: P + 'type', label: L + ': type', type: 'select', options: TYPES, value: c.type }, { name: P + 'size', label: L + ': size', type: 'select', options: SIZES.map(function (s) { return { v: s, l: s }; }), value: c.size },
          { name: P + 'code', label: L + ': code / banner reference', type: 'textarea', rows: 3, value: c.code, hint: dv === 'desktop' ? 'AdSense slot id, banner image + link, or HTML snippet.' : '' });
      });
      AGP.formModal({ title: isNew ? 'New ad zone' : 'Edit ad zone', size: 'lg', submit: 'Save zone', fields: f, onSubmit: function (v) {
        var a = all(), rec = isNew ? { id: AGP.uid('a') } : a.filter(function (x) { return x.id === z.id; })[0];
        rec.name = v.name; rec.key = AGP.slugify(v.key || v.name).replace(/-/g, '_'); rec.placement = v.placement; rec.active = v.active;
        rec.desktop = { enabled: v.d_enabled, type: v.d_type, size: v.d_size, code: v.d_code }; rec.mobile = { enabled: v.m_enabled, type: v.m_type, size: v.m_size, code: v.m_code };
        if (isNew) a.push(rec); save(a); AGP.toast('Ad zone saved.', 'ok');
      } });
    }
    var ST = null;   // synthetic click figures (demo): see admin-stats.js
    function adStat(r) { if (!ST) ST = AGP.stats.get(30).ads; return ST.filter(function (z) { return z.id === r.id; })[0] || { clicks: 0, impressions: 0, ctr: '0.0%' }; }
    var t = AGP.table(tEl, {
      id: 'id', label: 'Ad zones', rows: all, rowLabel: function (r) { return r.name; }, search: function (r) { return r.name + ' ' + r.key + ' ' + r.placement; }, pageSize: 10, sort: { key: 'name', dir: 'asc' },
      filters: [{ key: 'active', label: 'Status', options: [['on', 'Active'], ['off', 'Inactive']], test: function (r, v) { return (v === 'on') === !!r.active; } }],
      columns: [{ key: 'name', label: 'Zone', sort: true, render: function (r) { return h('div', {}, h('b', { text: r.name }), h('span', { class: 'a-muted block text-xs a-mono', text: r.key })); } }, { key: 'placement', label: 'Placement', sort: true },
        { key: 'desktop', label: 'Desktop', render: function (r) { return r.desktop.enabled ? r.desktop.type + ' • ' + r.desktop.size : 'Off'; } }, { key: 'mobile', label: 'Mobile', render: function (r) { return r.mobile.enabled ? r.mobile.type + ' • ' + r.mobile.size : 'Off'; } },
        { key: 'clicks', label: 'Clicks (30 d)', sort: function (r) { return adStat(r).clicks; }, render: function (r) { var s = adStat(r); return h('div', {}, h('b', { text: s.clicks.toLocaleString('en-GB') }), h('span', { class: 'a-muted block text-xs', text: 'CTR ' + s.ctr + ' · ' + s.impressions.toLocaleString('en-GB') + ' views' })); } },
        { key: 'active', label: 'Status', sort: function (r) { return r.active ? 1 : 0; }, render: function (r) { return AGP.badge(r.active ? 'active' : 'inactive', r.active ? 'Active' : 'Inactive'); } }],
      actions: function () { var a = [{ label: 'Preview', icon: 'eye', run: preview }]; if (canEdit) a.push({ label: 'Edit', icon: 'edit', run: form }); if (canDel) a.push({ label: 'Delete', icon: 'trash', run: function (z) { AGP.confirm({ title: 'Delete zone “' + z.name + '”?', text: 'Templates referencing its key will render nothing.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) { save(all().filter(function (x) { return x.id !== z.id; })); AGP.toast('Zone deleted.', 'warn'); } }); } }); return a; }
    });
    AGP.mount(root, AGP.head({ eyebrow: 'Site', title: 'Ad zones', sub: 'Create unlimited zones with separate desktop and mobile settings. Compatible with Google AdSense and custom banners.', actions: canCreate ? [AGP.btn('New zone', { icon: 'plus', cls: 'a-btn-primary', on: { click: function () { form(null); } } })] : [] }), tEl);
  });
})();
