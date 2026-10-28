/* Featured content: homepage slider, breaking news, recommended block, sidebar widgets. Changes save immediately. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var WT = [{ v: 'popular', l: 'Popular posts' }, { v: 'poll', l: 'Poll' }, { v: 'html', l: 'Custom HTML' }, { v: 'newsletter', l: 'Newsletter signup' }];
  AGP.page('featured', function (root) {
    var canEdit = AGP.can('featured', 'edit');
    var F = AGP.store.get('featured', { slider: [], breaking: [], recommended: [], widgets: [] });
    var live = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
    function save(msg) { AGP.store.set('featured', F); if (msg) { AGP.toast(msg, 'ok'); live.textContent = msg; } }
    function post(id) { return AGP.posts().filter(function (p) { return p.id === id; })[0]; }
    function swap(arr, i, d) { var j = i + d; if (j < 0 || j >= arr.length) return false; var t = arr[i]; arr[i] = arr[j]; arr[j] = t; return true; }
    function ib(label, icon, fn, dis) { return h('button', { type: 'button', class: 'a-iconbtn sm', title: label.split(':')[0], 'aria-label': label, disabled: dis || !canEdit, on: { click: fn } }, AGP.icon(icon)); }

    function postList(key, title, desc, max) {
      var ul = h('ul', { class: 'a-list' });
      function draw() {
        AGP.clear(ul); if (!F[key].length) ul.appendChild(h('li', {}, h('span', { class: 'a-muted', text: 'Nothing selected.' })));
        F[key].forEach(function (id, i) {
          var p = post(id); if (!p) return;
          var th = AGP.thumb(p);
          ul.appendChild(h('li', {}, h('span', { class: 'a-badge flash', text: String(i + 1) }), th, h('div', { class: 'flex-1 min-w-0' }, h('b', { class: 'ttl', text: p.title }), h('span', { class: 'a-muted text-xs', text: AGP.fmtDate(p.date) })),
            ib('Move up: ' + p.title, 'up', function () { if (swap(F[key], i, -1)) { save(); draw(); live.textContent = 'Moved up'; } }, i === 0), ib('Move down: ' + p.title, 'down', function () { if (swap(F[key], i, 1)) { save(); draw(); live.textContent = 'Moved down'; } }, i === F[key].length - 1),
            ib('Remove: ' + p.title, 'x', function () { F[key].splice(i, 1); save('Removed from ' + title.toLowerCase() + '.'); draw(); })));
        });
      }
      draw();
      return h('section', { class: 'a-card', 'aria-labelledby': 'h-' + key }, h('div', { class: 'a-card-h' }, h('div', {}, h('h2', { id: 'h-' + key, text: title }), h('p', { class: 'a-muted m-0 text-sm', text: desc })),
        canEdit ? AGP.btn('Choose posts', { icon: 'plus', cls: 'a-btn-sm', on: { click: function () { AGP.pickPosts({ selected: F[key], multi: true, title: title }).then(function (ids) { if (!ids) return; if (ids.length > max) { AGP.toast('Maximum ' + max + ' items. Extra items were ignored.', 'warn'); ids = ids.slice(0, max); } F[key] = ids; save(title + ' updated.'); draw(); }); } } }) : null), ul);
    }

    /* breaking news */
    var bn = h('ul', { class: 'a-list' });
    function drawBN() {
      AGP.clear(bn); if (!F.breaking.length) bn.appendChild(h('li', {}, h('span', { class: 'a-muted', text: 'No breaking news items.' })));
      F.breaking.forEach(function (b, i) {
        var sw = h('label', { class: 'a-switch' }, (function () { var c = h('input', { type: 'checkbox', checked: b.active, disabled: !canEdit, 'aria-label': 'Active: ' + b.text }); c.addEventListener('change', function () { b.active = c.checked; save(b.active ? 'Breaking item is live.' : 'Breaking item hidden.'); }); return c; })(), h('i'));
        bn.appendChild(h('li', {}, sw, h('div', { class: 'flex-1 min-w-0' }, h('b', { text: b.text }), h('span', { class: 'a-muted block text-xs', text: (b.url || 'no link') + (b.expires ? ' • expires ' + AGP.fmtDate(b.expires, true) : '') })),
          ib('Edit: ' + b.text, 'edit', function () { bnForm(b); }), ib('Delete: ' + b.text, 'trash', function () { F.breaking.splice(i, 1); save('Breaking item deleted.'); drawBN(); })));
      });
    }
    function bnForm(b) {
      var isNew = !b;
      AGP.formModal({ title: isNew ? 'Add breaking news' : 'Edit breaking news', fields: [{ name: 'text', label: 'Headline', required: true, value: b ? b.text : '', full: true }, { name: 'url', label: 'Link (optional)', type: 'url', value: b ? b.url : '' }, { name: 'expires', label: 'Auto-hide at', type: 'datetime-local', value: b ? b.expires : '' }, { name: 'active', label: 'Show now', type: 'checkbox', value: b ? b.active : true }],
        onSubmit: function (v) { if (isNew) F.breaking.unshift({ id: AGP.uid('b'), text: v.text, url: v.url, expires: v.expires, active: v.active }); else Object.assign(b, v); save('Breaking news saved.'); drawBN(); } });
    }
    drawBN();

    /* widgets */
    var wl = h('ul', { class: 'a-list' });
    function drawW() {
      AGP.clear(wl);
      F.widgets.forEach(function (w, i) {
        var sw = h('label', { class: 'a-switch' }, (function () { var c = h('input', { type: 'checkbox', checked: w.active, disabled: !canEdit, 'aria-label': 'Active: ' + w.title }); c.addEventListener('change', function () { w.active = c.checked; save(w.title + (w.active ? ' enabled.' : ' disabled.')); }); return c; })(), h('i'));
        wl.appendChild(h('li', {}, sw, h('div', { class: 'flex-1 min-w-0' }, h('b', { text: w.title }), h('span', { class: 'a-muted block text-xs', text: w.type + ' • ' + (w.config || '').slice(0, 60) })),
          ib('Move up: ' + w.title, 'up', function () { if (swap(F.widgets, i, -1)) { save(); drawW(); } }, i === 0), ib('Move down: ' + w.title, 'down', function () { if (swap(F.widgets, i, 1)) { save(); drawW(); } }, i === F.widgets.length - 1),
          ib('Edit: ' + w.title, 'edit', function () { wForm(w); }), ib('Delete: ' + w.title, 'trash', function () { F.widgets.splice(i, 1); save('Widget deleted.'); drawW(); })));
      });
    }
    function wForm(w) {
      var isNew = !w;
      AGP.formModal({ title: isNew ? 'Add sidebar widget' : 'Edit widget', fields: [{ name: 'title', label: 'Title', required: true, value: w ? w.title : '' }, { name: 'type', label: 'Type', type: 'select', options: WT, value: w ? w.type : 'popular' }, { name: 'config', label: 'Settings / HTML content', type: 'textarea', rows: 4, value: w ? w.config : '', hint: 'Custom HTML is sanitized on the public site (scripts and iframes are stripped).' }, { name: 'active', label: 'Enabled', type: 'checkbox', value: w ? w.active : true }],
        onSubmit: function (v) { v.config = v.type === 'html' ? AGP.sanitize(v.config) : v.config; if (isNew) F.widgets.push(Object.assign({ id: AGP.uid('w') }, v)); else Object.assign(w, v); save('Widget saved.'); drawW(); } });
    }
    drawW();

    AGP.mount(root, AGP.head({ eyebrow: 'Newsroom', title: 'Featured & widgets', sub: 'Homepage slider, breaking news ticker, recommended block and sidebar widgets. Changes apply immediately.' }),
      h('div', { class: 'a-grid2', style: null }, postList('slider', 'Homepage slider', 'Up to 5 published posts, in order.', 5), postList('recommended', 'Recommended block', 'Hand-picked posts under the hero (max 6).', 6)),
      h('section', { class: 'a-card mt-4', 'aria-labelledby': 'bnH' }, h('div', { class: 'a-card-h' }, h('div', {}, h('h2', { id: 'bnH', text: 'Breaking news ticker' }), h('p', { class: 'a-muted m-0 text-sm', text: 'Scrolling headlines above the navigation.' })), canEdit ? AGP.btn('Add item', { icon: 'plus', cls: 'a-btn-sm', on: { click: function () { bnForm(null); } } }) : null), bn),
      h('section', { class: 'a-card mt-4', 'aria-labelledby': 'wH' }, h('div', { class: 'a-card-h' }, h('div', {}, h('h2', { id: 'wH', text: 'Sidebar widgets' }), h('p', { class: 'a-muted m-0 text-sm', text: 'Polls, popular posts and custom HTML, ordered top to bottom.' })), canEdit ? AGP.btn('Add widget', { icon: 'plus', cls: 'a-btn-sm', on: { click: function () { wForm(null); } } }) : null), wl), live);
  });
})();
