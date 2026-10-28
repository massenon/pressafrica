/* Analytics data + visualisation helpers shared by the Analytics page, the dashboard and the Ads table.
   PROTOTYPE: every figure here is SYNTHETIC. It is generated deterministically (seeded PRNG) from the posts, categories and ad zones
   in the console so the screens, filters and exports can be reviewed. Production reads GA4 / Search Console / first-party logs instead. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var NS = 'http://www.w3.org/2000/svg';
  function sv(tag, attrs, kids) { var e = document.createElementNS(NS, tag); Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); }); (kids || []).forEach(function (c) { e.appendChild(c); }); return e; }
  function rng(seed) { var a = seed >>> 0; return function () { a = (a + 0x6D2B79F5) >>> 0; var t = a; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }
  function hash(s) { var x = 2166136261; String(s).split('').forEach(function (c) { x = Math.imul(x ^ c.charCodeAt(0), 16777619); }); return x >>> 0; }
  var fmt = function (n) { return Math.round(n).toLocaleString('en-GB'); };
  var pct = function (n, d) { return (d ? n / d * 100 : 0).toFixed(1) + '%'; };
  var dayKey = function (d) { return d.toISOString().slice(0, 10); };

  var COUNTRIES = [['CI', "Côte d'Ivoire", 14], ['FR', 'France', 13], ['SN', 'Senegal', 9], ['CM', 'Cameroon', 9], ['CD', 'DR Congo', 7], ['BJ', 'Benin', 7], ['MA', 'Morocco', 6], ['NG', 'Nigeria', 5],
    ['GA', 'Gabon', 4], ['ML', 'Mali', 4], ['GH', 'Ghana', 4], ['US', 'United States', 4], ['BE', 'Belgium', 3], ['CA', 'Canada', 3], ['XX', 'Other countries', 8]];
  var DEVICES = [['Mobile', 68], ['Desktop', 27], ['Tablet', 5]];
  var LANGS = [['Français', 74], ['English', 26]];
  var SOURCES = [['Google Search', 41], ['Direct', 22], ['Facebook', 12], ['WhatsApp', 9], ['Newsletter', 5], ['X', 4], ['Other referrals', 7]];
  var QUERIES = [['africa global press', 18, 41], ['can 2025 favoris', 14, 17], ['cedi ghana', 12, 15], ['présidentielle gambie 2026', 11, 12], ['cap-vert inondations', 9, 8], ['économie mozambique agriculture', 8, 9],
    ['netumbo nandi-ndaitwah', 8, 6], ['cedeao 50 ans', 7, 8], ['mortalité maternelle maurice', 6, 5], ['top 10 entreprises rdc', 6, 7], ['somalie quotidien', 5, 4], ['fagace plan stratégique', 4, 3]];
  var SEARCHES = [['can 2025', 22, 2], ['élections', 18, 6], ['cedi', 14, 3], ['santé', 12, 5], ['ouganda', 11, 4], ['économie', 10, 2], ['podcast', 9, 8], ['gambie', 9, 3], ['finance', 8, 4], ['vidéo', 7, 12], ['magazine', 6, 14], ['radio', 5, 30]];

  function weights(list, key) { var t = list.reduce(function (a, x) { return a + x[key]; }, 0); return list.map(function (x) { return x[key] / t; }); }
  function split(total, list, wkey, r) {   /* split a total by weight with seeded jitter; rows sum exactly to the total */
    var w = weights(list, wkey).map(function (x) { return x * (0.9 + r() * 0.2); }), s = w.reduce(function (a, b) { return a + b; }, 0), out = [], used = 0;
    w.forEach(function (x, i) { var v = i === w.length - 1 ? total - used : Math.round(total * x / s); used += v; out.push(v); });
    return out;
  }

  function period(days, endOffset) {
    var out = { views: [], visitors: [], sessions: [], dates: [], comments: [], signups: [], shares: [] };
    var posts = AGP.posts(), base = Math.max(900, posts.reduce(function (a, p) { return a + (p.views || 0); }, 0) / 140);
    for (var i = days - 1; i >= 0; i--) {
      var d = new Date(Date.now() - (i + endOffset) * 86400000), k = dayKey(d), r = rng(hash('d' + k));
      var dow = d.getDay(), wk = dow === 0 || dow === 6 ? 0.82 : 1.05, wave = 1 + 0.18 * Math.sin((d.getTime() / 86400000) / 4.7);
      var v = Math.round(base * wk * wave * (0.85 + r() * 0.3));
      out.dates.push(k); out.views.push(v); out.visitors.push(Math.round(v * (0.58 + r() * 0.06))); out.sessions.push(Math.round(v * (0.76 + r() * 0.05)));
      out.comments.push(Math.round(v / 260 * (0.6 + r()))); out.signups.push(Math.round(v / 210 * (0.5 + r()))); out.shares.push(Math.round(v / 95 * (0.6 + r() * 0.8)));
    }
    return out;
  }
  function sum(a) { return a.reduce(function (x, y) { return x + y; }, 0); }

  AGP.stats = {
    fmt: fmt, pct: pct,
    get: function (days) {
      var cur = period(days, 0), prev = period(days, days), r = rng(hash('rep' + days + AGP.posts().length));
      var views = sum(cur.views), visitors = sum(cur.visitors), sessions = sum(cur.sessions);
      var avg = Math.round(118 + r() * 40), bounce = 38 + r() * 8;
      var cs = split(views, COUNTRIES, 2, r), cvis = split(visitors, COUNTRIES, 2, r);
      var countries = COUNTRIES.map(function (c, i) { return { code: c[0], country: c[1], views: cs[i], visitors: cvis[i], share: pct(cs[i], views) }; }).sort(function (a, b) { return b.views - a.views; });
      var cats = AGP.cats().filter(function (c) { return !c.parent; });
      var published = AGP.posts().filter(function (p) { return p.status === 'published'; });
      var topicList = (cats.length ? cats : [{ name: { fr: 'Presse en ligne' } }]).map(function (c) {
        var n = published.filter(function (p) { return (p.categoryIds || []).indexOf(c.id) > -1; }).length;
        return [AGP.catName(c, 'fr'), Math.max(1, n)];
      });
      var ts = split(views, topicList, 1, r);
      var topics = topicList.map(function (t, i) { return { topic: t[0], views: ts[i], share: pct(ts[i], views), avgRead: Math.round(95 + r() * 90) + ' s' }; }).sort(function (a, b) { return b.views - a.views; });
      var ds = split(sessions, DEVICES, 1, r), devices = DEVICES.map(function (d, i) { return { device: d[0], sessions: ds[i], share: pct(ds[i], sessions) }; });
      var ls = split(views, LANGS, 1, r), languages = LANGS.map(function (d, i) { return { language: d[0], views: ls[i], share: pct(ls[i], views) }; });
      var ss = split(sessions, SOURCES, 1, r), sources = SOURCES.map(function (d, i) { return { source: d[0], sessions: ss[i], share: pct(ss[i], sessions) }; }).sort(function (a, b) { return b.sessions - a.sessions; });
      var scale = days / 28;
      var queries = QUERIES.map(function (q) { var imp = Math.round(q[2] * 90 * scale * (0.85 + r() * 0.3)), clk = Math.round(q[1] * 6 * scale * (0.85 + r() * 0.3)); return { query: q[0], clicks: clk, impressions: imp, ctr: pct(clk, imp), position: (2 + r() * 7).toFixed(1) }; }).sort(function (a, b) { return b.clicks - a.clicks; });
      var searches = SEARCHES.map(function (q) { var n = Math.round(q[1] * 4 * scale * (0.85 + r() * 0.3)); return { term: q[0], searches: n, noResults: Math.round(n * q[2] / 100) }; }).sort(function (a, b) { return b.searches - a.searches; });
      var tops = published.slice().sort(function (a, b) { return (b.views || 0) - (a.views || 0); }).slice(0, 10);
      var tw = tops.map(function (p, i) { return 1 / (i + 1.4); }), tsum = tw.reduce(function (a, b) { return a + b; }, 0);
      var topPosts = tops.map(function (p, i) { return { id: p.id, title: p.title, views: Math.round(views * 0.42 * tw[i] / tsum), avgRead: Math.round(80 + r() * 120) + ' s', comments: p.comments || Math.round(r() * 9) }; });
      var zones = AGP.store.get('ads', []).map(function (z) {
        var on = z.active && z.desktop && z.desktop.enabled !== false, imp = on ? Math.round(views * (0.5 + r() * 0.9)) : 0, clk = on ? Math.round(imp * (0.003 + r() * 0.009)) : 0;
        return { id: z.id, zone: z.name, key: z.key, status: z.active ? 'active' : 'inactive', impressions: imp, clicks: clk, ctr: imp ? pct(clk, imp) : '0.0%' };
      });
      return {
        days: days, series: cur, prevSeries: prev,
        kpi: { views: views, visitors: visitors, sessions: sessions, avgRead: avg, bounce: bounce, signups: sum(cur.signups), comments: sum(cur.comments), shares: sum(cur.shares),
          prev: { views: sum(prev.views), visitors: sum(prev.visitors), sessions: sum(prev.sessions), signups: sum(prev.signups), comments: sum(prev.comments), shares: sum(prev.shares) } },
        countries: countries, topics: topics, devices: devices, languages: languages, sources: sources, queries: queries, searches: searches, topPosts: topPosts, ads: zones
      };
    },
    /* report definitions: one per export (CSV) and per table on the Analytics page */
    reports: function (R) {
      var s = R.series;
      return [
        { id: 'overview', title: 'Daily traffic', cols: ['date', 'views', 'visitors', 'sessions'], rows: s.dates.map(function (d, i) { return { date: d, views: s.views[i], visitors: s.visitors[i], sessions: s.sessions[i] }; }) },
        { id: 'countries', title: 'Views by country', cols: ['country', 'code', 'views', 'visitors', 'share'], rows: R.countries },
        { id: 'topics', title: 'Views by topic', cols: ['topic', 'views', 'share', 'avgRead'], rows: R.topics },
        { id: 'languages', title: 'Views by language', cols: ['language', 'views', 'share'], rows: R.languages },
        { id: 'devices', title: 'Sessions by device', cols: ['device', 'sessions', 'share'], rows: R.devices },
        { id: 'sources', title: 'Traffic sources', cols: ['source', 'sessions', 'share'], rows: R.sources },
        { id: 'queries', title: 'Search engine queries (Search Console)', cols: ['query', 'clicks', 'impressions', 'ctr', 'position'], rows: R.queries },
        { id: 'searches', title: 'On-site searches', cols: ['term', 'searches', 'noResults'], rows: R.searches },
        { id: 'posts', title: 'Top articles', cols: ['title', 'views', 'avgRead', 'comments'], rows: R.topPosts },
        { id: 'ads', title: 'Ad zones: impressions and clicks', cols: ['zone', 'key', 'status', 'impressions', 'clicks', 'ctr'], rows: R.ads },
        { id: 'engagement', title: 'Engagement', cols: ['date', 'comments', 'newsletterSignups', 'shares'], rows: s.dates.map(function (d, i) { return { date: d, comments: s.comments[i], newsletterSignups: s.signups[i], shares: s.shares[i] }; }) }
      ];
    }
  };

  /* ---------- visualisation widgets ---------- */
  var PAL = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)', 'var(--chart-6)'];
  AGP.viz = {
    palette: PAL,
    /* area/line chart with focusable points; labels = ISO dates */
    line: function (data, dates, label) {
      var W = 720, H = 240, P = { l: 44, r: 16, t: 14, b: 26 }, max = Math.max.apply(null, data) * 1.1 || 1, iw = W - P.l - P.r, ih = H - P.t - P.b;
      var x = function (i) { return P.l + (data.length < 2 ? 0 : i * iw / (data.length - 1)); }, y = function (v) { return P.t + ih - v / max * ih; };
      var svg = sv('svg', { class: 'a-chart', viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': label + ': ' + data.length + ' days, peak ' + fmt(Math.max.apply(null, data)) + '.' });
      for (var g = 0; g <= 4; g++) { var gy = P.t + ih * g / 4, t = sv('text', { x: P.l - 8, y: gy + 4, 'text-anchor': 'end' }); t.textContent = fmt(max * (1 - g / 4)); svg.appendChild(sv('line', { class: 'grid', x1: P.l, x2: W - P.r, y1: gy, y2: gy })); svg.appendChild(t); }
      var d = data.map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
      svg.appendChild(sv('path', { class: 'area', d: d + ' L' + x(data.length - 1) + ' ' + (P.t + ih) + ' L' + P.l + ' ' + (P.t + ih) + ' Z' })); svg.appendChild(sv('path', { class: 'line', d: d }));
      var step = Math.ceil(data.length / 6);
      data.forEach(function (v, i) { if (i % step === 0 || i === data.length - 1) { var t = sv('text', { x: x(i), y: H - 6, 'text-anchor': 'middle' }); t.textContent = new Date(dates[i]).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); svg.appendChild(t); } });
      return { svg: svg, point: function (i, tip) { var dot = sv('circle', { class: 'dot', role: 'img', cx: x(i), cy: y(data[i]), r: data.length > 30 ? 3 : 5, tabindex: '0', 'aria-label': dates[i] + ': ' + fmt(data[i]) }); var show = function () { tip.textContent = dates[i] + ': ' + fmt(data[i]) + ' ' + label.toLowerCase(); }; dot.addEventListener('mouseenter', show); dot.addEventListener('focus', show); svg.appendChild(dot); } };
    },
    /* tiny trend line for stat cards */
    spark: function (data) {
      var W = 120, H = 32, max = Math.max.apply(null, data) || 1, min = Math.min.apply(null, data), rg = max - min || 1;
      var d = data.map(function (v, i) { return (i ? 'L' : 'M') + (i * W / (data.length - 1)).toFixed(1) + ' ' + (H - 3 - (v - min) / rg * (H - 6)).toFixed(1); }).join(' ');
      return sv('svg', { class: 'a-spark', viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true', focusable: 'false' }, [sv('path', { d: d })]);
    },
    /* horizontal bars: rows = [{label, value, note?}] */
    bars: function (rows, o) {
      o = o || {}; var mx = Math.max.apply(null, rows.map(function (r) { return r.value; })) || 1;
      return h('ul', { class: 'a-list a-list-bars a-viz-bars', 'aria-label': o.label || 'Bar chart' }, rows.map(function (r) {
        var bar = h('div', { class: 'a-bar', role: 'img', 'aria-label': r.label + ': ' + fmt(r.value) }, h('i')); bar.firstChild.style.width = Math.max(2, Math.round(r.value / mx * 100)) + '%';
        return h('li', { class: 'block' }, h('div', { class: 'flex justify-between mb-1' }, h('span', { text: r.label }), h('b', { text: fmt(r.value) + (r.note ? ' · ' + r.note : '') })), bar);
      }));
    },
    /* donut + legend: rows = [{label, value}] */
    donut: function (rows, o) {
      o = o || {}; var total = rows.reduce(function (a, r) { return a + r.value; }, 0) || 1, R = 15.9155, off = 25, circles = [];
      rows.forEach(function (r, i) { var p = r.value / total * 100; circles.push(sv('circle', { cx: 21, cy: 21, r: R, fill: 'none', stroke: PAL[i % PAL.length], 'stroke-width': 6, 'stroke-dasharray': p.toFixed(2) + ' ' + (100 - p).toFixed(2), 'stroke-dashoffset': off.toFixed(2) })); off -= p; });
      var svg = sv('svg', { class: 'a-donut', viewBox: '0 0 42 42', role: 'img', 'aria-label': (o.label || 'Donut chart') + ': ' + rows.map(function (r) { return r.label + ' ' + pct(r.value, total); }).join(', ') }, circles);
      var legend = h('ul', { class: 'a-legend' }, rows.map(function (r, i) { var sw = h('i', { 'aria-hidden': 'true' }); sw.style.background = PAL[i % PAL.length]; return h('li', {}, sw, h('span', { text: r.label }), h('b', { text: pct(r.value, total) })); }));
      return h('div', { class: 'a-donutwrap' }, svg, legend);
    },
    delta: function (cur, prev) { if (!prev) return ''; var d = (cur - prev) / prev * 100; return (d >= 0 ? '+' : '') + d.toFixed(1) + '% vs previous period'; }
  };

  /* ---------- exports ---------- */
  AGP.stats.csv = function (rep) { return AGP.toCSV(rep.rows, rep.cols); };
  AGP.stats.exportCSV = function (rep, days) { AGP.download('agp-' + rep.id + '-last-' + days + 'd.csv', '﻿' + AGP.stats.csv(rep), 'text/csv;charset=utf-8'); AGP.audit('export', 'analytics', rep.id, 'CSV, last ' + days + ' days'); };
  AGP.stats.exportAll = function (R, days) {
    var out = { generated: new Date().toISOString(), synthetic: true, rangeDays: days, kpi: R.kpi, reports: {} };
    AGP.stats.reports(R).forEach(function (rep) { out.reports[rep.id] = rep.rows; });
    AGP.download('agp-analytics-last-' + days + 'd.json', JSON.stringify(out, null, 1), 'application/json'); AGP.audit('export', 'analytics', 'all', 'JSON, last ' + days + ' days');
  };
})();
