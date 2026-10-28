/* Back-office shared library: DataTable, sanitizer, CSV, file helpers, media upload/picker, post picker. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $ = AGP.$, $$ = AGP.$$;

  /* ---------- status badge ---------- */
  var ST = { published: 'ok', draft: '', pending: 'warn', scheduled: 'info', trash: 'bad', approved: 'ok', rejected: 'bad', spam: 'bad', active: 'ok', unsubscribed: '', inactive: '' };
  AGP.badge = function (status, label) { return h('span', { class: 'a-badge ' + (ST[status] || ''), text: label || (status.charAt(0).toUpperCase() + status.slice(1)) }); };

  /* ---------- HTML sanitizer (allow-list; used for WYSIWYG output and seeded content) ---------- */
  var ALLOW = { P: [], BR: [], STRONG: [], B: [], EM: [], I: [], U: [], H2: [], H3: [], H4: [], UL: [], OL: [], LI: [], BLOCKQUOTE: [], HR: [], CODE: [], PRE: [], FIGURE: [], FIGCAPTION: [], A: ['href', 'title'], IMG: ['src', 'alt', 'title'], TABLE: [], THEAD: [], TBODY: [], TR: [], TH: [], TD: [] };
  var DROP = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, FORM: 1, LINK: 1, META: 1, SVG: 1, MATH: 1, TEMPLATE: 1 };
  AGP.sanitize = function (str) {
    var doc = new DOMParser().parseFromString('<body>' + String(str || '') + '</body>', 'text/html');
    (function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (c) {
        if (c.nodeType === 3) return;
        if (c.nodeType !== 1) { node.removeChild(c); return; }
        var tag = c.tagName;
        if (DROP[tag]) { node.removeChild(c); return; }
        walk(c);
        if (!ALLOW[tag]) { while (c.firstChild) node.insertBefore(c.firstChild, c); node.removeChild(c); return; }
        Array.prototype.slice.call(c.attributes).forEach(function (a) {
          var n = a.name.toLowerCase(), v = a.value;
          if (ALLOW[tag].indexOf(n) < 0) c.removeAttribute(a.name);
          else if (n === 'href' && !/^(https?:|mailto:|#|\/|\.\.?\/)/i.test(v.trim())) c.removeAttribute('href');
          else if (n === 'src' && !/^(data:image\/(png|jpe?g|gif|webp|avif);|blob:|\.\.?\/|assets\/)/i.test(v.trim())) c.removeAttribute('src');
        });
        if (tag === 'IMG' && !c.getAttribute('src')) node.removeChild(c);
        else if (tag === 'A' && c.getAttribute('href') && /^https?:/i.test(c.getAttribute('href'))) c.setAttribute('rel', 'nofollow noopener noreferrer');
      });
    })(doc.body);
    return doc.body.innerHTML;
  };

  /* ---------- files, CSV ---------- */
  AGP.readFile = function (file, as) {
    return new Promise(function (res, rej) {
      var r = new FileReader();
      r.onload = function () { res(r.result); }; r.onerror = function () { rej(r.error || new Error('Could not read file')); };
      if (as === 'text') r.readAsText(file); else r.readAsDataURL(file);
    });
  };
  AGP.download = function (name, text, mime) {
    var url = URL.createObjectURL(new Blob([text], { type: mime || 'text/plain' }));
    var a = h('a', { href: url, download: name }); document.body.appendChild(a); a.click();
    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 500);
  };
  AGP.parseCSV = function (t) {
    var rows = [], row = [], cur = '', q = false, i, c;
    t = String(t).replace(/^﻿/, '');
    for (i = 0; i < t.length; i++) {
      c = t[i];
      if (q) { if (c === '"') { if (t[i + 1] === '"') { cur += '"'; i++; } else q = false; } else cur += c; }
      else if (c === '"') q = true;
      else if (c === ',' || c === ';') { row.push(cur); cur = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(cur); cur = ''; if (row.some(function (x) { return x !== ''; })) rows.push(row); row = []; }
      else cur += c;
    }
    row.push(cur); if (row.some(function (x) { return x !== ''; })) rows.push(row);
    return rows;
  };
  AGP.toCSV = function (rows, cols) {
    function cell(v) { v = String(v == null ? '' : v); if (/^[=+\-@]/.test(v)) v = "'" + v; return /[",\n;]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
    return [cols.join(',')].concat(rows.map(function (r) { return cols.map(function (c) { return cell(r[c]); }).join(','); })).join('\n');
  };

  /* designed empty state: icon, one-line explanation, primary action */
  AGP.emptyState = function (o) {
    var b = o.action ? (o.action.href ? AGP.btn(o.action.label, { href: o.action.href, cls: 'a-btn-primary', icon: o.action.icon }) : AGP.btn(o.action.label, { cls: 'a-btn-primary', icon: o.action.icon, on: { click: o.action.run } })) : null;
    return h('div', { class: 'a-empty' }, AGP.icon(o.icon || 'info'), h('h3', { text: o.title }), h('p', { text: o.text || '' }), b);
  };
  /* ---------- DataTable ---------- */
  AGP.table = function (root, cfg) {
    var st = { q: (cfg.initial && cfg.initial.q) || '', f: Object.assign({}, cfg.initial && cfg.initial.f), sort: cfg.sort || null, page: 1, size: cfg.pageSize || 10, sel: {} };
    var uid = AGP.uid('t');
    var status = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
    var search = h('input', { class: 'a-input', type: 'search', id: uid + 's', value: st.q, placeholder: cfg.searchPlaceholder || 'Search…', autocomplete: 'off' });
    var tools = h('div', { class: 'a-tools' }, h('div', { class: 'a-field', style: null }, h('label', { class: 'a-label', for: uid + 's', text: 'Search' }), search));
    tools.firstChild.style.minWidth = '220px'; tools.firstChild.style.flex = '1';
    var fsel = {};
    (cfg.filters || []).forEach(function (f) {
      var s = h('select', { class: 'a-select', id: uid + f.key }, h('option', { value: '', text: 'All' }), f.options.map(function (o) { return h('option', { value: o[0], text: o[1], selected: st.f[f.key] === o[0] ? true : null }); }));
      s.addEventListener('change', function () { st.f[f.key] = s.value; st.page = 1; render(); });
      fsel[f.key] = s;
      tools.appendChild(h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: uid + f.key, text: f.label }), s));
    });
    if (cfg.toolsExtra) tools.appendChild(cfg.toolsExtra);
    var bulkBar = h('div', { class: 'a-bulk', hidden: true });
    var thead = h('thead'), tbody = h('tbody'), pager = h('div', { class: 'a-pager' });
    var table = h('table', { class: 'a-table', 'aria-label': cfg.label || 'Data table' }, thead, tbody);
    root.classList.add('a-card');
    AGP.mount(root, tools, bulkBar, h('div', { class: 'a-tablewrap' }, table), pager, status);
    search.addEventListener('input', AGP.debounce(function () { st.q = search.value; st.page = 1; render(); }, 150));

    var allCb = h('input', { type: 'checkbox', 'aria-label': 'Select all rows on this page' });
    allCb.addEventListener('change', function () { pageRows().forEach(function (r) { if (allCb.checked) st.sel[r[cfg.id]] = 1; else delete st.sel[r[cfg.id]]; }); render(); });
    function filtered() {
      var q = st.q.trim().toLowerCase(), rows = cfg.rows();
      rows = rows.filter(function (r) {
        if (q && cfg.search(r).toLowerCase().indexOf(q) < 0) return false;
        return (cfg.filters || []).every(function (f) { var v = st.f[f.key]; return !v || f.test(r, v); });
      });
      if (st.sort) {
        var col = cfg.columns.filter(function (c) { return c.key === st.sort.key; })[0];
        var get = col && typeof col.sort === 'function' ? col.sort : function (r) { return r[st.sort.key]; };
        rows = rows.slice().sort(function (a, b) {
          var x = get(a), y = get(b); if (x == null) x = ''; if (y == null) y = '';
          var c = typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' });
          return st.sort.dir === 'asc' ? c : -c;
        });
      }
      return rows;
    }
    function pageRows() { var all = filtered(), s = (st.page - 1) * st.size; return all.slice(s, s + st.size); }
    function selectedRows() { return cfg.rows().filter(function (r) { return st.sel[r[cfg.id]]; }); }
    function render() {
      var all = filtered(), pages = Math.max(1, Math.ceil(all.length / st.size));
      if (st.page > pages) st.page = pages;
      var start = (st.page - 1) * st.size, rows = all.slice(start, start + st.size);
      /* head */
      var tr = h('tr');
      if (cfg.bulk) tr.appendChild(h('th', { scope: 'col', class: 'w-10' }, allCb));
      cfg.columns.forEach(function (c) {
        var th = h('th', { scope: 'col', class: c.cls || '' });
        if (c.sort) {
          var active = st.sort && st.sort.key === c.key;
          th.setAttribute('aria-sort', active ? (st.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
          th.appendChild(h('button', { type: 'button', on: { click: function () { st.sort = { key: c.key, dir: active && st.sort.dir === 'asc' ? 'desc' : 'asc' }; render(); const b = $('th[aria-sort] button', thead); } } }, c.label, h('span', { 'aria-hidden': 'true', text: active ? (st.sort.dir === 'asc' ? ' ▲' : ' ▼') : ' ↕' })));
        } else th.textContent = c.label;
        tr.appendChild(th);
      });
      if (cfg.actions) tr.appendChild(h('th', { scope: 'col', class: 'text-right' }, h('span', { class: 'a-vh', text: 'Actions' })));
      AGP.mount(thead, tr);
      /* body */
      AGP.clear(tbody);
      if (!rows.length) tbody.appendChild(h('tr', {}, h('td', { colspan: cfg.columns.length + (cfg.bulk ? 1 : 0) + (cfg.actions ? 1 : 0) }, AGP.emptyState(cfg.rows().length ? { icon: 'search', title: 'No ' + (cfg.label || 'items').toLowerCase() + ' match', text: 'Nothing fits this search or filter. Try other keywords.', action: { label: 'Clear search and filters', run: function () { st.q = ''; search.value = ''; Object.keys(fsel).forEach(function (k) { fsel[k].value = ''; }); st.f = {}; st.page = 1; render(); } } } : (cfg.emptyState || { icon: 'file', title: 'Nothing here yet', text: cfg.empty || 'Items will appear here.' })))));
      rows.forEach(function (r) {
        var id = r[cfg.id], rt = h('tr', { 'aria-selected': st.sel[id] ? 'true' : 'false' });
        if (cfg.bulk) {
          var cb = h('input', { type: 'checkbox', checked: !!st.sel[id], 'aria-label': 'Select ' + (cfg.rowLabel ? cfg.rowLabel(r) : id) });
          cb.addEventListener('change', function () { if (cb.checked) st.sel[id] = 1; else delete st.sel[id]; rt.setAttribute('aria-selected', String(cb.checked)); updBulk(); });
          rt.appendChild(h('td', {}, cb));
        }
        cfg.columns.forEach(function (c) { var td = h('td', { class: c.cls || '' }); var v = c.render ? c.render(r) : r[c.key]; if (v && v.nodeType) td.appendChild(v); else td.textContent = v == null ? '' : v; rt.appendChild(td); });
        if (cfg.actions) {
          var td = h('td', { class: 'text-right whitespace-nowrap' });
          cfg.actions(r).forEach(function (a) { td.appendChild(h('button', { type: 'button', class: 'a-iconbtn sm', title: a.label, 'aria-label': a.label + ': ' + (cfg.rowLabel ? cfg.rowLabel(r) : id), on: { click: function () { a.run(r); } } }, AGP.icon(a.icon))); });
          rt.appendChild(td);
        }
        tbody.appendChild(rt);
      });
      /* pager */
      var from = all.length ? start + 1 : 0, to = start + rows.length;
      var sizeSel = h('select', { class: 'a-select', 'aria-label': 'Rows per page' }, [10, 25, 50].map(function (n) { return h('option', { value: n, text: n + ' / page', selected: n === st.size ? true : null }); }));
      sizeSel.style.width = 'auto'; sizeSel.addEventListener('change', function () { st.size = +sizeSel.value; st.page = 1; render(); });
      var pg = h('nav', { class: 'pg', 'aria-label': 'Pagination' });
      function pb(label, p, aria, dis, cur) { pg.appendChild(h('button', { type: 'button', class: 'a-btn a-btn-sm', disabled: dis, 'aria-label': aria, 'aria-current': cur ? 'page' : null, text: label, on: { click: function () { st.page = p; render(); var f = $('tbody input, tbody button', tbody); } } })); }
      pb('‹', st.page - 1, 'Previous page', st.page <= 1);
      var win = [];
      for (var p = 1; p <= pages; p++) if (p === 1 || p === pages || Math.abs(p - st.page) <= 1) win.push(p);
      var last = 0;
      win.forEach(function (p) { if (p - last > 1) pg.appendChild(h('span', { class: 'px-1', 'aria-hidden': 'true', text: '…' })); pb(String(p), p, 'Page ' + p, false, p === st.page); last = p; });
      pb('›', st.page + 1, 'Next page', st.page >= pages);
      AGP.mount(pager, h('span', { class: 'a-muted', text: all.length ? 'Showing ' + from + '–' + to + ' of ' + all.length : '0 results' }), pg, sizeSel);
      status.textContent = all.length + ' results, page ' + st.page + ' of ' + pages;
      updBulk();
    }
    function updBulk() {
      var rows = pageRows(), n = Object.keys(st.sel).length;
      if (cfg.bulk) {
        var onPage = rows.filter(function (r) { return st.sel[r[cfg.id]]; }).length;
        allCb.checked = rows.length > 0 && onPage === rows.length; allCb.indeterminate = onPage > 0 && onPage < rows.length;
      }
      bulkBar.hidden = !cfg.bulk || !n;
      if (!cfg.bulk || !n) return;
      AGP.mount(bulkBar, h('strong', { text: n + ' selected' }), cfg.bulk.map(function (b) {
        return h('button', { type: 'button', class: 'a-btn a-btn-sm ' + (b.cls || ''), on: { click: function () { var r = selectedRows(); Promise.resolve(b.run(r)).then(function () { st.sel = {}; render(); }); } } }, b.icon ? AGP.icon(b.icon) : null, b.label);
      }), h('button', { type: 'button', class: 'a-btn a-btn-sm a-btn-ghost', text: 'Clear', on: { click: function () { st.sel = {}; render(); } } }));
    }
    render();
    return { refresh: render, state: st, selected: selectedRows, setFilter: function (k, v) { st.f[k] = v; if (fsel[k]) fsel[k].value = v; st.page = 1; render(); } };
  };

  AGP.thumb = function (p) {
    var t = h('span', { class: 'a-thumb', 'aria-hidden': 'true' });
    if (p.image && p.image.src) t.appendChild(h('img', { src: p.image.src, alt: '', loading: 'lazy', class: 'w-full h-full object-cover' })); else { t.textContent = AGP.initials(p.title); t.style.background = AGP.tint(p.slug); }
    return t;
  };
  /* ---------- media preview, upload, picker ---------- */
  AGP.blobs = {};
  AGP.media = function () { return AGP.store.get('media', []); };
  AGP.mediaPreview = function (m, big) {
    var src = m.src || AGP.blobs[m.id];
    if (m.kind === 'image' && src) return h('img', { src: src, alt: m.alt || '' });
    var t = m.kind === 'image' ? AGP.initials(m.title || m.name) : (m.name.split('.').pop() || 'file').toUpperCase().slice(0, 4);
    var d = h('span', { text: t, 'aria-hidden': 'true' }); return d;
  };
  function loadImg(url) { return new Promise(function (res, rej) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = rej; i.src = url; }); }
  function processFile(file) {
    var kind = /^image\//.test(file.type) ? 'image' : /^video\//.test(file.type) ? 'video' : /^audio\//.test(file.type) ? 'audio' : 'file';
    var item = { id: AGP.uid('m'), name: file.name, kind: kind, size: file.size, mime: file.type, alt: '', title: '', date: new Date().toISOString().slice(0, 16), src: '' };
    if (kind === 'image') {
      return AGP.readFile(file).then(function (url) {
        if (/gif|svg/.test(file.type)) { item.src = url; return item; }
        return loadImg(url).then(function (img) {
          var s = Math.min(1, 1600 / img.width), c = document.createElement('canvas'); c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
          c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          var out = c.toDataURL('image/webp', 0.8); if (out.indexOf('data:image/webp') !== 0) out = c.toDataURL('image/jpeg', 0.82);
          item.src = out; item.size = Math.round(out.length * 0.75); item.name = file.name.replace(/\.\w+$/, '') + (out.indexOf('webp') > 0 ? '.webp' : '.jpg'); return item;
        }).catch(function () { item.src = url; return item; });
      });
    }
    if (file.size <= 1500000) return AGP.readFile(file).then(function (url) { item.src = url; return item; });
    AGP.blobs[item.id] = URL.createObjectURL(file); item.session = true; return Promise.resolve(item);
  }
  /* upload flow: choose files -> mandatory alt/title -> store. resolves with saved items. */
  AGP.uploadFiles = function (fileList, accept) {
    var files = Array.prototype.slice.call(fileList || []);
    if (!files.length) return Promise.resolve([]);
    return Promise.all(files.map(processFile)).then(function (items) {
      return new Promise(function (res) {
        var rows = items.map(function (it) {
          var ia = h('input', { class: 'a-input', value: it.kind === 'image' ? '' : 'n/a', 'aria-label': 'Alt text for ' + it.name, placeholder: 'Describe the image (required)', 'aria-required': 'true' });
          var it2 = h('input', { class: 'a-input', value: it.name.replace(/\.\w+$/, '').replace(/[-_]+/g, ' '), 'aria-label': 'Title for ' + it.name, 'aria-required': 'true' });
          var er = h('div', { class: 'a-err', hidden: true });
          var pv = h('div', { class: 'a-thumb', style: null }, AGP.mediaPreview(it)); pv.style.background = AGP.tint(it.name); pv.style.width = '72px'; pv.style.height = '56px';
          return { it: it, ia: ia, it2: it2, er: er, el: h('div', { class: 'a-q' }, h('div', { class: 'flex gap-3 items-start' }, pv, h('div', { class: 'flex-1' }, h('p', { class: 'a-label', text: it.name + ' • ' + AGP.fmtBytes(it.size) }), h('div', { class: 'a-grid2' }, h('div', {}, h('label', { class: 'a-label', text: it.kind === 'image' ? 'Alt text *' : 'Description' }), ia), h('div', {}, h('label', { class: 'a-label', text: 'Title *' }), it2)), er))) };
        });
        AGP.modal({
          title: 'Add ' + items.length + ' file' + (items.length > 1 ? 's' : ''), size: 'lg', static: true, onClose: function (v) { if (v !== 'ok') { items.forEach(function (i) { delete AGP.blobs[i.id]; }); res([]); } },
          body: h('div', {}, h('p', { class: 'a-muted', text: 'Alt text and title are mandatory for accessibility and SEO. Images are resized and converted to WebP in your browser.' }), rows.map(function (r) { return r.el; })),
          actions: [{ label: 'Cancel', cls: 'a-btn-ghost' }, {
            label: 'Save to library', cls: 'a-btn-primary', keep: true, run: function (m) {
              var bad = null;
              rows.forEach(function (r) {
                var m1 = r.it.kind === 'image' && !r.ia.value.trim() ? 'Alt text is required.' : !r.it2.value.trim() ? 'Title is required.' : '';
                r.er.hidden = !m1; r.er.textContent = m1; if (m1) { bad = bad || (r.it.kind === 'image' && !r.ia.value.trim() ? r.ia : r.it2); r.ia.setAttribute('aria-invalid', 'true'); }
              });
              if (bad) { bad.focus(); return false; }
              rows.forEach(function (r) { r.it.alt = r.ia.value.trim(); r.it.title = r.it2.value.trim(); });
              var all = AGP.media(); items.forEach(function (i) { all.unshift(i); });
              AGP.store.set('media', all);
              AGP.toast(items.length + ' file(s) added to the library.', 'ok');
              m.close('ok'); res(items); return false;
            }
          }]
        });
      });
    });
  };
  AGP.pickMedia = function (o) {
    o = o || {};
    return new Promise(function (res) {
      var chosen = {}, q = '';
      var grid = h('div', { class: 'a-media-grid' }), qi = h('input', { class: 'a-input', type: 'search', 'aria-label': 'Search media', placeholder: 'Search media…' });
      var file = h('input', { type: 'file', class: 'a-vh', multiple: o.multi ? true : null, accept: o.kind === 'image' ? 'image/*' : (o.kind === 'video' ? 'video/*' : o.kind === 'audio' ? 'audio/*' : null), tabindex: '-1', 'aria-hidden': 'true' });
      function draw() {
        AGP.clear(grid);
        var list = AGP.media().filter(function (m) { return (!o.kind || m.kind === o.kind) && (!q || (m.name + ' ' + m.title + ' ' + m.alt).toLowerCase().indexOf(q) > -1); });
        if (!list.length) grid.appendChild(h('div', { class: 'a-empty', text: 'No media yet. Upload a file.' }));
        list.forEach(function (m) {
          var pv = h('div', { class: 'pv', style: null }, AGP.mediaPreview(m)); pv.style.background = AGP.tint(m.name);
          var b = h('button', { type: 'button', class: 'a-media-item', 'aria-pressed': chosen[m.id] ? 'true' : 'false', on: { click: function () { if (!o.multi) chosen = {}; if (chosen[m.id]) delete chosen[m.id]; else chosen[m.id] = m; draw(); } } }, pv, h('div', { class: 'mt' }, h('b', { text: m.title || m.name }), h('span', { class: 'a-muted', text: m.kind + ' • ' + AGP.fmtBytes(m.size) })));
          grid.appendChild(b);
        });
      }
      qi.addEventListener('input', function () { q = qi.value.toLowerCase(); draw(); });
      file.addEventListener('change', function () { AGP.uploadFiles(file.files).then(function (items) { items.forEach(function (i) { chosen[i.id] = i; if (!o.multi) { /* keep last */ } }); draw(); }); file.value = ''; });
      draw();
      AGP.modal({
        title: o.title || 'Choose media', size: 'lg', onClose: function (v) { if (v !== 'ok') res([]); },
        body: h('div', {}, h('div', { class: 'flex gap-2 flex-wrap' }, h('div', { class: 'flex-1' }, qi), AGP.btn('Upload', { icon: 'upload', on: { click: function () { file.click(); } } }), file), grid),
        actions: [{ label: 'Cancel', cls: 'a-btn-ghost' }, { label: 'Use selected', cls: 'a-btn-primary', keep: true, run: function (m) { var v = Object.keys(chosen).map(function (k) { return chosen[k]; }); if (!v.length) { AGP.toast('Select at least one item.', 'warn'); return false; } m.close('ok'); res(v); return false; } }]
      });
    });
  };
  AGP.pickPosts = function (o) {
    o = o || {};
    return new Promise(function (res) {
      var sel = {}; (o.selected || []).forEach(function (i) { sel[i] = 1; });
      var qi = h('input', { class: 'a-input', type: 'search', 'aria-label': 'Search posts', placeholder: 'Search posts…' }), list = h('ul', { class: 'a-list' });
      function draw() {
        var q = qi.value.toLowerCase(); AGP.clear(list);
        AGP.store.get('posts', []).filter(function (p) { return p.status === 'published' && (!q || p.title.toLowerCase().indexOf(q) > -1); }).slice(0, 40).forEach(function (p) {
          var cb = h('input', { type: 'checkbox', checked: !!sel[p.id], id: 'pp' + p.id });
          cb.addEventListener('change', function () { if (!o.multi && cb.checked) { sel = {}; } if (cb.checked) sel[p.id] = 1; else delete sel[p.id]; if (!o.multi) draw(); });
          list.appendChild(h('li', {}, cb, h('label', { for: 'pp' + p.id, class: 'flex-1', text: p.title })));
        });
      }
      qi.addEventListener('input', draw); draw();
      AGP.modal({ title: o.title || 'Choose posts', size: 'lg', onClose: function (v) { if (v !== 'ok') res(null); }, body: h('div', {}, qi, list), actions: [{ label: 'Cancel', cls: 'a-btn-ghost' }, { label: 'Apply', cls: 'a-btn-primary', keep: true, run: function (m) { m.close('ok'); res(Object.keys(sel)); return false; } }] });
    });
  };

  /* categories helpers */
  AGP.cats = function () { return AGP.store.get('categories', []); };
  AGP.catName = function (c, lang) { return (c.name && (c.name[lang || 'fr'] || c.name.fr || c.name.en)) || c.slug; };
  AGP.catTree = function (cats) {
    cats = cats || AGP.cats(); var by = {}, roots = [];
    cats.forEach(function (c) { by[c.id] = { c: c, kids: [] }; });
    cats.forEach(function (c) { if (c.parent && by[c.parent]) by[c.parent].kids.push(by[c.id]); else roots.push(by[c.id]); });
    return roots;
  };
  AGP.catFlat = function (cats) { var out = []; (function w(ns, d) { ns.forEach(function (n) { out.push({ c: n.c, depth: d }); w(n.kids, d + 1); }); })(AGP.catTree(cats), 0); return out; };
  AGP.posts = function () { return AGP.store.get('posts', []); };
  AGP.savePosts = function (p) { return AGP.store.set('posts', p); };

  /* ---------- backups: snapshot, schedule check, restore (the Backups page and every admin page use these) ---------- */
  var BPFX = 'agp.admin.', BSKIP = /^(session|theme|lock|backups)$|^bk\./;
  AGP.backupCfg = function () { return Object.assign({ enabled: true, frequency: 'daily', retention: 3, destination: 'browser' }, AGP.store.get('backupcfg', {})); };
  function fnv(str) { var x = 2166136261; for (var i = 0; i < str.length; i++) { x ^= str.charCodeAt(i); x = Math.imul(x, 16777619); } return ('00000000' + (x >>> 0).toString(16)).slice(-8); }
  AGP.backupSnapshot = function () {
    var keys = {};
    try {
      for (var i = 0; i < localStorage.length; i++) {
        var k = localStorage.key(i); if (k.indexOf(BPFX) !== 0) continue; var n = k.slice(BPFX.length); if (BSKIP.test(n)) continue;
        try { keys[n] = JSON.parse(localStorage.getItem(k)); } catch (e) { keys[n] = localStorage.getItem(k); }
      }
    } catch (e) { /* storage blocked */ }
    return { format: 'agp-backup', version: 1, createdAt: new Date().toISOString(), keys: keys };
  };
  AGP.backupNow = function (type) {
    var snap = AGP.backupSnapshot(), text = JSON.stringify(snap), s = AGP.store.get('session', null), u = s && AGP.users().filter(function (x) { return x.id === s.userId; })[0];
    var entry = { id: AGP.uid('bk'), at: snap.createdAt, type: type, by: type === 'auto' ? 'System' : (u ? u.name : 'System'), size: text.length, keys: Object.keys(snap.keys).length, checksum: fnv(text), data: false };
    try { localStorage.setItem(BPFX + 'bk.' + entry.id, text); entry.data = true; } catch (e) { entry.data = false; }   // too large for the browser: the entry stays, download-only
    var list = AGP.store.get('backups', []); list.push(entry);
    var stored = list.filter(function (x) { return x.data; }), keep = AGP.backupCfg().retention;
    while (stored.length > keep) { var old = stored.shift(); try { localStorage.removeItem(BPFX + 'bk.' + old.id); } catch (e) { /* ignore */ } old.data = false; }
    AGP.store.set('backups', list);
    AGP.audit('backup_' + type.replace(/-/g, '_'), 'backups', entry.id, AGP.fmtBytes(entry.size) + ', ' + entry.keys + ' data sets');
    return { entry: entry, text: text };
  };
  AGP.backupRestore = function (snap) {
    AGP.backupNow('pre-restore');   // always keep a way back
    var n = 0, keys = snap.keys || {};
    Object.keys(keys).forEach(function (k) { if (BSKIP.test(k) || k === 'audit') return; try { localStorage.setItem(BPFX + k, JSON.stringify(keys[k])); n++; } catch (e) { /* quota */ } });
    try {
      for (var i = localStorage.length - 1; i >= 0; i--) { var k = localStorage.key(i); if (k.indexOf(BPFX) !== 0) continue; var name = k.slice(BPFX.length); if (!BSKIP.test(name) && name !== 'audit' && !(name in keys)) localStorage.removeItem(k); }
    } catch (e) { /* ignore */ }
    AGP.audit('backup_restore', 'backups', snap.createdAt || 'file', n + ' data sets restored');
    return n;
  };
  AGP.backupDue = function () {
    var list = AGP.store.get('backups', null);
    if (list === null) {   // first run: a short demo history so the page is reviewable (metadata only, no stored data)
      list = []; for (var i = 5; i >= 1; i--) { var at = new Date(Date.now() - i * 86400000 - 3600000).toISOString(); list.push({ id: 'bkdemo' + i, at: at, type: 'auto', by: 'System', size: 610000 + i * 4300, keys: 16, checksum: fnv('demo' + i), data: false, demo: true }); }
      AGP.store.set('backups', list);
    }
    var c = AGP.backupCfg(); if (!c.enabled) return;
    var last = list.filter(function (x) { return x.type === 'auto'; }).map(function (x) { return Date.parse(x.at); }).sort().pop();
    if (!last || Date.now() - last > (c.frequency === 'weekly' ? 7 : 1) * 86400000) AGP.backupNow('auto');
  };
})();
