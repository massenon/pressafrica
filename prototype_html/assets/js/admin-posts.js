/* Posts list: reactive DataTable, filters, bulk actions, CRUD in modals, toasts with undo. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var FORMATS = [['article', 'Article'], ['recipe', 'Recipe'], ['video', 'Video'], ['audio', 'Audio'], ['quiz', 'Quiz'], ['poll', 'Poll'], ['gallery', 'Gallery'], ['event', 'Event']];
  AGP.FORMATS = FORMATS;

  AGP.page('posts', function (root) {
    var me = AGP.user(), role = AGP.role(), own = role && role.id === 'author', P = AGP.params();
    var canCreate = AGP.can('posts', 'create'), canEdit = AGP.can('posts', 'edit'), canDel = AGP.can('posts', 'delete'), canPub = AGP.can('posts', 'publish');
    function rows() { var a = AGP.posts().filter(function (p) { return p.status !== 'trash' || true; }); return own ? a.filter(function (p) { return p.author === me.id; }) : a; }
    var flat = AGP.catFlat();
    var users = AGP.users();
    var tableEl = h('section', { 'aria-label': 'Posts list' });
    var cat = function (id) { var c = AGP.cats().filter(function (x) { return x.id === id; })[0]; return c ? AGP.catName(c) : ''; };

    function toggleTrash(list, status) { var a = AGP.posts(); var ids = list.map(function (p) { return p.id; }); a.forEach(function (p) { if (ids.indexOf(p.id) > -1) p.status = status; }); AGP.savePosts(a); }
    function remove(list) {
      var back = AGP.posts().filter(function (p) { return list.some(function (l) { return l.id === p.id; }); }), ids = list.map(function (p) { return p.id; });
      AGP.savePosts(AGP.posts().filter(function (p) { return ids.indexOf(p.id) < 0; })); t.refresh();
      AGP.toast(ids.length + ' post(s) deleted.', 'warn', { action: { label: 'Undo', run: function () { AGP.savePosts(back.concat(AGP.posts())); t.refresh(); AGP.toast('Restored.', 'ok'); } } });
    }
    function setStatus(list, status, msg) { toggleTrash(list, status); t.refresh(); AGP.toast(msg || (list.length + ' post(s) set to ' + status + '.'), 'ok'); }

    function editModal(p) {
      var isNew = !p; p = p || { id: AGP.uid('p'), status: 'draft', lang: 'fr', format: 'article', author: me.id, categoryIds: [], date: AGP.nowLocal(), content: '', excerpt: '', views: 0, comments: 0, faq: [], fd: {}, tags: [], image: { path: '', alt: '' }, seoTitle: '', seoDesc: '' };
      var statuses = [['draft', 'Draft'], ['pending', 'Pending review']].concat(canPub ? [['published', 'Published'], ['scheduled', 'Scheduled']] : []);
      AGP.formModal({
        title: isNew ? 'Quick-add post' : 'Quick edit post', submit: isNew ? 'Create post' : 'Save changes',
        fields: [
          { name: 'title', label: 'Title', required: true, value: p.title, full: true },
          { name: 'slug', label: 'Slug', value: p.slug, hint: 'Leave empty to generate from the title.' },
          { name: 'status', label: 'Status', type: 'select', options: statuses.map(function (s) { return { v: s[0], l: s[1] }; }), value: p.status },
          { name: 'lang', label: 'Language', type: 'select', options: [{ v: 'fr', l: 'Français' }, { v: 'en', l: 'English' }], value: p.lang },
          { name: 'format', label: 'Format', type: 'select', options: FORMATS.map(function (f) { return { v: f[0], l: f[1] }; }), value: p.format },
          { name: 'cat', label: 'Main category', type: 'select', options: [{ v: '', l: 'None' }].concat(flat.map(function (f) { return { v: f.c.id, l: '— '.repeat(f.depth) + AGP.catName(f.c) }; })), value: (p.categoryIds || [])[0] || '' },
          { name: 'author', label: 'Author', type: 'select', options: users.map(function (u) { return { v: u.id, l: u.name }; }), value: p.author },
          { name: 'excerpt', label: 'Excerpt', type: 'textarea', value: p.excerpt, rows: 3 }
        ],
        onSubmit: function (v) {
          var all = AGP.posts(), rec = isNew ? p : all.filter(function (x) { return x.id === p.id; })[0];
          rec.title = v.title; rec.slug = v.slug ? AGP.slugify(v.slug) : AGP.slugify(v.title); rec.status = v.status; rec.lang = v.lang; rec.format = v.format; rec.author = own ? me.id : v.author; rec.excerpt = v.excerpt;
          rec.categoryIds = v.cat ? [v.cat].concat((rec.categoryIds || []).slice(1)) : []; rec.modified = AGP.nowLocal();
          if (isNew) all.unshift(rec);
          AGP.savePosts(all); t.refresh(); AGP.toast(isNew ? 'Post created.' : 'Post updated.', 'ok');
        }
      });
    }
    function preview(p) {
      var body = h('article', {}, h('p', { class: 'a-eyebrow', text: p.lang.toUpperCase() + ' • ' + p.format + ' • ' + AGP.fmtDate(p.date) }), h('p', { class: 'a-muted', text: p.excerpt }));
      var c = h('div', { class: 'a-rte-area' }); c.innerHTML = AGP.sanitize(p.content); body.appendChild(c);
      AGP.modal({ title: p.title, size: 'lg', body: body, actions: [{ label: 'Close', cls: 'a-btn-ghost' }].concat(canEdit ? [{ label: 'Open in editor', cls: 'a-btn-primary', run: function () { location.href = 'post-edit.html?id=' + encodeURIComponent(p.id); return false; } }] : []) });
    }

    var t = AGP.table(tableEl, {
      id: 'id', label: 'Posts', searchPlaceholder: 'Search by title, author, slug…', pageSize: 10, sort: { key: 'date', dir: 'desc' },
      initial: { q: P.get('q') || '', f: { author: P.get('author') || '', status: P.get('status') || '' } },
      rows: rows, rowLabel: function (r) { return r.title; },
      search: function (r) { return r.title + ' ' + r.slug + ' ' + AGP.userName(r.author) + ' ' + r.excerpt; },
      filters: [
        { key: 'status', label: 'Status', options: [['published', 'Published'], ['draft', 'Draft'], ['pending', 'Pending'], ['scheduled', 'Scheduled']], test: function (r, v) { return r.status === v; } },
        { key: 'cat', label: 'Category', options: flat.map(function (f) { return [f.c.id, '— '.repeat(f.depth) + AGP.catName(f.c)]; }), test: function (r, v) { return (r.categoryIds || []).indexOf(v) > -1; } },
        { key: 'lang', label: 'Language', options: [['fr', 'Français'], ['en', 'English']], test: function (r, v) { return r.lang === v; } },
        { key: 'author', label: 'Author', options: users.map(function (u) { return [u.id, u.name]; }), test: function (r, v) { return r.author === v; } }
      ],
      columns: [
        { key: 'title', label: 'Post', sort: function (r) { return r.title; }, render: function (r) {
          var th = AGP.thumb(r);
          var a = canEdit ? h('a', { class: 'ttl', href: 'post-edit.html?id=' + encodeURIComponent(r.id), text: r.title }) : h('span', { class: 'ttl', text: r.title });
          return h('div', { class: 'flex gap-3 items-center' }, th, h('div', {}, a, h('span', { class: 'a-muted text-xs', text: r.format + ' • /' + r.slug.slice(0, 36) })));
        } },
        { key: 'author', label: 'Author', cls: 'text-sm', sort: function (r) { return AGP.userName(r.author); }, render: function (r) { return AGP.userName(r.author); } },
        { key: 'cat', label: 'Categories', render: function (r) { return (r.categoryIds || []).slice(0, 2).map(cat).join(', '); } },
        { key: 'lang', label: 'Lang', sort: true, render: function (r) { return h('span', { class: 'a-badge', text: r.lang.toUpperCase() }); } },
        { key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status); } },
        { key: 'date', label: 'Date', sort: true, render: function (r) { return AGP.fmtDate(r.date); } },
        { key: 'views', label: 'Views', sort: function (r) { return r.views || 0; }, render: function (r) { return (r.views || 0).toLocaleString('en-GB'); } }
      ],
      actions: function (r) {
        var a = [{ label: 'Preview', icon: 'eye', run: preview }];
        if (canEdit) a.push({ label: 'Quick edit', icon: 'edit', run: editModal });
        if (canCreate) a.push({ label: 'Duplicate', icon: 'copy', run: function (p) { var c = JSON.parse(JSON.stringify(p)); c.id = AGP.uid('p'); c.title += ' (copy)'; c.slug += '-copy'; c.status = 'draft'; var all = AGP.posts(); all.unshift(c); AGP.savePosts(all); t.refresh(); AGP.toast('Duplicated as draft.', 'ok'); } });
        if (canDel) a.push({ label: 'Delete', icon: 'trash', run: function (p) { AGP.confirm({ title: 'Delete this post?', text: '“' + p.title + '” will be removed. You can undo right after.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) remove([p]); }); } });
        return a;
      },
      bulk: [].concat(canPub ? [{ label: 'Publish', icon: 'check', run: function (l) { setStatus(l, 'published'); } }] : [], canEdit ? [{ label: 'Move to draft', icon: 'edit', run: function (l) { setStatus(l, 'draft'); } }, { label: 'Submit for review', icon: 'clock', run: function (l) { setStatus(l, 'pending'); } }] : [],
        [{ label: 'Export JSON', icon: 'download', run: function (l) { AGP.download('posts.json', JSON.stringify(l.map(function (p) { return { id: p.id, title: p.title, slug: p.slug, status: p.status, lang: p.lang, date: p.date }; }), null, 2), 'application/json'); } }],
        canDel ? [{ label: 'Delete', icon: 'trash', cls: 'a-btn-danger', run: function (l) { return AGP.confirm({ title: 'Delete ' + l.length + ' posts?', text: 'This removes them from the list. You can undo right after.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) remove(l); }); } }] : []),
      emptyState: { icon: 'file', title: 'No posts yet', text: 'Write your first story, pick a format and move it through review.', action: canCreate ? { label: 'New post', icon: 'plus', href: 'post-edit.html' } : null }
    });
    var actions = [];
    if (canCreate) actions.push(AGP.btn('Quick add', { icon: 'plus', on: { click: function () { editModal(null); } } }), AGP.btn('New post (full editor)', { icon: 'edit', cls: 'a-btn-primary', href: 'post-edit.html' }));
    AGP.mount(root, AGP.head({ eyebrow: own ? 'My posts' : 'Newsroom', title: own ? 'My posts' : 'Posts', sub: own ? 'Only your own posts are shown. Submit drafts for review when ready.' : 'Search, filter, review and publish. Bulk-select rows for batch actions.', actions: actions }), tableEl);
  });
})();
