/* Africa Global Press back-office: core (storage, utils, UI primitives, shell).
   Vanilla JS, no frameworks. Works from file://. All user text is escaped (html`` tag) or set via textContent. */
(function () {
  'use strict';
  var AGP = (window.AGP = { pages: {}, version: '1.0.0' });
  var PFX = 'agp.admin.';

  /* ---------- storage (localStorage with in-memory fallback) ---------- */
  var mem = {};
  AGP.store = {
    get: function (k, d) {
      try { var v = localStorage.getItem(PFX + k); if (v !== null) return JSON.parse(v); } catch (e) { /* blocked or corrupt */ }
      return k in mem ? mem[k] : d;
    },
    set: function (k, v) {
      mem[k] = v;
      try { localStorage.setItem(PFX + k, JSON.stringify(v)); return true; }
      catch (e) { AGP.toast('Browser storage is full or blocked. Changes kept for this session only.', 'warn'); return false; }
    },
    del: function (k) { delete mem[k]; try { localStorage.removeItem(PFX + k); } catch (e) { /* ignore */ } }
  };


  /* ---------- audit trail (append-only): every write to a tracked store key is journaled ---------- */
  var AUDIT_KEYS = { posts: 'posts', categories: 'categories', media: 'media', comments: 'comments', subscribers: 'newsletter', blacklist: 'newsletter', ads: 'ads', menus: 'menus', featured: 'featured',
    settings: 'settings', roles: 'users', users: 'users', podcasts: 'podcasts', videos: 'videos', magazine: 'magazine', radios: 'radios', tv: 'television', backupcfg: 'backups' };
  function ids(v) { var o = {}; (Array.isArray(v) ? v : []).forEach(function (x, i) { o[x && x.id != null ? x.id : 'i' + i] = JSON.stringify(x); }); return o; }
  function diffSummary(prev, next) {
    function arrays(v) { if (Array.isArray(v)) return { _: v }; if (v && typeof v === 'object') { var o = {}, any = false; Object.keys(v).forEach(function (k) { if (Array.isArray(v[k])) { o[k] = v[k]; any = true; } }); return any ? o : null; } return null; }
    var a = arrays(prev), b = arrays(next), add = 0, rem = 0, chg = 0;
    if (a || b) {
      Object.keys(Object.assign({}, a, b)).forEach(function (k) {
        var x = ids(a && a[k]), y = ids(b && b[k]);
        Object.keys(y).forEach(function (id) { if (!(id in x)) add++; else if (x[id] !== y[id]) chg++; });
        Object.keys(x).forEach(function (id) { if (!(id in y)) rem++; });
      });
      return { add: add, rem: rem, chg: chg, text: [add && '+' + add + ' added', chg && chg + ' changed', rem && rem + ' removed'].filter(Boolean).join(', ') };
    }
    var keys = Object.keys(Object.assign({}, prev, next)).filter(function (k) { return JSON.stringify((prev || {})[k]) !== JSON.stringify((next || {})[k]); });
    return { add: 0, rem: 0, chg: keys.length, text: keys.length ? 'fields: ' + keys.slice(0, 6).join(', ') + (keys.length > 6 ? '…' : '') : '' };
  }
  AGP.audit = function (action, module, target, detail, who) {
    try {
      var s = AGP.store.get('session', null), u = who || (s && AGP.users().filter(function (x) { return x.id === s.userId; })[0]) || { id: '', name: 'System', role: 'system' };
      var log = AGP.store.get('audit', []);
      log.push({ id: AGP.uid('au'), at: new Date().toISOString(), userId: u.id, user: u.name, role: u.role, action: action, module: module, target: target || '', detail: detail || '', ip: '192.0.2.' + (1 + AGP.hue(u.id || 'sys') % 200) });
      rawSet('audit', log.slice(-1000));
    } catch (e) { /* the trail must never break the action it records */ }
  };
  var rawSet = AGP.store.set;
  AGP.store.set = function (k, v) {
    var tracked = AUDIT_KEYS[k] && !AGP._seeding, prev = tracked ? AGP.store.get(k, null) : null;
    var ok = rawSet(k, v);
    if (tracked && prev !== null) {
      var d = diffSummary(prev, v);
      if (d.add || d.rem || d.chg) AGP.audit(d.add && !d.rem && !d.chg ? 'create' : d.rem && !d.add && !d.chg ? 'delete' : 'update', AUDIT_KEYS[k], k, d.text);
    }
    return ok;
  };

  /* ---------- utils ---------- */
  var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };
  var esc = (AGP.esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"'`]/g, function (c) { return ESC[c]; }); });
  function Safe(s) { this.s = s; }
  Safe.prototype.toString = function () { return this.s; };
  function val(v) {
    if (v instanceof Safe) return v.s;
    if (Array.isArray(v)) return v.map(val).join('');
    if (v == null || v === false) return '';
    return esc(v);
  }
  /* html`` auto-escapes every interpolation unless it is itself an html`` result (or AGP.raw). */
  AGP.html = function (s) {
    var out = s[0];
    for (var i = 1; i < s.length; i++) out += val(arguments[i]) + s[i];
    return new Safe(out);
  };
  AGP.raw = function (s) { return new Safe(String(s)); };
  AGP.setHTML = function (el, safe) { el.innerHTML = safe instanceof Safe ? safe.s : esc(safe); return el; };
  var html = AGP.html;
  AGP.$ = function (s, r) { return (r || document).querySelector(s); };
  AGP.$$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var $ = AGP.$, $$ = AGP.$$;
  AGP.uid = function (p) { return (p || 'id') + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); };
  AGP.slugify = function (s) {
    return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);
  };
  AGP.debounce = function (fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms || 200); }; };
  AGP.fmtDate = function (iso, withTime) {
    if (!iso) return '';
    var d = new Date(iso); if (isNaN(d)) return String(iso);
    try { return new Intl.DateTimeFormat('en-GB', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(d); } catch (e) { return iso; }
  };
  AGP.nowLocal = function () { var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };
  AGP.fmtBytes = function (n) { n = +n || 0; return n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; };
  AGP.initials = function (n) { return String(n || '?').split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase(); };
  AGP.hue = function (s) { var h = 0; String(s).split('').forEach(function (c) { h = (h * 31 + c.charCodeAt(0)) % 360; }); return h; };
  AGP.tint = function (s) { return 'hsl(' + AGP.hue(s) + ' 45% 30%)'; };
  AGP.textOf = function (htmlStr) { var d = new DOMParser().parseFromString(String(htmlStr || ''), 'text/html'); return (d.body.textContent || '').replace(/\s+/g, ' ').trim(); };
  AGP.params = function () { return new URLSearchParams(location.search); };

  /* DOM builder: h('div',{class:'x', on:{click:fn}, 'aria-label':'..'}, child, 'text') */
  var h = (AGP.h = function (tag, props) {
    var el = document.createElement(tag);
    props = props || {};
    Object.keys(props).forEach(function (k) {
      var v = props[k];
      if (v == null || v === false) return;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k === 'on') Object.keys(v).forEach(function (ev) { el.addEventListener(ev, v[ev]); });
      else if (k === 'value' || k === 'checked' || k === 'disabled' || k === 'hidden') el[k] = v;
      else el.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
    return el;
  });
  function add(el, c) {
    if (c == null || c === false) return;
    if (Array.isArray(c)) return c.forEach(function (x) { add(el, x); });
    if (c instanceof Safe) { el.insertAdjacentHTML('beforeend', c.s); return; }
    el.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
  }
  AGP.clear = function (el) { while (el.firstChild) el.removeChild(el.firstChild); return el; };
  AGP.mount = function (el) { AGP.clear(el); for (var i = 1; i < arguments.length; i++) add(el, arguments[i]); return el; };
  AGP.on = function (root, ev, sel, fn) {
    root.addEventListener(ev, function (e) { var t = e.target.closest && e.target.closest(sel); if (t && root.contains(t)) fn(e, t); });
  };

  /* ---------- icons (static strings only) ---------- */
  var IC = {
    home: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>', file: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
    folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>', image: '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
    star: '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>', msg: '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-5.6A8 8 0 1 1 21 12z"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>', users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
    ad: '<path d="M3 11v3a1 1 0 0 0 1 1h3l8 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M19 8a5 5 0 0 1 0 8"/>', menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>', plus: '<path d="M12 5v14M5 12h14"/>',
    x: '<path d="M6 6l12 12M18 6L6 18"/>', check: '<path d="M5 12.5l4.5 4.5L19 7"/>', search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>', edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
    eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
    moon: '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>', down: '<path d="M6 9l6 6 6-6"/>', right: '<path d="M9 6l6 6-6 6"/>', up: '<path d="M6 15l6-6 6 6"/>', left: '<path d="M15 6l-6 6 6 6"/>',
    grip: '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
    download: '<path d="M12 3v12M7 11l5 5 5-5M4 20h16"/>', upload: '<path d="M12 16V4M7 8l5-5 5 5M4 20h16"/>', bold: '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>',
    italic: '<path d="M10 5h8M6 19h8M14 5l-4 14"/>', link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1-1"/>',
    list: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>', quote: '<path d="M5 18v-5a5 5 0 0 1 5-5M14 18v-5a5 5 0 0 1 5-5"/>',
    spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>',
    cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>', clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    video: '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/>', mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    quiz: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>', layers: '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
    utensils: '<path d="M6 3v8a2 2 0 0 0 2 2v8M10 3v8M6 7h4M17 3c-2 2-3 5-3 8h3v10"/>', logout: '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h10"/>',
    bell: '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0"/>', shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>', globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>', indent: '<path d="M4 6h16M12 12h8M12 18h8M4 9l3 3-3 3"/>', outdent: '<path d="M4 6h16M12 12h8M12 18h8M7 9l-3 3 3 3"/>',
    chart: '<path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/>', info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>', refresh: '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/>', lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    tv: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 21h8M12 18v3M9 3l3 3 3-3"/>', radio: '<rect x="3" y="9" width="18" height="11" rx="2"/><circle cx="15" cy="14.5" r="2.5"/><path d="M6 13h4M6 16h4M5 9l12-5"/>',
    book: '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h10"/>', tag: '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
    db: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>', pie: '<path d="M12 3v9h9a9 9 0 1 1-9-9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/>',
    heading: '<path d="M6 4v16M18 4v16M6 12h12"/>', ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>'
  };
  AGP.icon = function (n, cls) {
    var s = document.createElement('span');
    s.innerHTML = '<svg class="a-ico ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + (IC[n] || '') + '</svg>';
    return s.firstChild;
  };
  AGP.iconHTML = function (n, cls) { return AGP.raw('<svg class="a-ico ' + esc(cls || '') + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + (IC[n] || '') + '</svg>'); };
  AGP.btn = function (label, opts) {
    opts = opts || {};
    var b = h(opts.href ? 'a' : 'button', { class: 'a-btn ' + (opts.cls || ''), type: opts.href ? null : 'button', href: opts.href, 'aria-label': opts.aria, on: opts.on });
    if (opts.icon) b.appendChild(AGP.icon(opts.icon));
    if (label) b.appendChild(h('span', { text: label }));
    return b;
  };

  /* ---------- toast ---------- */
  var toastBox;
  AGP.toast = function (msg, type, opts) {
    opts = opts || {};
    if (!toastBox) { toastBox = h('div', { class: 'a-toasts', role: 'region', 'aria-label': 'Notifications' }); document.body.appendChild(toastBox); }
    var t = h('div', { class: 'a-toast ' + (type || 'ok'), role: type === 'err' ? 'alert' : 'status' }, h('p', { text: msg }));
    var timer, left = opts.ms || (opts.action ? 8000 : 4500), started;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    function kill() { clearTimeout(timer); if (!t.parentNode || t.classList.contains('closing')) return; t.classList.add('closing'); setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, reduce ? 0 : 160); }
    function start() { started = Date.now(); timer = setTimeout(kill, left); }
    function pause() { clearTimeout(timer); left -= Date.now() - started; if (left < 1500) left = 1500; }
    t.addEventListener('mouseenter', pause); t.addEventListener('mouseleave', start); t.addEventListener('focusin', pause); t.addEventListener('focusout', start);
    if (opts.action) t.appendChild(h('button', { type: 'button', text: opts.action.label, on: { click: function () { opts.action.run(); kill(); } } }));
    t.appendChild(h('button', { type: 'button', 'aria-label': 'Dismiss notification', text: '×', on: { click: kill } }));
    toastBox.appendChild(t);
    start();
    return t;
  };

  /* ---------- modal (focus trap, Esc, restore focus) ---------- */
  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"]),[contenteditable="true"]';
  AGP.modal = function (o) {
    var prev = document.activeElement, id = AGP.uid('m');
    var body = h('div', { class: 'mb' });
    AGP.mount(body, o.body);
    var closeBtn = h('button', { type: 'button', class: 'a-iconbtn', 'aria-label': 'Close dialog' }, AGP.icon('x'));
    var box = h('div', { class: 'a-modal ' + (o.size || ''), role: o.alert ? 'alertdialog' : 'dialog', 'aria-modal': 'true', 'aria-labelledby': id + 't', tabindex: '-1' },
      h('header', {}, h('h2', { id: id + 't', text: o.title }), closeBtn), body);
    var wrap = h('div', { class: 'a-modal-wrap' }, box);
    var main = $('.a-shell');
    var api = { el: box, body: body, close: close };
    if (o.actions && o.actions.length) {
      var foot = h('footer');
      o.actions.forEach(function (a) {
        var b = h('button', { type: 'button', class: 'a-btn ' + (a.cls || ''), text: a.label, on: { click: function () { if (a.run && a.run(api) === false) return; if (!a.keep) close(); } } });
        a.el = b; foot.appendChild(b);
      });
      box.appendChild(foot);
    }
    function close(v) {
      document.removeEventListener('keydown', key, true);
      var rm = function () { if (wrap.parentNode) wrap.parentNode.removeChild(wrap); };
      if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) rm(); else { wrap.classList.add('closing'); setTimeout(rm, 140); }
      document.body.style.overflow = '';
      if (main && $$('.a-modal-wrap:not(.closing)').length === 0) main.removeAttribute('inert');
      if (prev && prev.focus && document.contains(prev)) prev.focus();
      if (o.onClose) o.onClose(v);
    }
    function key(e) {
      if (e.key === 'Escape' && wrap.parentNode && wrap === lastWrap()) { e.stopPropagation(); close(); return; }
      if (e.key !== 'Tab' || wrap !== lastWrap()) return;
      var f = $$(FOCUSABLE, box).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === box)) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    function lastWrap() { var all = $$('.a-modal-wrap:not(.closing)'); return all[all.length - 1]; }
    closeBtn.addEventListener('click', function () { close(); });
    wrap.addEventListener('mousedown', function (e) { if (e.target === wrap && !o.static) close(); });
    document.addEventListener('keydown', key, true);
    document.body.appendChild(wrap);
    document.body.style.overflow = 'hidden';
    if (main) main.setAttribute('inert', '');
    var first = o.focus ? $(o.focus, box) : $$(FOCUSABLE, body)[0];
    (first || box).focus();
    return api;
  };

  AGP.confirm = function (o) {
    return new Promise(function (res) {
      var done = false;
      function fin(v) { if (!done) { done = true; res(v); } }
      AGP.modal({
        title: o.title || 'Are you sure?', size: 'sm', alert: true, onClose: function () { fin(false); },
        body: h('p', { text: o.text || '' }),
        actions: [
          { label: o.cancel || 'Cancel', cls: 'a-btn-ghost', run: function () { fin(false); } },
          { label: o.ok || 'Confirm', cls: o.danger ? 'a-btn-danger' : 'a-btn-primary', run: function () { fin(true); } }
        ], focus: null
      });
    });
  };

  /* Form modal: fields [{name,label,type,options,required,value,hint,placeholder,min,max,rows,full,validate}] */
  AGP.formModal = function (o) {
    var form = h('form', { novalidate: 'novalidate', class: 'a-grid2' });
    var inputs = {};
    (o.fields || []).forEach(function (f) {
      var id = AGP.uid('f'), c;
      var v = f.value == null ? '' : f.value;
      if (f.type === 'select') {
        c = h('select', { class: 'a-select', id: id, name: f.name }, (f.options || []).map(function (op) { var ov = typeof op === 'object' ? op.v : op, ol = typeof op === 'object' ? op.l : op; return h('option', { value: ov, text: ol, selected: String(ov) === String(v) ? true : null }); }));
      } else if (f.type === 'textarea') {
        c = h('textarea', { class: 'a-textarea', id: id, name: f.name, rows: f.rows || 4, placeholder: f.placeholder }); c.value = v;
      } else if (f.type === 'checkbox') {
        c = h('input', { type: 'checkbox', id: id, name: f.name, checked: !!f.value });
      } else {
        c = h('input', { class: 'a-input', type: f.type || 'text', id: id, name: f.name, placeholder: f.placeholder, min: f.min, max: f.max, step: f.step, value: String(v), autocomplete: 'off' });
        if (f.type === 'color') c.className = 'a-input';
      }
      if (f.required) c.setAttribute('aria-required', 'true');
      var err = h('div', { class: 'a-err', id: id + 'e', hidden: true });
      var wrapEl;
      if (f.type === 'checkbox') wrapEl = h('div', { class: 'a-field' }, h('label', { class: 'a-check', for: id }, c, h('span', { text: f.label })), f.hint && h('div', { class: 'a-hint', text: f.hint }));
      else wrapEl = h('div', { class: 'a-field', style: null }, h('label', { class: 'a-label', for: id, text: f.label + (f.required ? ' *' : '') }), c, f.hint && h('div', { class: 'a-hint', text: f.hint }), err);
      if (f.full !== false && (f.type === 'textarea' || f.full)) wrapEl.style.gridColumn = '1 / -1';
      inputs[f.name] = { el: c, err: err, f: f };
      form.appendChild(wrapEl);
    });
    function read() {
      var out = {};
      Object.keys(inputs).forEach(function (k) { var i = inputs[k]; out[k] = i.f.type === 'checkbox' ? i.el.checked : (i.f.type === 'number' ? (i.el.value === '' ? '' : +i.el.value) : i.el.value.trim()); });
      return out;
    }
    function validate() {
      var vals = read(), bad = null;
      Object.keys(inputs).forEach(function (k) {
        var i = inputs[k], m = '';
        if (i.f.required && (vals[k] === '' || vals[k] === false)) m = 'This field is required.';
        else if (i.f.type === 'email' && vals[k] && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(vals[k])) m = 'Enter a valid email address.';
        else if (i.f.type === 'url' && vals[k] && !/^(https?:\/\/|\/|#|[\w.-]+\.html)/i.test(vals[k])) m = 'Enter a valid URL.';
        else if (i.f.validate) m = i.f.validate(vals[k], vals) || '';
        i.err.hidden = !m; i.err.textContent = m;
        if (m) { i.el.setAttribute('aria-invalid', 'true'); i.el.setAttribute('aria-describedby', i.err.id); bad = bad || i.el; } else { i.el.removeAttribute('aria-invalid'); i.el.removeAttribute('aria-describedby'); }
      });
      if (bad) bad.focus();
      return bad ? null : vals;
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });
    var m;
    function submit() { var v = validate(); if (v && o.onSubmit(v, m) !== false) m.close(); }
    m = AGP.modal({
      title: o.title, size: o.size, body: form,
      actions: [{ label: 'Cancel', cls: 'a-btn-ghost' }, { label: o.submit || 'Save', cls: 'a-btn-primary', keep: true, run: function () { submit(); return false; } }]
    });
    return m;
  };

  /* ---------- permissions & session ---------- */
  var MODULES = [
    ['posts', 'Posts'], ['categories', 'Categories'], ['media', 'Media library'], ['featured', 'Featured content'], ['comments', 'Comments'],
    ['newsletter', 'Newsletter'], ['ads', 'Ad zones'], ['podcasts', 'Podcasts'], ['menus', 'Menus'], ['settings', 'Settings'], ['users', 'Authors & roles'], ['ai', 'AI writer'],
    ['analytics', 'Analytics'], ['tags', 'Tags'], ['videos', 'Videos'], ['magazine', 'Magazine'], ['radios', 'African radios'], ['television', 'African television'], ['audit', 'Audit log'], ['backups', 'Backups']
  ];
  var ACTIONS = [['view', 'View'], ['create', 'Create'], ['edit', 'Edit'], ['delete', 'Delete'], ['publish', 'Publish / approve']];
  AGP.MODULES = MODULES; AGP.ACTIONS = ACTIONS;
  function allPerm(acts) { var o = {}; MODULES.forEach(function (m) { o[m[0]] = acts.slice(); }); return o; }
  function defaults() {
    var A = ['view', 'create', 'edit', 'delete', 'publish'];
    var editorPerm = allPerm(A); editorPerm.settings = ['view']; editorPerm.users = ['view']; editorPerm.ads = ['view'];  editorPerm.menus = ['view'];
    editorPerm.analytics = ['view']; editorPerm.audit = []; editorPerm.backups = [];   // the audit trail and backups are administrator-only
    var authorPerm = {}; MODULES.forEach(function (m) { authorPerm[m[0]] = []; });
    authorPerm.posts = ['view', 'create', 'edit']; authorPerm.media = ['view', 'create']; authorPerm.categories = ['view']; authorPerm.ai = ['view', 'create']; authorPerm.tags = ['view'];
    var modPerm = {}; MODULES.forEach(function (m) { modPerm[m[0]] = []; });
    modPerm.comments = A.slice(); modPerm.posts = ['view']; modPerm.newsletter = ['view'];
    return {
      roles: [
        { id: 'admin', name: 'Administrator', perms: allPerm(A), system: true },
        { id: 'editor', name: 'Editor', perms: editorPerm },
        { id: 'author', name: 'Author', perms: authorPerm },
        { id: 'moderator', name: 'Moderator', perms: modPerm }
      ],
      users: [
        { id: 'u1', name: 'Pressafrica Media', email: 'media@pressafrica.example', role: 'admin', status: 'active', bio: 'Newsroom desk.', joined: '2024-03-01' },
        { id: 'u2', name: 'Africa Global Press', email: 'desk@pressafrica.example', role: 'editor', status: 'active', bio: 'Editorial desk.', joined: '2024-03-05' },
        { id: 'u3', name: 'Comlan Anicet TIDJO', email: 'comlan@pressafrica.example', role: 'author', status: 'active', bio: 'Reporter, West Africa.', joined: '2024-06-12' },
        { id: 'u4', name: 'Koffi Didi HOUNNOU', email: 'koffi@pressafrica.example', role: 'author', status: 'active', bio: 'Reporter, economy and society.', joined: '2024-09-02' },
        { id: 'u5', name: 'Awa Diallo', email: 'awa@pressafrica.example', role: 'moderator', status: 'active', bio: 'Community moderator.', joined: '2025-01-20' }
      ]
    };
  }
  AGP.users = function () { return AGP.store.get('users', []); };
  AGP.roles = function () { return AGP.store.get('roles', []); };
  AGP.user = function () {
    var s = AGP.store.get('session', null); if (!s) return null;
    return AGP.users().filter(function (u) { return u.id === s.userId; })[0] || null;
  };
  AGP.role = function (u) { u = u || AGP.user(); return u && AGP.roles().filter(function (r) { return r.id === u.role; })[0]; };
  AGP.can = function (mod, act) { var r = AGP.role(); return !!(r && r.perms[mod] && r.perms[mod].indexOf(act || 'view') > -1); };
  AGP.userName = function (id) { var u = AGP.users().filter(function (x) { return x.id === id; })[0]; return u ? u.name : 'Unknown'; };

  /* Migration: browsers seeded before a module existed get that module's default permissions per role (custom roles get none). */
  function migrate() {
    var d = defaults(), roles = AGP.roles(), changed = false;
    roles.forEach(function (r) {
      if (!r.perms) return;
      var dr = d.roles.filter(function (x) { return x.id === r.id; })[0];
      MODULES.forEach(function (m) {
        if (r.perms[m[0]]) return;
        r.perms[m[0]] = dr && dr.perms[m[0]] ? dr.perms[m[0]].slice() : []; changed = true;
      });
    });
    if (changed) AGP.store.set('roles', roles);
  }

  /* ---------- seed (first run) ---------- */
  function seed() {
    if (AGP.store.get('seeded', 0) === 2) return;
    AGP._seeding = true;
    var S = window.AGP_SEED || { posts: [], categories: [] };
    var d = defaults();
    AGP.store.set('posts', S.posts);
    AGP.store.set('categories', S.categories);
    AGP.store.set('roles', d.roles); AGP.store.set('users', d.users);
    var media = [], seen = {};
    S.posts.forEach(function (p) {
      if (p.image && p.image.path && !seen[p.image.path] && media.length < 40) {
        seen[p.image.path] = 1;
        media.push({ id: 'm' + media.length, name: p.image.path.split('/').pop(), kind: 'image', size: 80000 + (media.length * 7919) % 400000, alt: p.image.alt || p.title, title: p.title.slice(0, 80), date: p.date, src: p.image.src || '', seed: !p.image.src });
      }
    });
    AGP.store.set('media', media);
    var names = ['Amina K.', 'Moussa D.', 'Fatou N.', 'Jean-Luc B.', 'Grace O.', 'Samuel T.', 'Nadia H.', 'Ibrahim S.', 'Chantal M.', 'Yao A.'];
    var txt = ['Excellent analyse, merci pour cet article.', 'Very informative piece, thanks for covering this.', 'Je ne suis pas d’accord avec cette conclusion.', 'Great reporting from the field.', 'Pouvez-vous publier une suite ?', 'Check this out: buy now cheap!!! http://spam.example', 'Un sujet essentiel pour le continent.', 'Please add more sources.'];
    var com = [];
    for (var i = 0; i < 36; i++) {
      var p = S.posts[(i * 5) % Math.max(1, S.posts.length)] || { id: 'p0', title: 'Post' };
      com.push({ id: 'cm' + i, postId: p.id, postTitle: p.title, author: names[i % names.length], email: names[i % names.length].split(' ')[0].toLowerCase() + '@example.com', text: txt[i % txt.length], date: '2026-10-' + String(1 + (i % 18)).padStart(2, '0') + 'T1' + (i % 10) + ':20', status: i % 8 === 5 ? 'spam' : i % 3 === 0 ? 'pending' : i % 7 === 0 ? 'rejected' : 'approved' });
    }
    AGP.store.set('comments', com);
    var subs = [];
    for (var j = 0; j < 48; j++) subs.push({ id: 's' + j, email: ['reader', 'lecteur', 'news', 'info', 'press', 'afri'][j % 6] + j + '@example.com', name: names[j % names.length], lang: j % 3 ? 'fr' : 'en', status: j % 11 === 0 ? 'unsubscribed' : j % 13 === 0 ? 'pending' : 'active', date: '2026-0' + (1 + j % 9) + '-' + String(1 + j % 27).padStart(2, '0') });
    AGP.store.set('subscribers', subs);
    AGP.store.set('blacklist', ['spam@spammer.example', 'abuse@bad.example']);
    AGP.store.set('ads', [
      { id: 'a1', name: 'Header leaderboard', key: 'header_top', placement: 'Below logo band', active: true, desktop: { type: 'adsense', size: '728x90', code: 'ca-pub-0000000000000000 / slot 1111111111', enabled: true }, mobile: { type: 'banner', size: '320x100', code: 'img: header-mobile.webp -> https://example.com', enabled: true } },
      { id: 'a2', name: 'Sidebar MPU', key: 'sidebar_mpu', placement: 'Sidebar, top', active: true, desktop: { type: 'adsense', size: '300x250', code: 'ca-pub-0000000000000000 / slot 2222222222', enabled: true }, mobile: { type: 'adsense', size: '300x250', code: 'ca-pub-0000000000000000 / slot 2222222223', enabled: false } },
      { id: 'a3', name: 'In-article', key: 'in_article', placement: 'After 3rd paragraph', active: false, desktop: { type: 'html', size: '640x200', code: '<!-- custom sponsor block -->', enabled: true }, mobile: { type: 'html', size: '320x250', code: '<!-- custom sponsor block -->', enabled: true } }
    ]);
    var cats = S.categories.filter(function (c) { return !c.parent; });
    function mi(l, u, t) { return { id: AGP.uid('mi'), label: l, url: u, type: t || 'internal', depth: 0, blank: false }; }
    AGP.store.set('menus', {
      'main-fr': [mi('Accueil', '../fr/index.html'), mi('Politique', '../fr/politique.html', 'category'), mi('Société', '../fr/societe.html', 'category'), Object.assign(mi('Culture', '../fr/culture.html', 'mega'), {}), Object.assign(mi('Sport', '../fr/sports.html', 'category'), { depth: 1 }), mi('Contact', '../fr/contact.html')],
      'main-en': [mi('Home', '../en/index.html'), mi('Politics', '../en/politics.html', 'category'), mi('Economy', '../en/economy.html', 'category'), mi('Contact', '../en/contact.html')],
      'footer-fr': [mi('Mentions légales', '../fr/legal.html'), mi('Confidentialité', '../fr/privacy.html'), mi('Pressafrica', 'https://pressafrica.media', 'external')]
    });
    var first = S.posts.filter(function (p) { return p.status === 'published'; });
    AGP.store.set('featured', {
      slider: first.slice(0, 5).map(function (p) { return p.id; }),
      breaking: [{ id: 'b1', text: 'Cap-Vert : le bilan des intempéries révisé', url: '', active: true, expires: '' }, { id: 'b2', text: 'Sommet régional : ouverture lundi', url: '', active: false, expires: '' }],
      recommended: first.slice(5, 9).map(function (p) { return p.id; }),
      widgets: [{ id: 'w1', type: 'popular', title: 'Les plus lus', active: true, config: '5 items, last 7 days' }, { id: 'w2', type: 'poll', title: 'Sondage de la semaine', active: true, config: 'Poll #1' }, { id: 'w3', type: 'html', title: 'Partenaires', active: false, config: '<p>Custom partner block</p>' }]
    });
    AGP.store.set('settings', {
      siteTitle: 'Africa Global Press', tagline: 'L’information africaine, sans frontières', logoText: 'AGP',
      colorPrimary: '#013079', colorSecondary: '#ffec00', fontHeading: 'Alegreya', fontBody: 'Encode Sans', baseSize: 20,
      langs: ['fr', 'en'], defaultLang: 'fr', urlStructure: 'subdir',
      cookiesOn: true, cookieText: 'We only use essential cookies until you accept third-party services.', blockThirdParty: true,
      maintenance: false, maintenanceMsg: 'Back soon. The newsroom is upgrading.', maintenanceIps: '',
      pwa: true, pwaName: 'Africa Global Press', pwaShort: 'AGP', pwaTheme: '#013079',
      aiProvider: 'openai', aiOpenaiKey: '', aiGeminiKey: '', aiModel: 'gpt-4o-mini',
      commentsApproval: true, minPassword: 12, passComplexity: true, maxAttempts: 5, lockMinutes: 15, nofollow: true,
      newsSitemap: true, rssFeed: true, popup: true
    });
    AGP.store.set('seeded', 2);
    AGP._seeding = false;
  }

  /* ---------- theme ---------- */
  function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    var b = $('#themeBtn');
    if (b) { b.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false'); AGP.mount(b, AGP.icon(t === 'dark' ? 'sun' : 'moon')); b.setAttribute('aria-label', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); }
  }
  function initTheme() {
    applyTheme(AGP.store.get('theme', null) === 'dark' ? 'dark' : 'light');   // light by default, same as the public site
  }
  AGP.toggleTheme = function () { var t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; AGP.store.set('theme', t); applyTheme(t); };

  /* ---------- shell ---------- */
  var NAV = [
    ['Overview', [['index', 'index.html', 'Dashboard', 'home', null], ['analytics', 'analytics.html', 'Analytics', 'chart', 'analytics']]],
    ['Newsroom', [['posts', 'posts.html', 'Posts', 'file', 'posts'], ['categories', 'categories.html', 'Categories', 'folder', 'categories'], ['tags', 'tags.html', 'Tags', 'tag', 'tags'], ['media', 'media.html', 'Media library', 'image', 'media'], ['featured', 'featured.html', 'Featured & widgets', 'star', 'featured']]],
    ['Channels', [['podcasts', 'podcasts.html', 'Podcasts', 'mic', 'podcasts'], ['videos', 'videos.html', 'Videos', 'video', 'videos'], ['magazine', 'magazine.html', 'Magazine', 'book', 'magazine'], ['radios', 'radios.html', 'African radios', 'radio', 'radios'], ['television', 'television.html', 'African television', 'tv', 'television']]],
    ['Audience', [['comments', 'comments.html', 'Comments', 'msg', 'comments'], ['newsletter', 'newsletter.html', 'Newsletter', 'mail', 'newsletter'], ['authors', 'authors.html', 'Authors & roles', 'users', 'users']]],
    ['Site', [['ads', 'ads.html', 'Ad zones', 'ad', 'ads'], ['menus', 'menus.html', 'Menus', 'menu', 'menus'], ['settings', 'settings.html', 'Settings', 'gear', 'settings']]],
    ['System', [['audit', 'audit.html', 'Audit log', 'shield', 'audit'], ['backups', 'backups.html', 'Backups', 'db', 'backups']]]
  ];
  var PAGE_MOD = { posts: 'posts', 'post-edit': 'posts', categories: 'categories', media: 'media', featured: 'featured', comments: 'comments', newsletter: 'newsletter', authors: 'users', ads: 'ads', podcasts: 'podcasts', menus: 'menus', settings: 'settings',
    analytics: 'analytics', tags: 'tags', videos: 'videos', magazine: 'magazine', radios: 'radios', television: 'television', audit: 'audit', backups: 'backups' };
  AGP.pending = function () {
    return { posts: AGP.store.get('posts', []).filter(function (p) { return p.status === 'pending'; }).length, comments: AGP.store.get('comments', []).filter(function (c) { return c.status === 'pending'; }).length };
  };
  function buildShell(page) {
    var main = $('#main'); if (!main) return;
    var user = AGP.user(), role = AGP.role();
    var pend = AGP.pending();
    var nav = h('nav', { class: 'a-nav', 'aria-label': 'Main navigation' });
    NAV.forEach(function (g) {
      var items = g[1].filter(function (i) { return !i[4] || AGP.can(i[4], 'view'); });
      if (!items.length) return;
      nav.appendChild(h('h2', { text: g[0] }));
      items.forEach(function (i) {
        var a = h('a', { href: i[1] }, AGP.icon(i[3]), h('span', { text: i[2] }));
        if (i[0] === page || (page === 'post-edit' && i[0] === 'posts')) a.setAttribute('aria-current', 'page');
        var n = i[0] === 'posts' ? pend.posts : i[0] === 'comments' ? pend.comments : 0;
        if (n) a.appendChild(h('span', { class: 'a-count', title: (i[0] === 'posts' ? 'Posts pending review' : 'Comments pending moderation'), 'aria-label': n + (i[0] === 'posts' ? ' posts pending review' : ' comments pending'), text: String(n) }));
        nav.appendChild(a);
      });
    });
    var side = h('aside', { class: 'a-side', id: 'sidebar', 'aria-label': 'Sidebar' },
      h('a', { class: 'a-side-brand', href: 'index.html' }, h('span', { class: 'a-logo', 'aria-hidden': 'true' }, h('img', { src: '../assets/icons/icon-192.png', alt: '', width: '44', height: '44' })), h('span', {}, h('b', { text: 'Africa Global Press' }), h('small', { text: 'Newsroom console' }))),
      nav);
    var scrim = h('div', { class: 'a-scrim', 'aria-hidden': 'true' });
    var menuBtn = h('button', { type: 'button', class: 'a-iconbtn a-menu-btn', 'aria-label': 'Open navigation', 'aria-controls': 'sidebar', 'aria-expanded': 'false' }, AGP.icon('menu'));
    var mq = window.matchMedia('(max-width: 1023px)');
    function setOpen(o) {
      side.classList.toggle('open', o); scrim.classList.toggle('open', o);
      menuBtn.setAttribute('aria-expanded', String(o));
      if (mq.matches) { if (o) side.removeAttribute('inert'); else side.setAttribute('inert', ''); }
      if (o) { var a = $('a[aria-current], a', side); a && a.focus(); }
    }
    function sync() { if (mq.matches) side.setAttribute('inert', ''); else side.removeAttribute('inert'); side.classList.remove('open'); scrim.classList.remove('open'); menuBtn.setAttribute('aria-expanded', 'false'); }
    sync(); mq.addEventListener('change', sync);
    menuBtn.addEventListener('click', function () { setOpen(true); });
    scrim.addEventListener('click', function () { setOpen(false); menuBtn.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && side.classList.contains('open') && !$('.a-modal-wrap')) { setOpen(false); menuBtn.focus(); } });

    var q = h('input', { class: 'a-input', type: 'text', role: 'searchbox', placeholder: 'Search posts…', 'aria-label': 'Search posts', autocomplete: 'off' });
    var searchForm = h('form', { class: 'a-search', role: 'search', on: { submit: function (e) { e.preventDefault(); if (q.value.trim()) location.href = 'posts.html?q=' + encodeURIComponent(q.value.trim()); } } }, q);
    var themeBtn = h('button', { type: 'button', class: 'a-iconbtn', id: 'themeBtn', 'aria-pressed': 'false', on: { click: AGP.toggleTheme } });
    var umBtn = h('button', { type: 'button', class: 'a-btn a-btn-ghost a-user-btn', 'aria-label': 'Account menu: ' + user.name, 'aria-haspopup': 'true', 'aria-expanded': 'false', 'aria-controls': 'userPanel' },
      h('span', { class: 'a-avatar', 'aria-hidden': 'true', text: AGP.initials(user.name) }), h('span', { class: 'hidden sm:inline', text: user.name }), AGP.icon('down'));
    var sel = h('select', { class: 'a-select', id: 'viewAs' }, AGP.users().map(function (u) { return h('option', { value: u.id, text: u.name + ' (' + u.role + ')', selected: u.id === user.id ? true : null }); }));
    sel.addEventListener('change', function () { AGP.store.set('session', { userId: sel.value }); AGP.toast('Now viewing as ' + sel.options[sel.selectedIndex].text, 'ok'); setTimeout(function () { location.href = 'index.html'; }, 400); });
    var panel = h('div', { id: 'userPanel', class: 'a-card', hidden: true },
      h('div', { class: 'a-card-b' },
        h('p', { class: 'a-eyebrow', text: 'Signed in as' }), h('p', { class: 'a-serif', text: user.name + ' • ' + (role ? role.name : '') }),
        h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'viewAs', text: 'View as (demo: role switcher)' }), sel),
        h('div', { class: 'flex gap-2 flex-wrap' }, AGP.btn('My posts', { href: 'posts.html?author=' + user.id, cls: 'a-btn-sm' }), AGP.btn('Log out', { icon: 'logout', cls: 'a-btn-sm a-btn-danger', on: { click: function () { AGP.audit('logout', 'auth', user.email); AGP.store.del('session'); location.href = 'login.html'; } } }))));
    panel.style.position = 'absolute'; panel.style.right = '0'; panel.style.top = '52px'; panel.style.width = '300px'; panel.style.maxWidth = 'calc(100vw - 32px)'; panel.style.zIndex = '40';
    function togglePanel(open) { panel.hidden = !open; umBtn.setAttribute('aria-expanded', String(open)); }
    umBtn.addEventListener('click', function () { togglePanel(panel.hidden); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { togglePanel(false); umBtn.focus(); } });
    document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target) && !umBtn.contains(e.target)) togglePanel(false); });
    var um = h('div', { style: null, class: 'relative' }, umBtn, panel); um.style.position = 'relative';
    var top = h('header', { class: 'a-top' }, menuBtn, searchForm, h('span', { style: null, class: 'flex-1 md:hidden' }),
      AGP.can('posts', 'create') ? AGP.btn('New post', { icon: 'plus', cls: 'a-btn-flash a-btn-sm a-new', aria: 'New post', href: 'post-edit.html' }) : null,
      AGP.btn('View site', { icon: 'ext', cls: 'a-btn-ghost a-btn-sm hidden md:inline-flex', href: '../fr/index.html' }), themeBtn, um);
    var shell = h('div', { class: 'a-shell' }, side, scrim, h('div', { class: 'a-main' }, top, main));
    document.body.insertBefore(shell, document.body.firstChild);
    document.body.insertBefore(h('a', { class: 'a-skip', href: '#main', text: 'Skip to main content' }), document.body.firstChild);
    main.classList.add('a-view'); main.setAttribute('tabindex', '-1');
    applyTheme(document.documentElement.getAttribute('data-theme'));
  }

  AGP.head = function (o) {
    var acts = h('div', { class: 'flex flex-wrap gap-2' }, o.actions || []);
    return h('div', { class: 'a-page-head' }, h('div', {}, o.eyebrow ? h('p', { class: 'a-eyebrow', text: o.eyebrow }) : null, h('h1', { text: o.title }), o.sub ? h('p', { text: o.sub }) : null), acts);
  };
  AGP.denied = function (el) { AGP.mount(el, h('div', { class: 'a-card a-empty' }, AGP.icon('lock'), h('h1', { text: 'Access denied' }), h('p', { text: 'Your role does not include permission for this section. Ask an administrator, or use the role switcher in the user menu.' }))); };
  AGP.page = function (name, fn) { AGP.pages[name] = fn; };

  /* ---------- boot ---------- */
  function boot() {
    var page = document.body.getAttribute('data-page');
    initTheme();
    seed(); migrate();
    if (page === 'login') { if (AGP.pages.login) AGP.pages.login(); return; }
    if (!AGP.user()) { location.replace('login.html'); return; }
    buildShell(page);
    if (AGP.backupDue) AGP.backupDue();
    var view = $('#view');
    var mod = PAGE_MOD[page];
    if (mod && !AGP.can(mod, 'view')) { AGP.denied(view); return; }
    var fn = AGP.pages[page];
    if (fn) { try { fn(view); } catch (err) { console.error(err); AGP.toast('This page hit an error: ' + err.message, 'err'); } }
  }
  document.addEventListener('DOMContentLoaded', boot);
})();
