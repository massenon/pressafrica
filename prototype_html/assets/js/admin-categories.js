/* Categories: unlimited nesting tree (ARIA tree, keyboard nav), FR/EN names, per-category SEO. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $ = AGP.$, $$ = AGP.$$;
  AGP.page('categories', function (root) {
    var canEdit = AGP.can('categories', 'edit'), canCreate = AGP.can('categories', 'create'), canDel = AGP.can('categories', 'delete');
    var lang = 'all', open = {}, treeEl = h('ul', { class: 'a-tree', role: 'tree', 'aria-label': 'Category hierarchy' }), live = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
    var cats = AGP.cats();
    cats.forEach(function (c) { open[c.id] = true; });
    function save() { AGP.store.set('categories', cats); }
    function counts() { var n = {}; AGP.posts().forEach(function (p) { (p.categoryIds || []).forEach(function (i) { n[i] = (n[i] || 0) + 1; }); }); return n; }
    function descendants(id) { var out = []; (function w(i) { cats.forEach(function (c) { if (c.parent === i) { out.push(c.id); w(c.id); } }); })(id); return out; }
    function label(c) { var n = lang === 'all' ? (c.name.fr || c.name.en) : c.name[lang]; return n || ((c.name.fr || c.name.en) + ' (no ' + lang.toUpperCase() + ' name)'); }

    function form(c, parentId) {
      var isNew = !c, banned = c ? [c.id].concat(descendants(c.id)) : [];
      var opts = [{ v: '', l: '(top level)' }].concat(AGP.catFlat(cats).filter(function (f) { return banned.indexOf(f.c.id) < 0; }).map(function (f) { return { v: f.c.id, l: '— '.repeat(f.depth) + label(f.c) }; }));
      AGP.formModal({
        title: isNew ? 'New category' : 'Edit category', submit: isNew ? 'Create' : 'Save',
        fields: [
          { name: 'fr', label: 'Name (Français)', value: c ? c.name.fr : '' }, { name: 'en', label: 'Name (English)', value: c ? c.name.en : '' },
          { name: 'slug', label: 'Slug', value: c ? c.slug : '', hint: 'Auto-generated if empty.', validate: function (v) { return v && cats.some(function (x) { return x.slug === AGP.slugify(v) && (!c || x.id !== c.id); }) ? 'Slug already in use.' : ''; } },
          { name: 'parent', label: 'Parent', type: 'select', options: opts, value: c ? c.parent : (parentId || '') },
          { name: 'desc', label: 'Description', type: 'textarea', value: c ? c.desc : '', rows: 2 },
          { name: 'seoTitle', label: 'SEO title', value: c ? c.seoTitle : '' }, { name: 'seoDesc', label: 'SEO description', type: 'textarea', rows: 2, value: c ? c.seoDesc : '' }
        ],
        onSubmit: function (v) {
          if (!v.fr && !v.en) { AGP.toast('Enter a name in at least one language.', 'err'); return false; }
          var rec = c || { id: AGP.uid('c') };
          rec.name = { fr: v.fr, en: v.en }; rec.slug = AGP.slugify(v.slug || v.fr || v.en); rec.parent = v.parent; rec.desc = v.desc; rec.seoTitle = v.seoTitle; rec.seoDesc = v.seoDesc;
          if (isNew) cats.push(rec);
          if (rec.parent) open[rec.parent] = true;
          save(); draw(); AGP.toast(isNew ? 'Category created.' : 'Category saved.', 'ok');
        }
      });
    }
    function move(c, dir) {
      var sibs = cats.filter(function (x) { return x.parent === c.parent; }), i = sibs.indexOf(c), j = i + dir;
      if (j < 0 || j >= sibs.length) return;
      var a = cats.indexOf(c), b = cats.indexOf(sibs[j]); cats[a] = sibs[j]; cats[b] = c; save(); draw(c.id); live.textContent = label(c) + ' moved ' + (dir < 0 ? 'up' : 'down') + '.';
    }
    function del(c) {
      var kids = cats.filter(function (x) { return x.parent === c.id; }).length;
      AGP.confirm({ title: 'Delete “' + label(c) + '”?', text: (kids ? kids + ' child categor' + (kids > 1 ? 'ies move' : 'y moves') + ' up one level. ' : '') + 'Posts keep their other categories.', ok: 'Delete', danger: true }).then(function (ok) {
        if (!ok) return;
        cats.forEach(function (x) { if (x.parent === c.id) x.parent = c.parent; });
        cats.splice(cats.indexOf(c), 1); save();
        var posts = AGP.posts(); posts.forEach(function (p) { p.categoryIds = (p.categoryIds || []).filter(function (i) { return i !== c.id; }); }); AGP.savePosts(posts);
        draw(); AGP.toast('Category deleted.', 'warn');
      });
    }
    function node(n, level, cnt) {
      var c = n.c, hasKids = n.kids.length > 0;
      var li = h('li', { role: 'treeitem', 'aria-level': String(level), 'aria-expanded': hasKids ? String(!!open[c.id]) : null, 'aria-label': label(c), tabindex: '-1', 'data-id': c.id });
      var tog = hasKids ? h('button', { type: 'button', class: 'a-iconbtn sm', tabindex: '-1', 'aria-label': (open[c.id] ? 'Collapse ' : 'Expand ') + label(c), on: { click: function (e) { e.stopPropagation(); open[c.id] = !open[c.id]; draw(c.id); } } }, AGP.icon(open[c.id] ? 'down' : 'right')) : h('span', { class: 'inline-block', style: null }); if (!hasKids) tog.style.width = '32px';
      var acts = h('div', { class: 'flex gap-1 flex-wrap' });
      function ab(lbl, ic, fn) { acts.appendChild(h('button', { type: 'button', class: 'a-iconbtn sm', tabindex: '-1', title: lbl, 'aria-label': lbl + ': ' + label(c), on: { click: function (e) { e.stopPropagation(); fn(); } } }, AGP.icon(ic))); }
      if (canCreate) ab('Add child', 'plus', function () { form(null, c.id); });
      if (canEdit) { ab('Move up', 'up', function () { move(c, -1); }); ab('Move down', 'down', function () { move(c, 1); }); ab('Edit', 'edit', function () { form(c); }); }
      if (canDel) ab('Delete', 'trash', function () { del(c); });
      var row = h('div', { class: 'a-tree-row' }, tog, h('div', { class: 'flex-1 min-w-0' }, h('b', { text: label(c) }), h('span', { class: 'a-muted text-xs block', text: '/' + c.slug })),
        c.name.fr ? h('span', { class: 'a-badge', text: 'FR' }) : null, c.name.en ? h('span', { class: 'a-badge', text: 'EN' }) : null, h('span', { class: 'a-badge info', text: (cnt[c.id] || 0) + ((cnt[c.id] || 0) === 1 ? ' post' : ' posts') }), acts);
      li.appendChild(row);
      if (hasKids && open[c.id]) { var ul = h('ul', { role: 'group' }); n.kids.forEach(function (k) { ul.appendChild(node(k, level + 1, cnt)); }); li.appendChild(ul); }
      return li;
    }
    function draw(focusId) {
      AGP.clear(treeEl); var cnt = counts();
      var roots = AGP.catTree(cats).filter(function (n) { return true; });
      roots.forEach(function (n) { treeEl.appendChild(node(n, 1, cnt)); });
      var items = $$('[role=treeitem]', treeEl); items.forEach(function (i, k) { i.tabIndex = -1; });
      var t = focusId && $('[data-id="' + focusId + '"]', treeEl) || items[0]; if (t) { t.tabIndex = 0; if (focusId) t.focus(); }
      stats.textContent = cats.length + ' categories, ' + AGP.catFlat(cats).filter(function (f) { return f.depth > 0; }).length + ' nested.';
    }
    var stats = h('p', { class: 'a-muted' });
    treeEl.addEventListener('keydown', function (e) {
      var it = e.target.closest && e.target.closest('[role=treeitem]'); if (!it || e.target !== it) return;
      var vis = $$('[role=treeitem]', treeEl), i = vis.indexOf(it), c = cats.filter(function (x) { return x.id === it.dataset.id; })[0], k = e.key, go = null;
      if (k === 'ArrowDown') go = vis[i + 1]; else if (k === 'ArrowUp') go = vis[i - 1]; else if (k === 'Home') go = vis[0]; else if (k === 'End') go = vis[vis.length - 1];
      else if (k === 'ArrowRight') { if (it.getAttribute('aria-expanded') === 'false') { open[c.id] = true; draw(c.id); return e.preventDefault(); } go = it.querySelector('ul [role=treeitem]'); }
      else if (k === 'ArrowLeft') { if (it.getAttribute('aria-expanded') === 'true') { open[c.id] = false; draw(c.id); return e.preventDefault(); } go = it.parentElement.closest('[role=treeitem]'); }
      else if ((k === 'Enter' || k === ' ') && canEdit) { e.preventDefault(); form(c); return; }
      else if (k === 'Delete' && canDel) { del(c); return; }
      if (go) { e.preventDefault(); $$('[role=treeitem]', treeEl).forEach(function (x) { x.tabIndex = -1; }); go.tabIndex = 0; go.focus(); }
    });
    var langSel = h('select', { class: 'a-select', id: 'catLang', 'aria-label': 'Display language' }, [['all', 'FR + EN'], ['fr', 'Français'], ['en', 'English']].map(function (o) { return h('option', { value: o[0], text: o[1] }); }));
    langSel.style.width = 'auto'; langSel.addEventListener('change', function () { lang = langSel.value; draw(); });
    var actions = [h('div', { class: 'flex items-center gap-2' }, h('label', { class: 'a-label', style: null, for: 'catLang', text: 'Show names in' }), langSel)];
    actions[0].firstChild.style.margin = '0';
    if (canCreate) actions.push(AGP.btn('New category', { icon: 'plus', cls: 'a-btn-primary', on: { click: function () { form(null, ''); } } }));
    AGP.mount(root, AGP.head({ eyebrow: 'Newsroom', title: 'Categories', sub: 'Unlimited nesting (Parent > Child > Grandchild) with French and English names. Arrow keys navigate, Enter edits.', actions: actions }),
      h('div', { class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { text: 'Hierarchy' }), stats), h('div', { class: 'a-card-b' }, treeEl)), live);
    draw();
  });
})();
