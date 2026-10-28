/* Analytics: KPIs, traffic chart, audience (country, device, language), content (topic, top articles), acquisition (sources, search queries, on-site searches),
   ad clicks, engagement; every report exports to CSV, the whole set to JSON, and the page prints to PDF. Data is synthetic in the prototype (see admin-stats.js). */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, S = AGP.stats, V = AGP.viz;
  var human = function (k) { return k.replace(/([A-Z])/g, ' $1').replace(/^./, function (c) { return c.toUpperCase(); }); };

  AGP.page('analytics', function (root) {
    var days = 30, R, rangeSel = h('select', { class: 'a-select', id: 'anRange', 'aria-label': 'Report period' }, [7, 30, 90].map(function (n) { return h('option', { value: n, text: 'Last ' + n + ' days', selected: n === 30 ? true : null }); }));
    rangeSel.style.width = 'auto';
    var body = h('div', { class: 'a-an' });

    function table(rep, limit) {
      var rows = limit ? rep.rows.slice(0, limit) : rep.rows;
      var thead = h('thead', {}, h('tr', {}, rep.cols.map(function (c) { return h('th', { scope: 'col', text: human(c) }); })));
      var tbody = h('tbody', {}, rows.map(function (r) { return h('tr', {}, rep.cols.map(function (c) { var v = r[c]; return h('td', { class: typeof v === 'number' ? 'text-right' : '', text: typeof v === 'number' ? S.fmt(v) : String(v) }); })); }));
      return h('div', { class: 'a-tablewrap' }, h('table', { class: 'a-table', 'aria-label': rep.title }, thead, tbody));
    }
    function card(rep, kids, opts) {
      opts = opts || {};
      var csv = h('button', { type: 'button', class: 'a-btn a-btn-sm a-btn-ghost a-noprint', 'aria-label': 'Export CSV: ' + rep.title, on: { click: function () { S.exportCSV(rep, days); AGP.toast('CSV exported: ' + rep.title, 'ok'); } } }, AGP.icon('download'), 'CSV');
      var more = opts.noTable ? null : h('details', { class: 'mt-3 a-noprint' }, h('summary', { class: 'a-muted cursor-pointer', text: 'View data as a table' }), table(rep, opts.limit || 0));
      return h('section', { class: 'a-card a-an-card' + (opts.cls ? ' ' + opts.cls : ''), 'aria-label': rep.title }, h('div', { class: 'a-card-h' }, h('h2', { text: rep.title }), csv), h('div', { class: 'a-card-b' }, kids, more));
    }
    function kpi(label, value, deltaTxt, icon, spark) {
      return h('article', { class: 'a-card a-kpi' }, h('div', { class: 'flex items-center gap-2 a-muted' }, AGP.icon(icon), h('span', { class: 'a-eyebrow', text: label })), h('div', { class: 'v', text: value }), h('div', { class: 'd', text: deltaTxt }), spark || null);
    }
    function mmss(s) { return Math.floor(s / 60) + ' min ' + String(s % 60).padStart(2, '0') + ' s'; }

    function draw() {
      days = +rangeSel.value; R = S.get(days);
      var reps = {}; S.reports(R).forEach(function (r) { reps[r.id] = r; });
      var k = R.kpi, s = R.series;
      var kp = h('section', { class: 'a-kpis', 'aria-label': 'Key figures' },
        kpi('Page views', S.fmt(k.views), V.delta(k.views, k.prev.views), 'eye', V.spark(s.views)), kpi('Visitors', S.fmt(k.visitors), V.delta(k.visitors, k.prev.visitors), 'users', V.spark(s.visitors)),
        kpi('Sessions', S.fmt(k.sessions), V.delta(k.sessions, k.prev.sessions), 'globe', V.spark(s.sessions)), kpi('Avg. read time', mmss(k.avgRead), 'per article view', 'clock'),
        kpi('Bounce rate', k.bounce.toFixed(1) + '%', 'single-page sessions', 'x'), kpi('Newsletter sign-ups', S.fmt(k.signups), V.delta(k.signups, k.prev.signups), 'mail', V.spark(s.signups)));

      var tip = h('div', { class: 'a-badge flash', role: 'status', 'aria-live': 'polite', text: 'Focus or hover a point to read a value' });
      var ch = V.line(s.views, s.dates, 'Page views'); s.views.forEach(function (v, i) { ch.point(i, tip); });
      var traffic = card(reps.overview, [tip, ch.svg], { cls: 'a-an-wide', limit: 0 });

      var audience = h('div', { class: 'a-an-grid' },
        card(reps.countries, V.bars(R.countries.slice(0, 8).map(function (c) { return { label: c.country, value: c.views, note: c.share }; }), { label: 'Views by country' }), { limit: 0 }),
        card(reps.devices, V.donut(R.devices.map(function (d) { return { label: d.device, value: d.sessions }; }), { label: 'Sessions by device' })),
        card(reps.languages, V.donut(R.languages.map(function (d) { return { label: d.language, value: d.views }; }), { label: 'Views by language' })));

      var content = h('div', { class: 'a-an-grid' },
        card(reps.topics, V.bars(R.topics.slice(0, 8).map(function (t) { return { label: t.topic, value: t.views, note: t.share }; }), { label: 'Views by topic' })),
        card(reps.sources, V.donut(R.sources.map(function (d) { return { label: d.source, value: d.sessions }; }), { label: 'Traffic sources' })),
        card(reps.posts, table(reps.posts), { noTable: true, cls: 'a-an-wide' }));

      var search = h('div', { class: 'a-an-grid a-an-2' },
        card(reps.queries, table(reps.queries), { noTable: true }), card(reps.searches, table(reps.searches), { noTable: true }));

      var adTotals = R.ads.reduce(function (a, z) { a.i += z.impressions; a.c += z.clicks; return a; }, { i: 0, c: 0 });
      var money = h('div', { class: 'a-an-grid a-an-2' },
        card(reps.ads, [h('p', { class: 'a-muted', text: 'Total: ' + S.fmt(adTotals.c) + ' clicks on ' + S.fmt(adTotals.i) + ' impressions (CTR ' + S.pct(adTotals.c, adTotals.i) + ').' }), table(reps.ads)], { noTable: true }),
        card(reps.engagement, h('div', { class: 'a-an-eng' },
          kpi('Comments', S.fmt(k.comments), V.delta(k.comments, k.prev.comments), 'msg', V.spark(s.comments)), kpi('Shares', S.fmt(k.shares), V.delta(k.shares, k.prev.shares), 'link', V.spark(s.shares)))));

      function sec(t, id, el) { return h('section', { 'aria-labelledby': id, class: 'a-an-sec' }, h('h2', { class: 'a-an-h', id: id, text: t }), el); }
      AGP.mount(body, kp, sec('Traffic', 'anT', traffic), sec('Audience', 'anA', audience), sec('Content', 'anC', content), sec('Search and acquisition', 'anS', search), sec('Ads and engagement', 'anM', money));
    }
    rangeSel.addEventListener('change', draw);
    var acts = [h('label', { class: 'a-vh', for: 'anRange', text: 'Report period' }), rangeSel,
      AGP.btn('Export all (JSON)', { icon: 'download', on: { click: function () { S.exportAll(R, days); AGP.toast('All reports exported.', 'ok'); } } }),
      AGP.btn('Print / PDF', { icon: 'file', on: { click: function () { AGP.audit('export', 'analytics', 'print', 'print / PDF, last ' + days + ' days'); window.print(); } } })];
    AGP.mount(root, AGP.head({ eyebrow: 'Overview', title: 'Analytics', sub: 'Audience, content, search and ad performance. Figures are synthetic demo data in this prototype.', actions: acts }),
      h('p', { class: 'a-note info mb-3', role: 'note', text: 'Demo data: generated in your browser. Production reads Google Analytics 4, Search Console and first-party logs (see Settings > Analytics).' }), body);
    draw();
  });
})();
