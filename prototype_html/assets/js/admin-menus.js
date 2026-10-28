/* Navigation builder: drag-and-drop (mouse) plus keyboard buttons (move, indent, outdent), mega menu flag, external links. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var TYPES = [{ v: 'internal', l: 'Internal page' }, { v: 'category', l: 'Category' }, { v: 'external', l: 'External link' }, { v: 'mega', l: 'Mega menu (with children)' }];
  AGP.page('menus', function (root) {
    var canEdit = AGP.can('menus', 'edit');
    var menus = AGP.store.get('menus', {}), key = Object.keys(menus)[0], items = [], dirty = false, drag = -1;
    var list = h('ul', { class: 'a-list', style: null }), prev = h('div', { class: 'a-navprev', role: 'img' }), live = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
    list.style.display = 'block'; list.style.padding = '16px';
    var sel = h('select', { class: 'a-select', id: 'menuSel' });
    function load() { items = JSON.parse(JSON.stringify(menus[key] || [])); dirty = false; draw(); }
    function drawSel() { AGP.mount(sel, Object.keys(menus).map(function (k) { return h('option', { value: k, text: k, selected: k === key ? true : null }); })); }
    sel.addEventListener('change', function () { if (dirty && !window.confirm('Discard unsaved changes to this menu?')) { sel.value = key; return; } key = sel.value; load(); });
    function block(i) { var e = i + 1; while (e < items.length && items[e].depth > items[i].depth) e++; return [i, e]; }
    function fix() { items.forEach(function (it, i) { var max = i ? items[i - 1].depth + 1 : 0; if (it.depth > max) it.depth = max; if (it.depth < 0) it.depth = 0; if (it.depth > 2) it.depth = 2; }); }
    function mv(i, dir) {
      var b = block(i), depth = items[i].depth, label = items[i].label, at;
      if (dir < 0) {
        if (b[0] === 0) return;
        var p = b[0] - 1; while (p > 0 && items[p].depth > depth) p--;
        if (items[p].depth < depth) return;
        at = p; var chunk = items.splice(b[0], b[1] - b[0]); items.splice.apply(items, [at, 0].concat(chunk));
      } else {
        if (b[1] >= items.length || items[b[1]].depth < depth) return;
        var nb = block(b[1]), len = b[1] - b[0], c2 = items.splice(b[0], len);
        items.splice.apply(items, [nb[1] - len, 0].concat(c2));
      }
      fix(); dirty = true; draw(); live.textContent = 'Moved ' + label + (dir < 0 ? ' up' : ' down');
    }
    function itemForm(it, idx) {
      var isNew = !it;
      var cats = AGP.catFlat().map(function (f) { return { v: f.c.slug, l: '— '.repeat(f.depth) + AGP.catName(f.c) }; });
      AGP.formModal({ title: isNew ? 'Add menu item' : 'Edit menu item', submit: isNew ? 'Add' : 'Save', fields: [
        { name: 'label', label: 'Label', required: true, value: it ? it.label : '' }, { name: 'type', label: 'Type', type: 'select', options: TYPES, value: it ? it.type : 'internal' },
        { name: 'url', label: 'URL or path', required: true, value: it ? it.url : '', hint: 'e.g. ../fr/contact.html or https://example.org', validate: function (v) { return /^(https?:\/\/|\.\.?\/|\/|#|[\w-]+\.html)/i.test(v) && !/^javascript:/i.test(v) ? '' : 'Use http(s):// or a relative path.'; } },
        { name: 'cat', label: 'Or pick a category (fills the URL)', type: 'select', options: [{ v: '', l: '—' }].concat(cats), value: '' },
        { name: 'blank', label: 'Open in a new tab', type: 'checkbox', value: it ? it.blank : false }], onSubmit: function (v) {
        if (v.cat && !v.url) v.url = '../fr/' + v.cat + '.html';
        var rec = { id: it ? it.id : AGP.uid('mi'), label: v.label, url: v.url, type: v.type, depth: it ? it.depth : 0, blank: v.blank };
        if (isNew) items.push(rec); else items[idx] = rec; fix(); dirty = true; draw();
      } });
    }
    function draw() {
      AGP.clear(list);
      if (!items.length) list.appendChild(h('li', { class: 'a-empty', text: 'This menu is empty. Add an item.' }));
      items.forEach(function (it, i) {
        var li = h('li', { class: 'a-menu-item', draggable: canEdit ? 'true' : null, 'data-i': String(i) });
        li.style.marginLeft = (it.depth * 28) + 'px';
        var info = h('div', { class: 'flex-1 min-w-0' }, h('b', { text: it.label }), h('span', { class: 'a-muted block text-xs', text: it.url + (it.blank ? ' (new tab)' : '') }));
        li.appendChild(h('span', { class: 'a-drag', 'aria-hidden': 'true', title: 'Drag to reorder' }, AGP.icon('grip'))); li.appendChild(info);
        li.appendChild(h('span', { class: 'a-badge ' + (it.type === 'mega' ? 'flash' : ''), text: it.type }));
        if (canEdit) {
          var ctl = [['Move up', 'up', function () { mv(i, -1); }, i === 0], ['Move down', 'down', function () { mv(i, 1); }, block(i)[1] >= items.length], ['Indent (make sub-item)', 'indent', function () { items[i].depth++; fix(); dirty = true; draw(); }, i === 0 || it.depth >= 2 || it.depth > items[i - 1].depth], ['Outdent', 'outdent', function () { items[i].depth--; fix(); dirty = true; draw(); }, it.depth === 0], ['Edit', 'edit', function () { itemForm(it, i); }, false], ['Delete', 'trash', function () { var b = block(i); var removed = items.splice(b[0], 1); fix(); dirty = true; draw(); AGP.toast('Removed "' + removed[0].label + '". Save to apply.', 'warn'); }, false]];
          ctl.forEach(function (c) { li.appendChild(h('button', { type: 'button', class: 'a-iconbtn sm', title: c[0], 'aria-label': c[0] + ': ' + it.label, disabled: c[3], on: { click: c[2] } }, AGP.icon(c[1]))); });
          li.addEventListener('dragstart', function (e) { drag = i; li.classList.add('a-dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(i)); });
          li.addEventListener('dragend', function () { li.classList.remove('a-dragging'); drag = -1; });
          li.addEventListener('dragover', function (e) { if (drag < 0) return; e.preventDefault(); li.classList.add('a-dropline'); });
          li.addEventListener('dragleave', function () { li.classList.remove('a-dropline'); });
          li.addEventListener('drop', function (e) {
            e.preventDefault(); li.classList.remove('a-dropline'); if (drag < 0 || drag === i) return;
            var b = block(drag); if (i >= b[0] && i < b[1]) return;
            var chunk = items.splice(b[0], b[1] - b[0]), at = i > b[0] ? i - chunk.length : i; items.splice.apply(items, [at, 0].concat(chunk)); fix(); dirty = true; draw(); live.textContent = 'Reordered.';
          });
        }
        list.appendChild(li);
      });
      AGP.mount(prev, items.filter(function (x) { return x.depth === 0; }).map(function (x) { return h('span', { text: x.label }); }).concat(items.filter(function (x) { return x.depth > 0; }).length ? [h('span', { class: 'sub', text: '+ ' + items.filter(function (x) { return x.depth > 0; }).length + ' sub-items' })] : []));
      prev.setAttribute('aria-label', 'Navigation preview: ' + items.filter(function (x) { return x.depth === 0; }).map(function (x) { return x.label; }).join(', '));
      saveBtn.disabled = !dirty; state.textContent = dirty ? 'Unsaved changes' : 'Saved';
    }
    var state = h('span', { class: 'a-badge', role: 'status' });
    var saveBtn = AGP.btn('Save menu', { icon: 'check', cls: 'a-btn-primary', on: { click: function () { menus[key] = JSON.parse(JSON.stringify(items)); AGP.store.set('menus', menus); dirty = false; draw(); AGP.toast('Menu "' + key + '" saved.', 'ok'); } } });
    var acts = canEdit ? [AGP.btn('Add item', { icon: 'plus', on: { click: function () { itemForm(null); } } }), AGP.btn('New menu', { icon: 'menu', on: { click: function () { AGP.formModal({ title: 'New menu', submit: 'Create', fields: [{ name: 'name', label: 'Menu name', required: true, hint: 'e.g. footer-en' }], onSubmit: function (v) { var k = AGP.slugify(v.name); if (menus[k]) { AGP.toast('A menu with this name exists.', 'err'); return false; } menus[k] = []; AGP.store.set('menus', menus); key = k; drawSel(); load(); } }); } } }), saveBtn] : [];
    drawSel();
    AGP.mount(root, AGP.head({ eyebrow: 'Site', title: 'Menus', sub: 'Drag rows to reorder, or use the buttons (keyboard friendly). Up to 3 levels, mega menu and external links supported.', actions: acts }),
      h('div', { class: 'a-card' }, h('div', { class: 'a-tools' }, h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'menuSel', text: 'Menu' }), sel), state), list),
      h('div', { class: 'a-card mt-4' }, h('div', { class: 'a-card-h' }, h('h2', { text: 'Preview' })), h('div', { class: 'a-card-b' }, prev)), live);
    load();
  });
})();
