/* Dashboard: KPI cards, inline-SVG chart, quick access, latest posts, review queue. Authors see only their own posts ("my dashboard"). */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, html = AGP.html;
  var NS = 'http://www.w3.org/2000/svg';
  function sv(tag, attrs, kids) {
    var e = document.createElementNS(NS, tag);
    Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function series(total, days, seedKey) {
    var s = 11 + AGP.hue(seedKey), out = [], base = Math.max(40, total / 90);
    for (var i = 0; i < days; i++) {
      s = (s * 16807) % 2147483647;
      var wave = 1 + 0.35 * Math.sin(i / 3.1) + 0.25 * Math.sin(i / 1.3 + 2), trend = 0.75 + i / (days * 2.2);
      out.push(Math.round(base * wave * trend * (0.7 + (s / 2147483647) * 0.6)));
    }
    return out;
  }
  function chart(data, labelStart) {
    var W = 720, H = 260, P = { l: 44, r: 16, t: 16, b: 28 }, max = Math.max.apply(null, data) * 1.1 || 1;
    var iw = W - P.l - P.r, ih = H - P.t - P.b;
    var x = function (i) { return P.l + (data.length < 2 ? 0 : i * iw / (data.length - 1)); }, y = function (v) { return P.t + ih - v / max * ih; };
    var svg = sv('svg', { class: 'a-chart', viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Line chart of daily page views over the last ' + data.length + ' days. Peak ' + Math.max.apply(null, data) + ' views. A data table is available below.' });
    for (var g = 0; g <= 4; g++) {
      var gy = P.t + ih * g / 4, t = sv('text', { x: P.l - 8, y: gy + 4, 'text-anchor': 'end' }); t.textContent = Math.round(max * (1 - g / 4)); svg.appendChild(sv('line', { class: 'grid', x1: P.l, x2: W - P.r, y1: gy, y2: gy })); svg.appendChild(t);
    }
    var line = data.map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
    svg.appendChild(sv('path', { class: 'area', d: line + ' L' + x(data.length - 1) + ' ' + (P.t + ih) + ' L' + P.l + ' ' + (P.t + ih) + ' Z' }));
    svg.appendChild(sv('path', { class: 'line', d: line }));
    var step = Math.ceil(data.length / 6);
    data.forEach(function (v, i) {
      if (i % step === 0 || i === data.length - 1) { var d = new Date(labelStart.getTime() + i * 86400000), t = sv('text', { x: x(i), y: H - 8, 'text-anchor': 'middle' }); t.textContent = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); svg.appendChild(t); }
    });
    return { svg: svg, x: x, y: y };
  }

  AGP.page('index', function (root) {
    var me = AGP.user(), own = me.role === 'author';
    var all = AGP.posts(), posts = own ? all.filter(function (p) { return p.author === me.id; }) : all;
    var cm = AGP.store.get('comments', []), subs = AGP.store.get('subscribers', []);
    var by = function (s) { return posts.filter(function (p) { return p.status === s; }).length; };
    var views = posts.reduce(function (a, p) { return a + (p.views || 0); }, 0);
    var kpis = [
      ['Published', by('published'), 'live on the site', 'file'], ['In review', by('pending'), 'waiting for an editor', 'clock', 'posts.html?status=pending', 'Open review queue'],
      ['Drafts', by('draft'), 'work in progress', 'edit'], ['Scheduled', by('scheduled'), 'queued for release', 'cal'],
      [own ? 'Views (my posts)' : 'Total views', views.toLocaleString('en-GB'), 'all time', 'chart'],
      [own ? 'My comments' : 'Comments to moderate', own ? posts.reduce(function (a, p) { return a + (p.comments || 0); }, 0) : cm.filter(function (c) { return c.status === 'pending'; }).length, own ? 'on my posts' : 'pending approval', 'msg'].concat(own ? [] : ['comments.html?status=pending', 'Moderate now'])
    ];
    if (!own) kpis.push(['Subscribers', subs.filter(function (s) { return s.status === 'active'; }).length, 'active on newsletter', 'mail']);

    var kp = h('section', { class: 'a-kpis', 'aria-label': 'Key figures' }, kpis.map(function (k) {
      var act = k[4] && +k[1] > 0; return h('article', { class: 'a-card a-kpi' + (act ? ' act' : '') }, h('div', { class: 'flex items-center gap-2 a-muted' }, AGP.icon(k[3]), h('span', { class: 'a-eyebrow', text: k[0] })), h('div', { class: 'v', text: String(k[1]) }), h('div', { class: 'd' }, k[2], act ? h('span', {}, ' \u2022 ', h('a', { href: k[4], text: k[5] })) : null));
    }));

    /* chart card */
    var range = 30, box = h('div', { class: 'relative' }), tip = h('div', { class: 'a-badge flash', role: 'status', 'aria-live': 'polite', text: 'Focus or hover a point to read a value' });
    var tableBox = h('details', { class: 'mt-3' }, h('summary', { class: 'a-muted cursor-pointer', text: 'View data as a table' }));
    var rangeSel = h('select', { class: 'a-select', 'aria-label': 'Chart range', id: 'rng' }, [7, 30, 90].map(function (n) { return h('option', { value: n, text: 'Last ' + n + ' days', selected: n === 30 ? true : null }); }));
    rangeSel.style.width = 'auto';
    function drawChart() {
      range = +rangeSel.value;
      var data = series(Math.max(views, 3000), range, me.id), start = new Date(Date.now() - (range - 1) * 86400000), c = chart(data, start);
      data.forEach(function (v, i) {
        var d = new Date(start.getTime() + i * 86400000), lbl = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long' }) + ': ' + v + ' views';
        var dot = sv('circle', { class: 'dot', role: 'img', cx: c.x(i), cy: c.y(v), r: range > 30 ? 3 : 5, tabindex: '0', 'aria-label': lbl });
        var show = function () { tip.textContent = lbl; };
        dot.addEventListener('mouseenter', show); dot.addEventListener('focus', show); c.svg.appendChild(dot);
      });
      AGP.mount(box, c.svg);
      var rows = data.map(function (v, i) { return h('tr', {}, h('td', { text: new Date(start.getTime() + i * 86400000).toLocaleDateString('en-GB') }), h('td', { text: String(v) })); });
      AGP.mount(tableBox, h('summary', { class: 'a-muted cursor-pointer', text: 'View data as a table' }), h('div', { class: 'a-tablewrap', style: null }, h('table', { class: 'a-table' }, h('thead', {}, h('tr', {}, h('th', { scope: 'col', text: 'Date' }), h('th', { scope: 'col', text: 'Views' }))), h('tbody', {}, rows))));
      tableBox.lastChild.style.maxHeight = '240px'; tableBox.lastChild.style.overflow = 'auto';
    }
    rangeSel.addEventListener('change', drawChart); drawChart();
    var chartCard = h('section', { class: 'a-card', 'aria-labelledby': 'chT' },
      h('div', { class: 'a-card-h' }, h('h2', { id: 'chT', text: 'Audience, daily views' }), rangeSel),
      h('div', { class: 'a-card-b' }, tip, box, tableBox));

    /* categories bars */
    var cats = AGP.cats(), counts = {};
    posts.forEach(function (p) { (p.categoryIds || []).forEach(function (id) { counts[id] = (counts[id] || 0) + 1; }); });
    var top = cats.map(function (c) { return { n: AGP.catName(c), v: counts[c.id] || 0 }; }).sort(function (a, b) { return b.v - a.v; }).slice(0, 6);
    var mx = top.length ? top[0].v || 1 : 1;
    var catCard = h('section', { class: 'a-card', 'aria-labelledby': 'cbT' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'cbT', text: 'Top categories' })),
      h('ul', { class: 'a-list a-list-bars' }, top.map(function (t) {
        var bar = h('div', { class: 'a-bar', role: 'img', 'aria-label': t.n + ': ' + t.v + ' posts' }, h('i')); bar.firstChild.style.width = Math.round(t.v / mx * 100) + '%';
        return h('li', { class: 'block' }, h('div', { class: 'flex justify-between mb-1' }, h('span', { text: t.n }), h('b', { text: String(t.v) })), bar);
      })));

    /* quick access */
    var qa = [['post-edit.html', 'New post', 'plus', 'posts', 'create'], ['media.html', 'Upload media', 'upload', 'media', 'create'], ['categories.html', 'Categories', 'folder', 'categories', 'view'], ['comments.html', 'Moderate comments', 'msg', 'comments', 'view'], ['featured.html', 'Homepage slider', 'star', 'featured', 'view'], ['newsletter.html', 'Newsletter', 'mail', 'newsletter', 'view'], ['ads.html', 'Ad zones', 'ad', 'ads', 'view'], ['settings.html', 'Settings', 'gear', 'settings', 'view']]
      .filter(function (q) { return AGP.can(q[3], q[4]); });
    var quick = h('section', { class: 'a-card mb-5', 'aria-labelledby': 'qaT' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'qaT', text: 'Quick access' })),
      h('div', { class: 'a-card-b' }, h('div', { class: 'a-quick' }, qa.map(function (q) { return h('a', { href: q[0] }, AGP.icon(q[2]), h('span', { text: q[1] })); }))));
    quick.style.marginBottom = '20px';

    /* latest posts + review queue */
    var latest = posts.slice().sort(function (a, b) { return b.date.localeCompare(a.date); }).slice(0, 6);
    var postRow = function (p) {
      var th = AGP.thumb(p);
      return h('li', {}, th, h('div', { class: 'flex-1 min-w-0' }, h('a', { class: 'ttl', href: 'post-edit.html?id=' + encodeURIComponent(p.id), text: p.title }), h('span', { class: 'a-muted text-sm', text: AGP.userName(p.author) + ' • ' + AGP.fmtDate(p.date) })), AGP.badge(p.status));
    };
    var latestCard = h('section', { class: 'a-card', 'aria-labelledby': 'ltT' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'ltT', text: own ? 'My latest posts' : 'Latest posts' }), AGP.btn('All posts', { href: 'posts.html' + (own ? '?author=' + me.id : ''), cls: 'a-btn-sm' })), h('ul', { class: 'a-list' }, latest.map(postRow)));
    var queue = h('ul', { class: 'a-list' });
    function drawQueue() {
      var pend = AGP.posts().filter(function (p) { return p.status === 'pending' && (!own || p.author === me.id); }).slice(0, 5);
      AGP.clear(queue);
      if (!pend.length) queue.appendChild(h('li', {}, h('span', { class: 'a-muted', text: 'Nothing waiting for review.' })));
      pend.forEach(function (p) {
        var li = h('li', {}, h('div', { class: 'flex-1 min-w-0' }, h('a', { class: 'ttl', href: 'post-edit.html?id=' + encodeURIComponent(p.id), text: p.title }), h('span', { class: 'a-muted text-sm', text: AGP.userName(p.author) })));
        if (AGP.can('posts', 'publish')) {
          li.appendChild(h('button', { type: 'button', class: 'a-btn a-btn-sm a-btn-primary', 'aria-label': 'Approve and publish: ' + p.title, text: 'Publish', on: { click: function () { var a = AGP.posts(); a.forEach(function (x) { if (x.id === p.id) x.status = 'published'; }); AGP.savePosts(a); AGP.toast('Published: ' + p.title, 'ok'); drawQueue(); } } }));
        }
        queue.appendChild(li);
      });
    }
    drawQueue();
    var queueCard = h('section', { class: 'a-card', 'aria-labelledby': 'qT' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'qT', text: 'Editorial queue' }), AGP.badge('pending', 'Pending review')), queue);

    /* audience snapshot (synthetic demo figures, see admin-stats.js): country, topic and search leaders, linking to the full Analytics page */
    var snap = null;
    if (!own && AGP.can('analytics', 'view') && AGP.stats) {
      var R = AGP.stats.get(30), V = AGP.viz;
      var mini = function (id, title, body) { return h('section', { class: 'a-card', 'aria-labelledby': id }, h('div', { class: 'a-card-h' }, h('h2', { id: id, text: title }), AGP.btn('Analytics', { href: 'analytics.html', cls: 'a-btn-sm a-btn-ghost' })), h('div', { class: 'a-card-b' }, body)); };
      snap = h('div', { class: 'a-an-grid a-an-dash mb-5', 'aria-label': 'Audience snapshot, last 30 days' },
        mini('snC', 'Views by country', V.bars(R.countries.slice(0, 5).map(function (c) { return { label: c.country, value: c.views, note: c.share }; }), { label: 'Views by country' })),
        mini('snT', 'Views by topic', V.bars(R.topics.slice(0, 5).map(function (c) { return { label: c.topic, value: c.views, note: c.share }; }), { label: 'Views by topic' })),
        mini('snS', 'Top searches', h('ul', { class: 'a-list a-list-bars' }, R.searches.slice(0, 5).map(function (q) { return h('li', { class: 'block' }, h('div', { class: 'flex justify-between' }, h('span', { text: q.term }), h('b', { text: q.searches + ' searches' }))); }))));
    }
    AGP.mount(root,
      AGP.head({ eyebrow: new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }), title: own ? 'My dashboard' : 'Good day, ' + me.name.split(' ')[0], sub: own ? 'Your posts, drafts and performance.' : 'Everything happening in the newsroom today.' }),
      kp, quick, snap,
      h('div', { class: 'a-dash-grid' }, h('div', { class: 'a-stack' }, chartCard, latestCard), h('div', { class: 'a-stack' }, catCard, queueCard)));   // two stacks of similar height: no empty space under the chart
  });
})();
