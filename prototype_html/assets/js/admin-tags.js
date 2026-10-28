/* Tags: keyword taxonomy shared by all posts. Tags live on the posts themselves (post.tags) plus a catalogue of unused tags ('tagcat').
   Rename, merge and delete rewrite every post that carries the tag. On first run, tags are suggested from article titles so the screen is not empty. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var RULES = [['CAN 2025', /\bcan 2025\b|afcon/i], ['Élections', /élection|election|présidentielle|presidential|scrutin/i], ['Économie', /économ|econom|croissance|growth|agricultur|export/i], ['Finance', /financ|budget|cedi|fiscal|banque|bank/i],
    ['Gouvernance', /gouvernance|governance|démocratie|democracy|cedeao|ecowas/i], ['Santé', /santé|health|mortalit|sanitaire/i], ['Jeunesse', /jeunesse|youth|jeunes/i], ['Sécurité', /sécurité|security|violence|terror/i], ['Culture', /culture|cultur/i], ['Éducation', /éducation|education|écolier|school/i]];
  function slug(t) { return AGP.slugify(t); }
  function seedTags() {
    if (AGP.store.get('tagseed', 0)) return;
    var posts = AGP.posts(), n = 0;
    posts.forEach(function (p) {
      if (p.tags && p.tags.length) return; var txt = (p.title || '') + ' ' + (p.excerpt || ''), tags = [];
      RULES.forEach(function (r) { if (r[1].test(txt) && tags.length < 3) tags.push(r[0]); });
      if (tags.length) { p.tags = tags; n++; }
    });
    AGP._seeding = true; AGP.savePosts(posts); AGP.store.set('tagseed', 1); AGP._seeding = false;
  }
  function all() {
    var count = {}, last = {};
    AGP.posts().forEach(function (p) { (p.tags || []).forEach(function (t) { count[t] = (count[t] || 0) + 1; if (!last[t] || (p.date || '') > last[t]) last[t] = p.date || ''; }); });
    AGP.store.get('tagcat', []).forEach(function (t) { if (!(t in count)) count[t] = 0; });
    return Object.keys(count).map(function (t) { return { id: t, tag: t, slug: slug(t), posts: count[t], last: last[t] || '' }; });
  }
  function rewrite(fn) { var posts = AGP.posts(); posts.forEach(function (p) { p.tags = fn(p.tags || []); }); AGP.savePosts(posts); }
  function uniq(a) { var o = {}; return a.filter(function (x) { if (o[x]) return false; o[x] = 1; return true; }); }

  AGP.page('tags', function (root) {
    seedTags();
    var canC = AGP.can('tags', 'create'), canE = AGP.can('tags', 'edit'), canD = AGP.can('tags', 'delete');
    var tEl = h('section', { 'aria-label': 'Tags' });
    function nameField(v) { return { name: 'name', label: 'Tag name', required: true, value: v || '', validate: function (x) { return x.length > 40 ? 'Maximum 40 characters.' : ''; } }; }
    function create() {
      AGP.formModal({ title: 'New tag', submit: 'Create', fields: [nameField()], onSubmit: function (v) {
        if (all().some(function (t) { return t.tag.toLowerCase() === v.name.toLowerCase(); })) { AGP.toast('That tag already exists.', 'err'); return false; }
        var c = AGP.store.get('tagcat', []); c.push(v.name); AGP.store.set('tagcat', c); t.refresh(); AGP.toast('Tag created.', 'ok');
      } });
    }
    function rename(r) {
      AGP.formModal({ title: 'Rename tag', submit: 'Save', fields: [nameField(r.tag)], onSubmit: function (v) {
        if (v.name !== r.tag && all().some(function (x) { return x.tag.toLowerCase() === v.name.toLowerCase(); })) { AGP.toast('That tag already exists. Use Merge instead.', 'err'); return false; }
        rewrite(function (ts) { return uniq(ts.map(function (x) { return x === r.tag ? v.name : x; })); });
        AGP.store.set('tagcat', AGP.store.get('tagcat', []).map(function (x) { return x === r.tag ? v.name : x; })); t.refresh(); AGP.toast('Tag renamed.', 'ok');
      } });
    }
    function merge(r) {
      var others = all().filter(function (x) { return x.tag !== r.tag; }); if (!others.length) { AGP.toast('There is no other tag to merge into.', 'warn'); return; }
      AGP.formModal({ title: 'Merge "' + r.tag + '" into…', submit: 'Merge', fields: [{ name: 'into', label: 'Keep this tag', type: 'select', value: others[0].tag, options: others.map(function (x) { return { v: x.tag, l: x.tag + ' (' + x.posts + ')' }; }) }], onSubmit: function (v) {
        rewrite(function (ts) { return uniq(ts.map(function (x) { return x === r.tag ? v.into : x; })); });
        AGP.store.set('tagcat', AGP.store.get('tagcat', []).filter(function (x) { return x !== r.tag; })); t.refresh(); AGP.toast('Merged into "' + v.into + '".', 'ok');
      } });
    }
    function remove(list) {
      var names = list.map(function (x) { return x.tag; }), n = list.reduce(function (a, x) { return a + x.posts; }, 0);
      return AGP.confirm({ title: 'Delete ' + (names.length === 1 ? '"' + names[0] + '"' : names.length + ' tags') + '?', text: n ? 'The tag is removed from ' + n + ' post(s). The posts themselves are not deleted.' : 'The tag is not used by any post.', ok: 'Delete', danger: true }).then(function (ok) {
        if (!ok) return; rewrite(function (ts) { return ts.filter(function (x) { return names.indexOf(x) < 0; }); });
        AGP.store.set('tagcat', AGP.store.get('tagcat', []).filter(function (x) { return names.indexOf(x) < 0; })); t.refresh(); AGP.toast('Deleted.', 'warn');
      });
    }
    var t = AGP.table(tEl, {
      emptyState: { icon: 'tag', title: 'No tags yet', text: 'Tags added to posts appear here.', action: canC ? { label: 'New tag', icon: 'plus', run: create } : null },
      id: 'id', label: 'Tags', rows: all, rowLabel: function (r) { return r.tag; }, search: function (r) { return r.tag + ' ' + r.slug; }, sort: { key: 'posts', dir: 'desc' }, pageSize: 25,
      filters: [{ key: 'use', label: 'Usage', options: [['used', 'Used'], ['unused', 'Unused']], test: function (r, v) { return (v === 'used') === (r.posts > 0); } }],
      columns: [{ key: 'tag', label: 'Tag', sort: true, render: function (r) { return h('b', { text: r.tag }); } }, { key: 'slug', label: 'Slug', sort: true, render: function (r) { return h('span', { class: 'a-mono a-muted', text: r.slug }); } },
        { key: 'posts', label: 'Posts', sort: true, render: function (r) { return r.posts ? h('a', { href: 'posts.html?q=' + encodeURIComponent(r.tag), text: String(r.posts) }) : document.createTextNode('0'); } },
        { key: 'last', label: 'Last used', sort: true, render: function (r) { return r.last ? AGP.fmtDate(r.last) : document.createTextNode('-'); } }],
      actions: function (r) { var a = []; if (canE) { a.push({ label: 'Rename', icon: 'edit', run: function () { rename(r); } }); a.push({ label: 'Merge into…', icon: 'copy', run: function () { merge(r); } }); } if (canD) a.push({ label: 'Delete', icon: 'trash', run: function () { remove([r]); } }); return a; },
      bulk: canD ? [{ label: 'Delete', icon: 'trash', cls: 'a-btn-danger', run: remove }] : []
    });
    AGP.mount(root, AGP.head({ eyebrow: 'Newsroom', title: 'Tags', sub: 'Keywords that group articles across sections. Renaming or merging a tag updates every post that uses it.', actions: canC ? [AGP.btn('New tag', { icon: 'plus', on: { click: create } })] : [] }), tEl);
  });
})();
