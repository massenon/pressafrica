/* Africa Global Press - public site behaviours (vanilla JS, no dependencies, works from file://). */
(function () {
  'use strict';

  var d = document, w = window, html = d.documentElement;
  /* Turbo Drive re-executes this script after each page swap: first drop what the previous run attached to document/window and its intervals. */
  if (w.__agpOff) w.__agpOff();
  var off = [], timers = [];
  w.__agpOff = function () { off.forEach(function (f) { f(); }); timers.forEach(function (t) { w.clearInterval(t); }); off = []; timers = []; };
  function gOn(t, ev, fn, o) { t.addEventListener(ev, fn, o); off.push(function () { t.removeEventListener(ev, fn, o); }); }
  function tInt(fn, ms) { var id = w.setInterval(fn, ms); timers.push(id); return id; }
  var LANG = (d.body && d.body.getAttribute('data-lang')) || html.lang || 'fr';
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- helpers ---------- */
  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
  function on(el, ev, fn, o) { if (!el) return; if (el === d || el === w) gOn(el, ev, fn, o); else el.addEventListener(ev, fn, o); }
  function store(k, v) {
    try { if (v === undefined) { return w.localStorage.getItem(k); } w.localStorage.setItem(k, v); } catch (e) { /* storage blocked */ }
    return null;
  }
  function norm(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  var MSG = {
    fr: { required: 'Ce champ est obligatoire.', email: 'Saisissez une adresse e-mail valide.', tel: 'Saisissez un numéro de téléphone valide.',
          consent: 'Vous devez accepter pour envoyer le message.', copied: 'Lien copié', sending: 'Envoi en cours…', saved: 'Vos préférences ont été enregistrées.' },
    en: { required: 'This field is required.', email: 'Enter a valid email address.', tel: 'Enter a valid phone number.',
          consent: 'You must agree in order to send the message.', copied: 'Link copied', sending: 'Sending…', saved: 'Your preferences have been saved.' }
  }[LANG] || {};

  // run non-critical work (autoplay) after load, when the main thread is idle
  function onIdle(fn) {
    function go() { (w.requestIdleCallback || function (f) { w.setTimeout(f, 300); })(fn, { timeout: 2000 }); }
    if (d.readyState === 'complete') go(); else gOn(w, 'load', go);
  }

  /* ---------- date in topbar ---------- */
  (function () {
    var el = $('[data-date]');
    if (!el || !w.Intl) return;
    try {
      el.textContent = new Date().toLocaleDateString(LANG === 'fr' ? 'fr-FR' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) { /* keep static date */ }
  })();

  /* ---------- language preference ---------- */
  $$('[data-lang]').forEach(function (a) {
    if (a.tagName === 'A') on(a, 'click', function () { store('agp-lang', a.getAttribute('data-lang')); });
  });
  store('agp-lang', LANG);

  /* ---------- theme (dark mode) ---------- */
  var toggles = $$('[data-theme-toggle]');
  function applyTheme(t, persist) {
    html.setAttribute('data-theme', t);
    toggles.forEach(function (b) { b.setAttribute('aria-checked', t === 'dark' ? 'true' : 'false'); });
    if (persist) store('agp-theme', t);
    var m = $('meta[name="theme-color"]'); if (m) m.setAttribute('content', t === 'dark' ? '#0b0b0b' : '#013079');
  }
  applyTheme(html.getAttribute('data-theme') || 'light', false);
  toggles.forEach(function (b) { on(b, 'click', function () { applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true); }); });

  /* ---------- dialogs (offcanvas, modals) with focus trap ---------- */
  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  var active = null, lastTrigger = null;
  var inertTargets = function () { return $$('.skip-link,#site-header,#main,#footer,.back-top').filter(function (e) { return e; }); };

  function openDialog(el, trigger) {
    if (!el) return;
    if (active && active !== el) closeDialog(active, true);
    active = el; lastTrigger = trigger || d.activeElement;
    el.hidden = false;
    void el.offsetWidth;
    el.classList.add('is-open');
    inertTargets().forEach(function (e) { e.setAttribute('inert', ''); });
    html.style.overflow = 'hidden';
    var panel = $('.oc-panel,.modal-card', el) || el;
    var first = $('input:not([type=hidden]),button.icon-btn', panel) || panel;
    w.setTimeout(function () { (first.focus ? first : panel).focus({ preventScroll: true }); }, 30);
    $$('[data-open]').forEach(function (b) { if (b.getAttribute('aria-controls') === el.id) b.setAttribute('aria-expanded', 'true'); });
  }
  function closeDialog(el, quick) {
    if (!el || el.hidden) return;
    el.classList.remove('is-open');
    var done = function () { el.hidden = true; };
    if (quick || reduce) done(); else w.setTimeout(done, 320);
    inertTargets().forEach(function (e) { e.removeAttribute('inert'); });
    html.style.overflow = '';
    $$('[data-open]').forEach(function (b) { if (b.getAttribute('aria-controls') === el.id) b.setAttribute('aria-expanded', 'false'); });
    if (active === el) active = null;
    if (lastTrigger && lastTrigger.focus && !quick) { try { lastTrigger.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
  }
  /* 'Explore by topic': Foxiz template popup (top position). The content lives in <template id="tmpl-topics-popup"> and is cloned into a dialog host on first open;
     the same open/close machinery as the other dialogs gives Esc, focus trap, inert page, scroll lock and focus return. Click outside the panel closes it. */
  function topicsPopup() {
    var host = d.getElementById('topics-popup'); if (host) return host;
    var tpl = d.getElementById('tmpl-topics-popup'); if (!tpl) return null;
    host = d.createElement('div'); host.id = 'topics-popup'; host.className = 'popup-host popup-topics pop-top is-template-popup'; host.hidden = true;
    host.setAttribute('role', 'dialog'); host.setAttribute('aria-modal', 'true'); host.setAttribute('aria-label', tpl.getAttribute('data-label') || '');
    host.innerHTML = '<div class="pop-bg"></div><div class="pop-container"><div class="pop-content"><div class="pop-body"></div>' +
      '<button type="button" class="icon-btn pop-close" data-close aria-label="' + (tpl.getAttribute('data-close') || 'Close') + '"><svg class="i" aria-hidden="true" focusable="false"><use href="#i-close"/></svg></button></div></div>';
    $('.pop-body', host).appendChild(tpl.content.cloneNode(true));
    d.body.appendChild(host);
    on(host, 'click', function (e) { if (e.target.classList.contains('pop-container') || e.target.classList.contains('pop-bg')) closeDialog(host); });
    $$('a', host).forEach(function (a) { on(a, 'click', function () { closeDialog(host, true); }); });
    return host;
  }
  var DIALOGS = { offcanvas: '#offcanvas', search: '#search-overlay', newsletter: '#newsletter-modal', login: '#login-modal' };
  gOn(d, 'click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-open],[data-close]') : null;
    if (!t) return;
    if (t.hasAttribute('data-close')) { var host = t.closest('.offcanvas,.modal,.popup-host'); closeDialog(host); return; }
    var k = t.getAttribute('data-open');
    if (k === 'cookies') { showCookie(true); return; }
    if (k === 'topics') { var pop = topicsPopup(); if (pop) { e.preventDefault(); openDialog(pop, t); } return; }
    var target = $(DIALOGS[k]);
    if (target) { e.preventDefault(); openDialog(target, t); }
  });
  gOn(d, 'keydown', function (e) {
    if (e.key === 'Escape') {
      if (active) { closeDialog(active); return; }
      closeMegas(true);
    }
    if (e.key === 'Tab' && active) {
      var list = $$(FOCUSABLE, active).filter(function (x) { return x.offsetParent !== null; });
      if (!list.length) return;
      var f = list[0], l = list[list.length - 1];
      if (e.shiftKey && (d.activeElement === f || !active.contains(d.activeElement))) { e.preventDefault(); l.focus(); }
      else if (!e.shiftKey && (d.activeElement === l || !active.contains(d.activeElement))) { e.preventDefault(); f.focus(); }
    }
  });
  // search overlay -> navigate on submit is native (GET form)
  // offcanvas links close the panel on same-page anchors
  $$('#offcanvas a').forEach(function (a) { on(a, 'click', function () { closeDialog($('#offcanvas'), true); }); });

  /* ---------- mega menus ---------- */
  var megaItems = $$('[data-mega]');
  var megaTimer = null;
  function closeMegas(returnFocus) {
    megaItems.forEach(function (it) {
      var btn = $('.nav-chev', it), p = $('.mega', it);
      if (!p || p.hidden) return;
      p.hidden = true; it.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false');
      if (returnFocus && it.contains(d.activeElement)) btn.focus();
    });
  }
  function openMega(it) {
    closeMegas(false);
    var btn = $('.nav-chev', it), p = $('.mega', it);
    if (!p) return;
    p.hidden = false; it.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true');
  }
  megaItems.forEach(function (it) {
    var btn = $('.nav-chev', it);
    on(btn, 'click', function (e) { e.stopPropagation(); (btn.getAttribute('aria-expanded') === 'true') ? closeMegas(false) : openMega(it); });
    on(it, 'mouseenter', function () { if (w.innerWidth < 1024) return; w.clearTimeout(megaTimer); megaTimer = w.setTimeout(function () { openMega(it); }, 120); });
    on(it, 'mouseleave', function () { w.clearTimeout(megaTimer); megaTimer = w.setTimeout(function () { closeMegas(false); }, 220); });
    on(it, 'focusout', function (e) { if (!it.contains(e.relatedTarget)) { var p = $('.mega', it); if (p && !p.hidden) closeMegas(false); } });
  });
  on(d, 'click', function (e) { if (!e.target.closest || !e.target.closest('[data-mega]')) closeMegas(false); });

  /* ---------- ticker ---------- */
  (function () {
    var list = $('[data-ticker]'); if (!list) return;
    var started = false, items = $$('.ticker-item', list), i = 0, timer = null, paused = reduce, hover = false;
    var bPause = $('[data-ticker-pause]');
    function show(n) {
      i = (n + items.length) % items.length;
      items.forEach(function (it, k) { it.classList.toggle('is-active', k === i); it.setAttribute('aria-hidden', k === i ? 'false' : 'true'); $$('a', it).forEach(function (a) { a.tabIndex = k === i ? 0 : -1; }); });
    }
    function play() { w.clearInterval(timer); if (started && !paused && !hover && items.length > 1) timer = tInt(function () { show(i + 1); }, 5000); }
    function setPaused(p) {
      paused = p; if (bPause) { bPause.setAttribute('aria-pressed', p ? 'true' : 'false'); bPause.setAttribute('aria-label', bPause.getAttribute(p ? 'data-l-play' : 'data-l-pause')); var u = $('use', bPause); if (u) u.setAttribute('href', p ? '#i-play' : '#i-pause'); }
      play();
    }
    on($('[data-ticker-prev]'), 'click', function () { show(i - 1); play(); });
    on($('[data-ticker-next]'), 'click', function () { show(i + 1); play(); });
    on(bPause, 'click', function () { setPaused(!paused); });
    on(list, 'mouseenter', function () { hover = true; play(); }); on(list, 'mouseleave', function () { hover = false; play(); });
    on(list, 'focusin', function () { hover = true; play(); }); on(list, 'focusout', function () { hover = false; play(); });
    show(0); setPaused(reduce);
    onIdle(function () { started = true; play(); });
  })();

  /* ---------- hero slider ---------- */
  (function () {
    var root = $('[data-hero]'); if (!root) return;
    var started = false, slides = $$('[data-slide]', root), dots = $$('[data-dot]', root), i = 0, timer = null, paused = reduce, hover = false;
    var bPause = $('[data-pause]', root), view = $('.hero-viewport', root);
    function go(n, user) {
      if (started) root.classList.add('is-ready');
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) {
        var on_ = k === i; s.classList.toggle('is-active', on_); s.setAttribute('aria-hidden', on_ ? 'false' : 'true');
        $$('a,button', s).forEach(function (x) { x.tabIndex = on_ ? 0 : -1; });
        if (on_) { var im = $('img', s); if (im && im.loading === 'lazy') im.loading = 'eager'; }
      });
      dots.forEach(function (b, k) { if (k === i) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current'); });
    }
    function play() { w.clearInterval(timer); if (started && !paused && !hover && slides.length > 1) timer = tInt(function () { go(i + 1); }, 7000); view.setAttribute('aria-live', paused || hover ? 'polite' : 'off'); }
    function setPaused(p) {
      paused = p; bPause.setAttribute('aria-pressed', p ? 'true' : 'false'); bPause.setAttribute('aria-label', bPause.getAttribute(p ? 'data-l-play' : 'data-l-pause'));
      var u = $('use', bPause); if (u) u.setAttribute('href', p ? '#i-play' : '#i-pause'); play();
    }
    on($('[data-prev]', root), 'click', function () { go(i - 1, true); play(); });
    on($('[data-next]', root), 'click', function () { go(i + 1, true); play(); });
    dots.forEach(function (b, k) { on(b, 'click', function () { go(k, true); play(); }); });
    on(bPause, 'click', function () { setPaused(!paused); });
    on(root, 'mouseenter', function () { hover = true; play(); }); on(root, 'mouseleave', function () { hover = false; play(); });
    on(root, 'focusin', function () { hover = true; play(); }); on(root, 'focusout', function () { hover = false; play(); });
    on(root, 'keydown', function (e) {
      if (e.key === 'ArrowLeft') { go(i - 1, true); play(); } else if (e.key === 'ArrowRight') { go(i + 1, true); play(); }
    });
    var sx = null;
    on(view, 'pointerdown', function (e) { sx = e.clientX; });
    on(view, 'pointerup', function (e) { if (sx === null) return; var dx = e.clientX - sx; sx = null; if (Math.abs(dx) > 50) { go(i + (dx < 0 ? 1 : -1), true); play(); } });
    go(0); setPaused(reduce);
    onIdle(function () { started = true; play(); });
  })();

  /* ---------- home: economic news pager ---------- */
  (function () {
    var root = $('[data-econ]'); if (!root) return;
    var pages = $$('[data-econ-page]', root), n = 0, prev = $('[data-econ-prev]', root), next = $('[data-econ-next]', root);
    function show(k) {
      n = Math.max(0, Math.min(pages.length - 1, k));
      pages.forEach(function (p, j) { p.hidden = j !== n; });
      prev.disabled = n === 0; next.disabled = n === pages.length - 1;
      $$('.reveal', pages[n]).forEach(function (r) { r.classList.remove('is-pending'); });
    }
    on(prev, 'click', function () { show(n - 1); }); on(next, 'click', function () { show(n + 1); });
    if (pages.length < 2) { next.disabled = true; }
    show(0);
  })();

  /* ---------- archive pagination (DOM) ---------- */
  (function () {
    var host = $('[data-paged]'); if (!host) return;
    var panels = $$('.list-page', host), btns = $$('[data-goto]'), nextBtn = $('[data-next-page]'), cur = 0;
    function show(k, scroll) {
      cur = Math.max(0, Math.min(panels.length - 1, k));
      panels.forEach(function (p, j) { p.hidden = j !== cur; $$('.reveal', p).forEach(function (r) { r.classList.remove('is-pending'); }); });
      btns.forEach(function (b, j) { if (j === cur) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); });
      if (nextBtn) nextBtn.disabled = cur === panels.length - 1;
      if (scroll) host.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    }
    btns.forEach(function (b, j) { on(b, 'click', function () { show(j, true); }); });
    on(nextBtn, 'click', function () { show(cur + 1, true); });
    if (panels.length > 1) show(0, false);
  })();

  /* ---------- thematic: load more ---------- */
  (function () {
    var btn = $('[data-loadmore]'); if (!btn) return;
    var end = $('[data-end]');
    on(btn, 'click', function () {
      var more = $$('[data-more]'), batch = more.slice(0, 9);
      batch.forEach(function (c, k) { c.hidden = false; c.removeAttribute('data-more'); $$('.reveal', c).forEach(function (r) { r.classList.remove('is-pending'); }); });
      if (batch[0]) { var a = $('a', batch[0]); if (a) a.focus({ preventScroll: false }); }
      if (!$$('[data-more]').length) { btn.hidden = true; if (end) end.hidden = false; }
    });
  })();

  /* ---------- FAQ accordions ---------- */
  $$('.faq-btn').forEach(function (b) {
    on(b, 'click', function () {
      var open = b.getAttribute('aria-expanded') === 'true', p = d.getElementById(b.getAttribute('aria-controls'));
      b.setAttribute('aria-expanded', open ? 'false' : 'true'); if (p) p.hidden = open;
    });
  });

  /* ---------- reveal on scroll ---------- */
  (function () {
    var els = $$('.reveal');
    if (!els.length) return;
    if (reduce || !('IntersectionObserver' in w)) { return; }
    var vh = w.innerHeight || 800;
    els.forEach(function (e) { if (e.getBoundingClientRect().top > vh) e.classList.add('is-pending'); });
    els = els.filter(function (e) { return e.classList.contains('is-pending'); });
    els.forEach(function (e) {
      var sib = e.parentElement ? $$(':scope > .reveal', e.parentElement) : [];
      var k = Math.max(0, sib.indexOf(e)); e.style.setProperty('--d', Math.min(k, 5) * 70 + 'ms');
    });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.remove('is-pending'); en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    els.forEach(function (e) { io.observe(e); });
  })();

  /* ---------- back to top ---------- */
  (function () {
    var b = $('[data-backtop]'); if (!b) return;
    b.hidden = false;
    function upd() { b.classList.toggle('is-on', (w.pageYOffset || html.scrollTop) > 700); b.tabIndex = b.classList.contains('is-on') ? 0 : -1; }
    on(w, 'scroll', upd, { passive: true }); upd();
    on(b, 'click', function (e) { e.preventDefault(); w.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); var m = $('#main'); if (m) m.focus({ preventScroll: true }); });
  })();

    /* ---------- podcasts: filter episodes by show; only one audio plays at a time ---------- */
  (function () {
    var list = $('[data-pod-list]');
    $$('[data-pod-filter]').forEach(function (b) {
      on(b, 'click', function () {
        var k = b.getAttribute('data-pod-filter');
        $$('[data-pod-filter]').forEach(function (x) { var a = x === b; x.classList.toggle('is-on', a); x.setAttribute('aria-pressed', a ? 'true' : 'false'); });
        if (list) $$('.pod-ep', list).forEach(function (el) { el.hidden = k !== 'all' && el.getAttribute('data-show') !== k; });
      });
    });
    on(d, 'play', function (e) {
      if (!e.target || e.target.tagName !== 'AUDIO') return;
      $$('audio').forEach(function (a) { if (a !== e.target) a.pause(); });
    }, true);
  })();

  /* ---------- videos: filter by topic; a card loads its video into the main player ---------- */
  (function () {
    var list = $('[data-vid-list]'), player = $('#vid-player'); if (!list) return;
    $$('[data-vid-filter]').forEach(function (b) {
      on(b, 'click', function () {
        var k = b.getAttribute('data-vid-filter');
        $$('[data-vid-filter]').forEach(function (x) { var a = x === b; x.classList.toggle('is-on', a); x.setAttribute('aria-pressed', a ? 'true' : 'false'); });
        $$('.vid-card', list).forEach(function (el) { el.hidden = k !== 'all' && el.getAttribute('data-topic') !== k; });
      });
    });
    if (!player) return;
    function load(a) {
      player.poster = a.getAttribute('data-poster'); player.src = a.getAttribute('data-src');
      player.setAttribute('aria-label', a.getAttribute('aria-label'));
      $('#vid-title').textContent = a.getAttribute('data-title'); $('#vid-desc').textContent = a.getAttribute('data-desc'); $('#vid-meta').textContent = a.getAttribute('data-meta');
      var rd = $('#vid-read'); if (rd) { rd.href = a.getAttribute('data-read'); rd.hidden = false; }
      $('#vid-top').scrollIntoView({ behavior: w.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      var p = player.play(); if (p && p.catch) p.catch(function () { /* autoplay refused: controls stay available */ });
    }
    $$('[data-vid-play]', list).forEach(function (a) { on(a, 'click', function (e) { e.preventDefault(); load(a); }); });
    var hash = w.location.hash && $(w.location.hash + ' [data-vid-play]'); if (hash) load(hash);   // deep link from the header menu
  })();

  /* ---------- radios / television directories: filter entries by country ---------- */
  (function () {
    var list = $('[data-dir-list]'); if (!list) return;
    $$('[data-dir-filter]').forEach(function (b) {
      on(b, 'click', function () {
        var k = b.getAttribute('data-dir-filter');
        $$('[data-dir-filter]').forEach(function (x) { var a = x === b; x.classList.toggle('is-on', a); x.setAttribute('aria-pressed', a ? 'true' : 'false'); });
        $$('.dir-card', list).forEach(function (el) { el.hidden = k !== 'all' && el.getAttribute('data-country') !== k; });
      });
    });
  })();

  /* ---------- reading progress (article pages) ---------- */
  (function () {
    var bar = $('[data-read-progress]'), art = $('.article .prose'); if (!bar || !art) return;
    function upd() {
      var r = art.getBoundingClientRect(), total = r.height - w.innerHeight * 0.6;
      var p = total > 0 ? Math.min(1, Math.max(0, (-r.top + w.innerHeight * 0.2) / total)) : 0;
      bar.style.transform = 'scaleX(' + p.toFixed(3) + ')';
    }
    on(w, 'scroll', upd, { passive: true }); on(w, 'resize', upd); upd();
  })();

  /* ---------- copy link ---------- */
  $$('[data-copy-link]').forEach(function (b) {
    on(b, 'click', function () {
      var url = w.location.href, ok = function () {
        if (b.hasAttribute('data-icon-only')) {   // icon buttons keep their glyph: flag the state instead of swapping the content
          var t0 = b.title; b.classList.add('is-copied'); b.title = b.getAttribute('data-ok') || MSG.copied;
          w.setTimeout(function () { b.classList.remove('is-copied'); b.title = t0; }, 1800); return;
        }
        var o = b.innerHTML; b.textContent = b.getAttribute('data-ok') || MSG.copied; w.setTimeout(function () { b.innerHTML = o; }, 1800);
      };
      if (navigator.clipboard && w.isSecureContext) { navigator.clipboard.writeText(url).then(ok, ok); }
      else { var t = d.createElement('textarea'); t.value = url; d.body.appendChild(t); t.select(); try { d.execCommand('copy'); } catch (e) { /* ignore */ } d.body.removeChild(t); ok(); }
    });
  });

  /* ---------- native share sheet (only where the browser offers one) ---------- */
  if (navigator.share) {
    $$('[data-native-share]').forEach(function (b) {
      b.hidden = false;
      on(b, 'click', function () { navigator.share({ title: b.getAttribute('data-title') || d.title, url: w.location.href }).catch(function () { /* dismissed */ }); });
    });
  }

  /* ---------- password toggle ---------- */
  $$('[data-pw]').forEach(function (b) {
    on(b, 'click', function () { var inp = b.parentElement.querySelector('input'); var show = inp.type === 'password'; inp.type = show ? 'text' : 'password'; b.setAttribute('aria-pressed', show ? 'true' : 'false'); });
  });

  /* ---------- forms ---------- */
  function setErr(input, msg) {
    var err = d.getElementById(input.id + '-err');
    if (msg) { input.setAttribute('aria-invalid', 'true'); if (err) { err.textContent = msg; err.hidden = false; } }
    else { input.removeAttribute('aria-invalid'); if (err) { err.hidden = true; err.textContent = ''; } }
  }
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  function validateField(f) {
    var v = (f.type === 'checkbox') ? f.checked : f.value.trim();
    if (f.required && !v) { setErr(f, f.type === 'checkbox' ? MSG.consent : MSG.required); return false; }
    if (v && f.type === 'email' && !EMAIL.test(v)) { setErr(f, MSG.email); return false; }
    if (v && f.type === 'tel' && !/^[+()\d][\d\s().-]{5,}$/.test(v)) { setErr(f, MSG.tel); return false; }
    setErr(f, ''); return true;
  }
  var contact = $('[data-contact]');
  if (contact) {
    var fields = $$('input:not([type=hidden]):not(.hp input),textarea', contact).filter(function (f) { return f.name !== 'website'; });
    fields.forEach(function (f) { on(f, 'blur', function () { if (f.value || f.getAttribute('aria-invalid')) validateField(f); }); on(f, 'input', function () { if (f.getAttribute('aria-invalid')) validateField(f); }); });
    on(contact, 'submit', function (e) {
      e.preventDefault();
      var summary = $('.form-summary', contact), ok = $('.form-ok', contact), btn = $('button[type=submit]', contact);
      var bad = fields.filter(function (f) { return !validateField(f); });
      if (bad.length) { summary.hidden = false; bad[0].focus(); return; }
      summary.hidden = true;
      var hp = contact.elements.website;
      btn.classList.add('is-busy'); var label = btn.textContent; btn.textContent = MSG.sending;
      w.setTimeout(function () {   // simulated submit: nothing leaves the browser
        if (!hp || !hp.value) {
          try {
            var box = JSON.parse(store('agp-contact') || '[]');
            box.push({ at: new Date().toISOString(), lang: LANG, firstName: contact.elements.fname.value, lastName: contact.elements.lname.value, email: contact.elements.email.value, message: contact.elements.message.value });
            store('agp-contact', JSON.stringify(box.slice(-20)));
          } catch (x) { /* ignore */ }
        }
        contact.reset(); btn.classList.remove('is-busy'); btn.textContent = label; ok.hidden = false; ok.focus();
      }, 900);
    });
  }
  $$('[data-newsletter]').forEach(function (f) {
    on(f, 'submit', function (e) {
      e.preventDefault();
      var em = f.elements.email, ok = $('.form-ok', f);
      if (!EMAIL.test(em.value.trim())) { setErr(em, MSG.email); em.focus(); return; }
      setErr(em, '');
      var cons = f.elements.consent, cerr = cons && d.getElementById(cons.id + '-err');   // in-article box only: explicit opt-in (GDPR)
      if (cons) { if (!cons.checked) { if (cerr) cerr.hidden = false; cons.focus(); return; } if (cerr) cerr.hidden = true; }
      if (!f.elements.website.value) { try { var box = JSON.parse(store('agp-newsletter') || '[]'); box.push({ at: new Date().toISOString(), lang: LANG, email: em.value.trim(), consent: cons ? new Date().toISOString() : undefined }); store('agp-newsletter', JSON.stringify(box.slice(-50))); } catch (x) { /* ignore */ } }
      ok.hidden = false; em.value = ''; if (cons) cons.checked = false;
    });
  });
  $$('[data-comment]').forEach(function (f) {   // prototype: queued locally, nothing leaves the browser; the real site moderates server-side
    on(f, 'submit', function (e) {
      e.preventDefault();
      var el = f.elements, err = $('.form-err', f), ok = $('.form-ok', f);
      var bad = [el.comment, el.author, el.email].filter(function (x) { return !x.value.trim(); });
      if (el.email.value.trim() && !EMAIL.test(el.email.value.trim())) bad.push(el.email);
      [el.comment, el.author, el.email].forEach(function (x) { if (bad.indexOf(x) < 0) x.removeAttribute('aria-invalid'); else x.setAttribute('aria-invalid', 'true'); });
      if (bad.length || !el.consent.checked) { err.hidden = false; ok.hidden = true; (bad[0] || el.consent).focus(); return; }
      err.hidden = true;
      if (!el.website.value) {
        try {
          var box = JSON.parse(store('agp-comments') || '[]');
          box.push({ at: new Date().toISOString(), lang: LANG, post: f.getAttribute('data-post'), postTitle: f.getAttribute('data-title'), author: el.author.value.trim(), email: el.email.value.trim(), text: el.comment.value.trim(), consent: new Date().toISOString(), status: 'pending' });
          store('agp-comments', JSON.stringify(box.slice(-50)));
        } catch (x) { /* ignore */ }
      }
      f.reset(); ok.hidden = false;
    });
  });
  $$('[data-login]').forEach(function (f) {
    on(f, 'submit', function (e) {
      e.preventDefault();
      var u = f.elements.log.value.trim(), p = f.elements.pwd.value;
      var err = $('.form-err', f), note = $('.form-note', f);
      if (!u || !p) { err.hidden = false; note.hidden = true; return; }
      err.hidden = true; note.hidden = false; f.elements.pwd.value = '';
    });
  });

  /* ---------- cookie consent (blocks third-party scripts until accepted) ---------- */
  var banner = $('#cookie-banner'), CKEY = 'agp-consent';
  function readConsent() { try { return JSON.parse(store(CKEY) || 'null'); } catch (e) { return null; } }
  function activateScripts(c) {
    $$('script[type="text/plain"][data-consent-cat]').forEach(function (s) {
      if (s.getAttribute('data-done') || !c[s.getAttribute('data-consent-cat')]) return;
      s.setAttribute('data-done', '1');
      var src = s.getAttribute('data-src');
      if (src) { var n = d.createElement('script'); n.src = src; n.async = true; d.head.appendChild(n); }
    });
    d.dispatchEvent(new CustomEvent('agp:consent', { detail: c }));
  }
  function saveConsent(c) {
    c.v = 1; c.ts = new Date().toISOString(); store(CKEY, JSON.stringify(c));
    if (banner) banner.hidden = true;
    activateScripts(c);
  }
  function showCookie(force) {
    if (!banner) return;
    var c = readConsent();
    if (c && !force) return;
    if (c) { var a = $('[name=analytics]', banner), s = $('[name=social]', banner); if (a) a.checked = !!c.analytics; if (s) s.checked = !!c.social; }
    banner.hidden = false;
    if (force) { var b = $('[data-consent="all"]', banner); if (b) b.focus(); }
  }
  if (banner) {
    var c0 = readConsent();
    if (c0) activateScripts(c0); else w.setTimeout(function () { showCookie(false); }, 600);
    $$('[data-consent]', banner).forEach(function (b) {
      on(b, 'click', function () { var all = b.getAttribute('data-consent') === 'all'; saveConsent({ analytics: all, social: all }); });
    });
    var custom = $('[data-consent-custom]', banner), prefs = $('#ck-prefs', banner);
    on(custom, 'click', function () { var o = prefs.hidden; prefs.hidden = !o; custom.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    on(prefs, 'submit', function (e) { e.preventDefault(); saveConsent({ analytics: prefs.elements.analytics.checked, social: prefs.elements.social.checked }); });
  }

  /* ---------- search results page (filter over embedded JSON) ---------- */
  (function () {
    var host = $('[data-search-results]'); if (!host) return;
    var idx = [], msg = {};
    try { idx = JSON.parse($('#search-index').textContent); msg = JSON.parse($('#search-msgs').textContent); } catch (e) { return; }
    var form = $('[data-search-form]'), input = $('#q'), cat = $('[data-search-cat]'), sort = $('[data-search-sort]'), count = $('[data-search-count]'), more = $('[data-search-more]');
    var PAGE = 10, shown = PAGE, results = [];
    var params = new URLSearchParams(w.location.search);
    input.value = params.get('q') || ''; if (params.get('cat')) cat.value = params.get('cat');
    function score(it, toks) {
      var t = norm(it.t), e = norm(it.e), c = norm(it.c.join(' ')), s = 0;
      for (var k = 0; k < toks.length; k++) {
        var tk = toks[k], hit = false;
        if (t.indexOf(tk) > -1) { s += 5 + (t.indexOf(tk) === 0 ? 2 : 0); hit = true; }
        if (c.indexOf(tk) > -1) { s += 3; hit = true; }
        if (e.indexOf(tk) > -1) { s += 1; hit = true; }
        if (!hit) return 0;
      }
      return s;
    }
    function hl(text, toks) {
      var frag = d.createDocumentFragment(), n = norm(text), pos = 0, ranges = [];
      toks.forEach(function (tk) { var p = 0, j; while (tk && (j = n.indexOf(tk, p)) > -1) { ranges.push([j, j + tk.length]); p = j + tk.length; } });
      ranges.sort(function (a, b) { return a[0] - b[0]; });
      var merged = []; ranges.forEach(function (r) { var l = merged[merged.length - 1]; if (l && r[0] <= l[1]) l[1] = Math.max(l[1], r[1]); else merged.push(r.slice()); });
      merged.forEach(function (r) {
        if (r[0] > pos) frag.appendChild(d.createTextNode(text.slice(pos, r[0])));
        var m = d.createElement('mark'); m.textContent = text.slice(r[0], r[1]); frag.appendChild(m); pos = r[1];
      });
      if (pos < text.length) frag.appendChild(d.createTextNode(text.slice(pos)));
      return frag;
    }
    function el(tag, cls, text) { var x = d.createElement(tag); if (cls) x.className = cls; if (text) x.textContent = text; return x; }
    function render() {
      var q = input.value.trim(), toks = norm(q).split(/\s+/).filter(Boolean), ck = cat.value;
      results = idx.map(function (it) { return { it: it, s: toks.length ? score(it, toks) : 1 }; })
        .filter(function (r) { return r.s > 0 && (!ck || r.it.k.indexOf(ck) > -1); });
      if (sort.value === 'date' || !toks.length) results.sort(function (a, b) { return a.it.d < b.it.d ? 1 : -1; });
      else results.sort(function (a, b) { return b.s - a.s || (a.it.d < b.it.d ? 1 : -1); });
      host.textContent = '';
      var list = results.slice(0, shown);
      count.textContent = (q ? msg['for'] + ' « ' + q + ' » — ' : '') + msg.count.replace('{n}', results.length);
      if (!results.length) { host.appendChild(el('p', 'empty', q ? msg.none : msg.prompt)); more.hidden = true; return; }
      list.forEach(function (r) {
        var it = r.it, art = el('article', 'row-item is-in');
        var a1 = el('a', 'thumb thumb-row'); a1.href = it.u; a1.tabIndex = -1; a1.setAttribute('aria-hidden', 'true');
        if (it.i) { var im = d.createElement('img'); im.src = it.i; im.width = 420; im.height = 280; im.loading = 'lazy'; im.decoding = 'async'; im.alt = msg.alt + it.t; im.title = it.t; a1.appendChild(im); }
        else { a1.appendChild(el('span', 'img-ph')); }
        var box = d.createElement('div');
        var tm = el('time', '', it.df); tm.setAttribute('datetime', it.d);
        var h = el('h2', 'row-title'), ha = d.createElement('a'); ha.href = it.u; ha.appendChild(hl(it.t, toks)); h.appendChild(ha);
        var p = el('p', 'excerpt'); p.appendChild(hl(it.e, toks));
        var chips = el('div', 'chips'); it.c.forEach(function (c) { chips.appendChild(el('span', 'chip', c)); });
        box.appendChild(tm); box.appendChild(h); box.appendChild(p); box.appendChild(chips);
        art.appendChild(a1); art.appendChild(box); host.appendChild(art);
      });
      more.hidden = results.length <= shown;
    }
    function sync() {
      try { var u = new URL(w.location.href); if (input.value.trim()) u.searchParams.set('q', input.value.trim()); else u.searchParams.delete('q'); if (cat.value) u.searchParams.set('cat', cat.value); else u.searchParams.delete('cat'); w.history.replaceState(null, '', u.toString()); } catch (e) { /* file:// may refuse */ }
    }
    on(form, 'submit', function (e) { e.preventDefault(); shown = PAGE; sync(); render(); });
    on(input, 'input', function () { shown = PAGE; render(); });
    on(cat, 'change', function () { shown = PAGE; sync(); render(); });
    on(sort, 'change', function () { shown = PAGE; render(); });
    on(more, 'click', function () { shown += PAGE; render(); });
    render();
  })();

  /* ---------- service worker (http/https only) ---------- */
  if ('serviceWorker' in navigator && /^https?:$/.test(w.location.protocol) && !w.__agpSw) {
    w.__agpSw = true;
    w.addEventListener('load', function () { navigator.serviceWorker.register(d.body.getAttribute('data-sw')).catch(function () { /* optional */ }); });
  }

  /* ---------- Turbo Drive: after a page swap, move focus to <main> so keyboard and screen-reader users land on the new content ---------- */
  if (!w.__agpTurbo) { w.__agpTurbo = true; d.addEventListener('turbo:before-visit', function () { w.__agpNav = true; }); }
  if (w.__agpNav) { w.__agpNav = false; var mainEl = $('#main'); if (mainEl) mainEl.focus({ preventScroll: true }); }
})();
