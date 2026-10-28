/* Podcasts: shows + episodes (bilingual FR/EN), audio preview, publish workflow, JSON export for the public site.
   Data lives in localStorage ('podcasts'), seeded from admin/podcasts-seed.js (a copy of data/podcasts.json).
   The public pages are generated from data/podcasts.json: use "Export JSON" and replace that file, then rebuild. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;

  /* ---- data access ---- */
  function db() {
    var d = AGP.store.get('podcasts', null);
    if (!d) { d = JSON.parse(JSON.stringify(window.AGP_PODCASTS || { shows: [], episodes: [] })); AGP.store.set('podcasts', d); }
    return d;
  }
  function save(d) { AGP.store.set('podcasts', d); }
  function showName(d, id) { var s = d.shows.filter(function (x) { return x.id === id; })[0]; return s ? s.en.title : id; }
  /* audio paths stored relative to the site root; the admin lives one level down */
  function src(p) { p = p.replace('{lang}', 'en'); return /^(https?:|data:|blob:)/.test(p) ? p : '../' + p; }
  function mmss(sec) { return Math.round(sec / 60) + ' min'; }

  AGP.page('podcasts', function (root) {
    var canC = AGP.can('podcasts', 'create'), canE = AGP.can('podcasts', 'edit'), canD = AGP.can('podcasts', 'delete'), canP = AGP.can('podcasts', 'publish');
    var tEl = h('section', { 'aria-label': 'Episodes' }), shEl = h('section', { class: 'a-card mt-4', 'aria-labelledby': 'shHd' });

    function episodeFields(ep) {
      var d = db(); ep = ep || { fr: {}, en: {}, show: d.shows[0] && d.shows[0].id, date: new Date().toISOString().slice(0, 10), duration: 1200, audio: 'assets/audio/new-episode-{lang}.mp3', status: 'draft' };
      return [
        { name: 'titleFr', label: 'Title (Français)', value: ep.fr.title, required: true, full: true },
        { name: 'titleEn', label: 'Title (English)', value: ep.en.title, required: true, full: true },
        { name: 'descFr', label: 'Description (Français)', type: 'textarea', rows: 3, value: ep.fr.desc },
        { name: 'descEn', label: 'Description (English)', type: 'textarea', rows: 3, value: ep.en.desc },
        { name: 'show', label: 'Show', type: 'select', value: ep.show, options: d.shows.map(function (s) { return { v: s.id, l: s.en.title }; }) },
        { name: 'date', label: 'Publish date', type: 'date', value: ep.date, required: true },
        { name: 'minutes', label: 'Duration (minutes)', type: 'number', min: 1, max: 600, value: Math.round(ep.duration / 60), required: true },
        { name: 'status', label: 'Status', type: 'select', value: ep.status, options: canP ? [{ v: 'draft', l: 'Draft' }, { v: 'published', l: 'Published' }] : [{ v: 'draft', l: 'Draft' }] },
        { name: 'audio', label: 'Audio file path or URL', value: ep.audio, required: true, full: true, hint: 'Relative to the site root (assets/audio/...) or https://. Use {lang} for one file per language (name-fr.mp3 / name-en.mp3).' },
        { name: 'article', label: 'Linked article id (optional)', value: ep.article || '', hint: 'French article id, e.g. 4617. Adds a "Read the related article" link.' }
      ];
    }
    function apply(ep, v) {
      ep.fr = { title: v.titleFr, desc: v.descFr }; ep.en = { title: v.titleEn, desc: v.descEn };
      ep.show = v.show; ep.date = v.date; ep.duration = v.minutes * 60; ep.status = v.status; ep.audio = v.audio; ep.article = v.article || '';
      return ep;
    }
    function editEpisode(ep) {
      AGP.formModal({
        title: ep ? 'Edit episode' : 'New episode', size: 'lg', submit: ep ? 'Save' : 'Create', fields: episodeFields(ep),
        onSubmit: function (v) {
          var d = db();
          if (ep) { var cur = d.episodes.filter(function (x) { return x.id === ep.id; })[0]; apply(cur, v); }
          else { d.episodes.push(apply({ id: AGP.uid('ep') }, v)); }
          save(d); t.refresh(); AGP.toast(ep ? 'Episode saved.' : 'Episode created.', 'ok');
        }
      });
    }
    function preview(ep) {
      var au = h('audio', { controls: true, preload: 'metadata', src: src(ep.audio), class: 'a-audio', 'aria-label': 'Preview: ' + ep.en.title });
      AGP.modal({ title: ep.en.title, body: h('div', {}, h('p', { class: 'a-muted', text: showName(db(), ep.show) + ' · ' + mmss(ep.duration) }), au), actions: [{ label: 'Close', cls: 'a-btn-ghost' }] });
    }
    function setStatus(ids, status) {
      var d = db(); d.episodes.forEach(function (e) { if (ids.indexOf(e.id) > -1) e.status = status; }); save(d); t.refresh();
      AGP.toast(ids.length + (status === 'published' ? ' published.' : ' moved to draft.'), 'ok');
    }
    function exportJson() { AGP.download('podcasts.json', JSON.stringify(db(), null, 1), 'application/json'); AGP.toast('Exported. Replace data/podcasts.json and rebuild the site.', 'ok'); }

    var t = AGP.table(tEl, {
      emptyState: { icon: 'mic', title: 'No episodes yet', text: 'Create your first episode to publish it on the Podcasts page.', action: canC ? { label: 'New episode', icon: 'plus', run: function () { editEpisode(); } } : null },
      id: 'id', label: 'Episodes', rows: function () { return db().episodes; }, rowLabel: function (r) { return r.en.title; },
      search: function (r) { return r.en.title + ' ' + r.fr.title; }, sort: { key: 'date', dir: 'desc' }, pageSize: 10,
      filters: [{ key: 'status', label: 'Status', options: [['published', 'Published'], ['draft', 'Draft']], test: function (r, v) { return r.status === v; } }],
      columns: [
        { key: 'title', label: 'Episode', sort: true, render: function (r) { return h('div', {}, h('b', { text: r.en.title }), h('div', { class: 'a-muted', text: r.fr.title })); } },
        { key: 'show', label: 'Show', sort: true, render: function (r) { return document.createTextNode(showName(db(), r.show)); } },
        { key: 'duration', label: 'Length', sort: true, render: function (r) { return document.createTextNode(mmss(r.duration)); } },
        { key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status); } },
        { key: 'date', label: 'Date', sort: true, render: function (r) { return AGP.fmtDate(r.date); } }
      ],
      actions: function (r) {
        var a = [{ label: 'Listen', icon: 'mic', run: function () { preview(r); } }];
        if (canE) a.push({ label: 'Edit', icon: 'edit', run: function () { editEpisode(r); } });
        if (canP) a.push(r.status === 'published' ? { label: 'Unpublish', icon: 'x', run: function () { setStatus([r.id], 'draft'); } } : { label: 'Publish', icon: 'check', run: function () { setStatus([r.id], 'published'); } });
        if (canD) a.push({ label: 'Delete', icon: 'trash', run: function () {
          AGP.confirm({ title: 'Delete "' + r.en.title + '"?', text: 'The episode is removed from the console.', ok: 'Delete', danger: true }).then(function (ok) {
            if (!ok) return; var d = db(); d.episodes = d.episodes.filter(function (x) { return x.id !== r.id; }); save(d); t.refresh(); AGP.toast('Episode deleted.', 'warn');
          });
        } });
        return a;
      },
      bulk: (canP ? [{ label: 'Publish', icon: 'check', run: function (l) { setStatus(l.map(function (x) { return x.id; }), 'published'); } }, { label: 'Unpublish', icon: 'x', run: function (l) { setStatus(l.map(function (x) { return x.id; }), 'draft'); } }] : [])
    });

    /* ---- shows ---- */
    function renderShows() {
      var d = db();
      shEl.textContent = '';
      shEl.appendChild(h('div', { class: 'a-card-h' }, h('h2', { id: 'shHd', text: 'Shows' }),
        canC ? AGP.btn('Add show', { icon: 'plus', cls: 'a-btn-sm', on: { click: function () { editShow(); } } }) : null));
      shEl.appendChild(h('div', { class: 'a-card-b' }, h('ul', { class: 'a-list' }, d.shows.map(function (s) {
        var n = d.episodes.filter(function (e) { return e.show === s.id; }).length;
        return h('li', { class: 'flex' }, h('div', {}, h('b', { text: s.en.title + ' / ' + s.fr.title }), h('div', { class: 'a-muted', text: n + ' episode' + (n === 1 ? '' : 's') + ' · ' + s.en.desc })),
          canE ? AGP.btn('Edit', { icon: 'edit', cls: 'a-btn-sm a-btn-ghost', on: { click: function () { editShow(s); } } }) : null);
      }))));
    }
    function editShow(s) {
      AGP.formModal({
        title: s ? 'Edit show' : 'New show', submit: s ? 'Save' : 'Create',
        fields: [{ name: 'tFr', label: 'Name (Français)', value: s && s.fr.title, required: true }, { name: 'tEn', label: 'Name (English)', value: s && s.en.title, required: true },
          { name: 'dFr', label: 'Description (Français)', type: 'textarea', rows: 3, value: s && s.fr.desc }, { name: 'dEn', label: 'Description (English)', type: 'textarea', rows: 3, value: s && s.en.desc }],
        onSubmit: function (v) {
          var d = db(), o = s ? d.shows.filter(function (x) { return x.id === s.id; })[0] : { id: v.tEn.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || AGP.uid('show') };
          o.fr = { title: v.tFr, desc: v.dFr }; o.en = { title: v.tEn, desc: v.dEn }; if (!s) d.shows.push(o);
          save(d); renderShows(); t.refresh(); AGP.toast('Show saved.', 'ok');
        }
      });
    }
    renderShows();

    var acts = [];
    if (canC) acts.push(AGP.btn('New episode', { icon: 'plus', on: { click: function () { editEpisode(); } } }));
    acts.push(AGP.btn('Export JSON', { icon: 'download', on: { click: exportJson } }));
    AGP.mount(root, AGP.head({ eyebrow: 'Newsroom', title: 'Podcasts', sub: 'Shows and episodes in French and English. Published episodes appear on the Podcasts page of the site.', actions: acts }), tEl, shEl);
  });
})();
