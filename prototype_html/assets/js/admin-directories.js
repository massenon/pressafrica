/* Channel modules: Videos, Magazine, African radios, African television. One factory, four management pages (bilingual FR/EN content,
   publish workflow, search/sort/filter, bulk actions, JSON export for the public site). Data lives in localStorage and is seeded from
   admin/directories-seed.js (a copy of data/videos.json, magazine.json, radios.json, tv.json, written by tools/sync_admin_seeds.py).
   Publish flow (mock): Export JSON, replace the data file, run tools/build_site.py. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var STATUS = [{ v: 'draft', l: 'Draft' }, { v: 'published', l: 'Published' }];
  var LANGS = [{ v: 'fr', l: 'Français' }, { v: 'en', l: 'English' }, { v: 'fr,en', l: 'Français + English' }];
  var GENRES = [{ v: 'news', l: 'News' }, { v: 'public', l: 'Public service' }, { v: 'culture', l: 'Culture' }];
  var today = function () { return new Date().toISOString().slice(0, 10); };
  var clock = function (s) { s = +s || 0; return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
  var bi = function (r, k) { return (r.en && r.en[k]) || (r.fr && r.fr[k]) || ''; };
  var urlOk = function (v) { return !v || /^https:\/\/[^\s]+\.[^\s]+$/i.test(v) ? '' : 'Use a full https:// address, or leave empty.'; };
  var idsOk = function (v) { return !v || /^\d+(\s*,\s*\d+)*$/.test(v) ? '' : 'Comma-separated numeric article ids, e.g. 4617, 4632.'; };

  function register(c) {
    function db() {
      var d = AGP.store.get(c.key, null);
      if (!d) { d = JSON.parse(JSON.stringify(window[c.seed] || c.empty)); AGP.store.set(c.key, d); }
      return d;
    }
    function save(d) { AGP.store.set(c.key, d); }
    AGP.page(c.page, function (root) {
      var canC = AGP.can(c.page, 'create'), canE = AGP.can(c.page, 'edit'), canD = AGP.can(c.page, 'delete'), canP = AGP.can(c.page, 'publish');
      var tEl = h('section', { 'aria-label': c.plural });
      function rows() { return db()[c.list]; }
      function edit(rec) {
        var d = db();
        AGP.formModal({
          title: (rec ? 'Edit ' : 'New ') + c.noun, size: 'lg', submit: rec ? 'Save' : 'Create',
          fields: c.fields(rec || c.blank(d), d, canP).map(function (f) { return f; }),
          onSubmit: function (v) {
            var dd = db(), cur = rec ? dd[c.list].filter(function (x) { return x.id === rec.id; })[0] : { id: c.id(dd) };
            c.apply(cur, v); if (!canP && !rec) cur.status = 'draft';
            if (!rec) dd[c.list].push(cur);
            save(dd); t.refresh(); AGP.toast(c.noun.charAt(0).toUpperCase() + c.noun.slice(1) + (rec ? ' saved.' : ' created.'), 'ok');
          }
        });
      }
      function setStatus(ids, status) {
        var d = db(); d[c.list].forEach(function (x) { if (ids.indexOf(x.id) > -1) x.status = status; }); save(d); t.refresh();
        AGP.toast(ids.length + (status === 'published' ? ' published.' : ' moved to draft.'), 'ok');
      }
      function exportJson() { AGP.download(c.exportFile, JSON.stringify(db(), null, 1), 'application/json'); AGP.audit('export', c.page, c.exportFile, 'JSON'); AGP.toast('Exported. Replace data/' + c.exportFile + ' and rebuild the site.', 'ok'); }

      var t = AGP.table(tEl, {
        emptyState: { icon: c.icon, title: 'No ' + c.plural.toLowerCase() + ' yet', text: 'Create the first one to publish it on the public page.', action: canC ? { label: 'New ' + c.noun, icon: 'plus', run: function () { edit(); } } : null },
        id: 'id', label: c.plural, rows: rows, rowLabel: function (r) { return c.title(r); }, search: c.search, sort: c.sort, pageSize: 10,
        filters: [{ key: 'status', label: 'Status', options: [['published', 'Published'], ['draft', 'Draft']], test: function (r, v) { return r.status === v; } }].concat(c.filters ? c.filters(db) : []),
        columns: c.columns(db).concat([{ key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status); } }]),
        actions: function (r) {
          var a = []; if (c.preview) a.push({ label: 'Preview', icon: c.previewIcon || 'eye', run: function () { c.preview(r); } });
          if (canE) a.push({ label: 'Edit', icon: 'edit', run: function () { edit(r); } });
          if (canP) a.push(r.status === 'published' ? { label: 'Unpublish', icon: 'x', run: function () { setStatus([r.id], 'draft'); } } : { label: 'Publish', icon: 'check', run: function () { setStatus([r.id], 'published'); } });
          if (canD) a.push({ label: 'Delete', icon: 'trash', run: function () {
            AGP.confirm({ title: 'Delete "' + c.title(r) + '"?', text: 'It is removed from the console and, after the next export and rebuild, from the site.', ok: 'Delete', danger: true }).then(function (ok) {
              if (!ok) return; var d = db(); d[c.list] = d[c.list].filter(function (x) { return x.id !== r.id; }); save(d); t.refresh(); AGP.toast(c.noun.charAt(0).toUpperCase() + c.noun.slice(1) + ' deleted.', 'warn');
            });
          } });
          return a;
        },
        bulk: canP ? [{ label: 'Publish', icon: 'check', run: function (l) { setStatus(l.map(function (x) { return x.id; }), 'published'); } }, { label: 'Unpublish', icon: 'x', run: function (l) { setStatus(l.map(function (x) { return x.id; }), 'draft'); } }] : []
      });
      var acts = []; if (canC) acts.push(AGP.btn('New ' + c.noun, { icon: 'plus', on: { click: function () { edit(); } } }));
      acts.push(AGP.btn('Export JSON', { icon: 'download', on: { click: exportJson } }));
      AGP.mount(root, AGP.head({ eyebrow: 'Channels', title: c.title_page, sub: c.sub, actions: acts }), c.note ? h('p', { class: 'a-note info mb-3', role: 'note', text: c.note }) : null, tEl);
    });
  }

  /* ---- Videos ---- */
  register({
    page: 'videos', key: 'videos', seed: 'AGP_VIDEOS', empty: { topics: [], videos: [] }, list: 'videos', noun: 'video', plural: 'Videos', icon: 'video', exportFile: 'videos.json', title_page: 'Videos',
    sub: 'Reports and video formats in French and English. Published videos appear on the Videos page and in the header menu.',
    note: 'Prototype clips are 10-second placeholders (assets/video/). Replace each file with real footage and set its length.',
    id: function (d) { return AGP.uid('vid'); }, blank: function (d) { return { fr: {}, en: {}, topic: d.topics[0] && d.topics[0].id, date: today(), duration: 60, file: 'assets/video/new-video.mp4', poster: '', status: 'draft', article: '' }; },
    title: function (r) { return bi(r, 'title'); }, search: function (r) { return bi(r, 'title') + ' ' + (r.fr && r.fr.title) + ' ' + r.topic; }, sort: { key: 'date', dir: 'desc' },
    filters: function (db) { return [{ key: 'topic', label: 'Topic', options: db().topics.map(function (t) { return [t.id, t.en]; }), test: function (r, v) { return r.topic === v; } }]; },
    columns: function (db) { return [
      { key: 'title', label: 'Video', sort: function (r) { return bi(r, 'title'); }, render: function (r) { return h('div', {}, h('b', { text: bi(r, 'title') }), h('div', { class: 'a-muted', text: r.fr && r.fr.title })); } },
      { key: 'topic', label: 'Topic', sort: true, render: function (r) { var t = db().topics.filter(function (x) { return x.id === r.topic; })[0]; return document.createTextNode(t ? t.en : r.topic); } },
      { key: 'duration', label: 'Length', sort: true, render: function (r) { return document.createTextNode(clock(r.duration)); } },
      { key: 'date', label: 'Date', sort: true, render: function (r) { return AGP.fmtDate(r.date); } }]; },
    fields: function (v, d, canP) { return [
      { name: 'titleFr', label: 'Title (Français)', value: v.fr.title, required: true, full: true }, { name: 'titleEn', label: 'Title (English)', value: v.en.title, required: true, full: true },
      { name: 'descFr', label: 'Description (Français)', type: 'textarea', rows: 3, value: v.fr.desc }, { name: 'descEn', label: 'Description (English)', type: 'textarea', rows: 3, value: v.en.desc },
      { name: 'topic', label: 'Topic', type: 'select', value: v.topic, options: d.topics.map(function (t) { return { v: t.id, l: t.en }; }) }, { name: 'date', label: 'Publish date', type: 'date', value: v.date, required: true },
      { name: 'duration', label: 'Length (seconds)', type: 'number', min: 1, max: 14400, value: v.duration, required: true }, { name: 'status', label: 'Status', type: 'select', value: v.status, options: canP ? STATUS : [STATUS[0]] },
      { name: 'file', label: 'Video file path (MP4, H.264)', value: v.file, required: true, full: true, hint: 'Relative to the site root, e.g. assets/video/vid-009.mp4.', validate: function (x) { return /^assets\/video\/[\w.-]+\.mp4$/i.test(x) ? '' : 'Use assets/video/<name>.mp4'; } },
      { name: 'poster', label: 'Poster image path (optional)', value: v.poster, full: true, hint: 'Defaults to the linked article photo.' },
      { name: 'article', label: 'Linked article id (optional)', value: v.article || '', hint: 'French article id, e.g. 4617.', validate: function (x) { return /^\d*$/.test(x) ? '' : 'Digits only.'; } }]; },
    apply: function (r, v) { r.fr = { title: v.titleFr, desc: v.descFr }; r.en = { title: v.titleEn, desc: v.descEn }; r.topic = v.topic; r.date = v.date; r.duration = +v.duration; r.status = v.status; r.file = v.file; r.poster = v.poster; r.article = v.article; },
    previewIcon: 'video', preview: function (r) { AGP.modal({ title: bi(r, 'title'), size: 'lg', body: h('div', {}, h('p', { class: 'a-muted', text: clock(r.duration) + ' · ' + r.date }), h('video', { controls: true, preload: 'metadata', src: '../' + r.file, poster: r.poster ? '../' + r.poster : null, class: 'a-audio', 'aria-label': 'Preview: ' + bi(r, 'title') })), actions: [{ label: 'Close', cls: 'a-btn-ghost' }] }); }
  });

  /* ---- Magazine ---- */
  register({
    page: 'magazine', key: 'magazine', seed: 'AGP_MAGAZINE', empty: { issues: [] }, list: 'issues', noun: 'issue', plural: 'Magazine issues', icon: 'book', exportFile: 'magazine.json', title_page: 'Magazine',
    sub: 'Monthly issues with a cover story and a table of contents linking to site articles. Published issues appear on the Magazine page.',
    id: function (d) { var n = d.issues.reduce(function (a, x) { return Math.max(a, +String(x.id).replace(/\D/g, '') || 0); }, 0) + 1; return 'mag-' + String(n).padStart(3, '0'); },
    blank: function (d) { var n = d.issues.reduce(function (a, x) { return Math.max(a, +x.number || 0); }, 0) + 1; return { fr: {}, en: {}, number: n, date: today().slice(0, 8) + '01', cover_article: '', articles: [], status: 'draft' }; },
    title: function (r) { return 'No. ' + r.number + ' ' + bi(r, 'title'); }, search: function (r) { return r.number + ' ' + bi(r, 'title') + ' ' + (r.fr && r.fr.title); }, sort: { key: 'number', dir: 'desc' },
    columns: function () { return [
      { key: 'number', label: 'No.', sort: true, render: function (r) { return document.createTextNode('No. ' + r.number); } },
      { key: 'title', label: 'Issue', sort: function (r) { return bi(r, 'title'); }, render: function (r) { return h('div', {}, h('b', { text: bi(r, 'title') }), h('div', { class: 'a-muted', text: r.fr && r.fr.title })); } },
      { key: 'articles', label: 'Contents', render: function (r) { return document.createTextNode((r.articles || []).length + ' articles'); } },
      { key: 'date', label: 'Issue date', sort: true, render: function (r) { return AGP.fmtDate(r.date); } }]; },
    fields: function (v, d, canP) { return [
      { name: 'titleFr', label: 'Title (Français)', value: v.fr.title, required: true, full: true }, { name: 'titleEn', label: 'Title (English)', value: v.en.title, required: true, full: true },
      { name: 'descFr', label: 'Summary (Français)', type: 'textarea', rows: 3, value: v.fr.desc }, { name: 'descEn', label: 'Summary (English)', type: 'textarea', rows: 3, value: v.en.desc },
      { name: 'number', label: 'Issue number', type: 'number', min: 1, max: 9999, value: v.number, required: true }, { name: 'date', label: 'Issue date', type: 'date', value: v.date, required: true },
      { name: 'cover', label: 'Cover article id', value: v.cover_article, required: true, hint: 'French article id whose photo is the cover.', validate: function (x) { return /^\d+$/.test(x) ? '' : 'Digits only.'; } },
      { name: 'status', label: 'Status', type: 'select', value: v.status, options: canP ? STATUS : [STATUS[0]] },
      { name: 'articles', label: 'Contents: article ids', value: (v.articles || []).join(', '), full: true, hint: 'French article ids in reading order, comma separated.', validate: idsOk }]; },
    apply: function (r, v) { r.fr = { title: v.titleFr, desc: v.descFr }; r.en = { title: v.titleEn, desc: v.descEn }; r.number = +v.number; r.date = v.date; r.cover_article = v.cover; r.status = v.status; r.articles = v.articles ? v.articles.split(',').map(function (x) { return x.trim(); }) : []; }
  });

  /* ---- Radios and television share one shape ---- */
  function directory(page, key, seed, list, noun, plural, icon, file, title, sub) {
    register({
      page: page, key: key, seed: seed, empty: (function () { var o = {}; o[list] = []; return o; })(), list: list, noun: noun, plural: plural, icon: icon, exportFile: file, title_page: title, sub: sub,
      note: 'Editorial directory of public broadcasters. Verify each official website before publishing.',
      id: function (d) { var n = d[list].reduce(function (a, x) { return Math.max(a, +String(x.id).replace(/\D/g, '') || 0); }, 0) + 1; return (page === 'radios' ? 'rad-' : 'tv-') + String(n).padStart(3, '0'); },
      blank: function () { return { fr: {}, en: {}, name: '', country: '', language: 'fr', genre: 'news', website: '', status: 'draft' }; },
      title: function (r) { return r.name; }, search: function (r) { return r.name + ' ' + r.country + ' ' + bi(r, 'desc'); }, sort: { key: 'name', dir: 'asc' },
      filters: function () { return [{ key: 'genre', label: 'Genre', options: GENRES.map(function (g) { return [g.v, g.l]; }), test: function (r, v) { return r.genre === v; } }]; },
      columns: function () { return [
        { key: 'name', label: noun.charAt(0).toUpperCase() + noun.slice(1), sort: true, render: function (r) { return h('div', {}, h('b', { text: r.name }), h('div', { class: 'a-muted', text: bi(r, 'desc') })); } },
        { key: 'country', label: 'Country', sort: true }, { key: 'language', label: 'Language', sort: true }, { key: 'genre', label: 'Genre', sort: true }]; },
      fields: function (v, d, canP) { return [
        { name: 'name', label: 'Name', value: v.name, required: true, full: true }, { name: 'country', label: 'Country (ISO code)', value: v.country, required: true, hint: 'Two letters, e.g. FR, NG, ZA.', validate: function (x) { return /^[A-Za-z]{2}$/.test(x) ? '' : 'Two letters.'; } },
        { name: 'language', label: 'Language', type: 'select', value: v.language, options: LANGS }, { name: 'genre', label: 'Genre', type: 'select', value: v.genre, options: GENRES }, { name: 'status', label: 'Status', type: 'select', value: v.status, options: canP ? STATUS : [STATUS[0]] },
        { name: 'website', label: 'Official website (https)', type: 'url', value: v.website, full: true, validate: urlOk },
        { name: 'descFr', label: 'Description (Français)', type: 'textarea', rows: 3, value: v.fr.desc }, { name: 'descEn', label: 'Description (English)', type: 'textarea', rows: 3, value: v.en.desc }]; },
      apply: function (r, v) { r.name = v.name; r.country = v.country.toUpperCase(); r.language = v.language; r.genre = v.genre; r.website = v.website; r.status = v.status; r.fr = { desc: v.descFr }; r.en = { desc: v.descEn }; }
    });
  }
  directory('radios', 'radios', 'AGP_RADIOS', 'stations', 'station', 'Radio stations', 'radio', 'radios.json', 'African radios', 'Directory of radio stations in French and English. Published stations appear on the African Radios page.');
  directory('television', 'tv', 'AGP_TV', 'channels', 'channel', 'TV channels', 'tv', 'tv.json', 'African television', 'Directory of television channels in French and English. Published channels appear on the African Television page.');
})();
