/* Post-format builders and the small WYSIWYG editor.
   SWAP POINT: AGP.rte() is a minimal contentEditable editor. To use TinyMCE or CKEditor 5 later, keep the same contract:
   AGP.rte({value,label,onInput}) -> { el, getHTML(), setHTML(html), insertHTML(html), getSelectionText(), focus() }.
   Always run output through the server-side sanitizer as well (AGP.sanitize here mirrors it for the mock). */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $ = AGP.$, $$ = AGP.$$;

  /* ---------- fields ---------- */
  function fld(label, ctl, hint, req) {
    var id = AGP.uid('f'); ctl.id = id; if (req) ctl.setAttribute('aria-required', 'true');
    return h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: id, text: label + (req ? ' *' : '') }), ctl, hint ? h('div', { class: 'a-hint', text: hint }) : null);
  }
  function bind(ctl, data, key, changed, num) {
    ctl.value = data[key] == null ? '' : data[key];
    ctl.addEventListener('input', function () { data[key] = num && ctl.value !== '' ? +ctl.value : ctl.value; changed(); });
    return ctl;
  }
  function inp(data, key, changed, o) { o = o || {}; var i = h('input', { class: 'a-input', type: o.type || 'text', placeholder: o.ph, min: o.min, step: o.step, autocomplete: 'off' }); return bind(i, data, key, changed, o.num); }
  function sel(data, key, changed, opts) { var s = h('select', { class: 'a-select' }, opts.map(function (o) { return h('option', { value: o[0], text: o[1] }); })); return bind(s, data, key, changed); }
  function area(data, key, changed, rows) { var t = h('textarea', { class: 'a-textarea', rows: rows || 4 }); return bind(t, data, key, changed); }
  function chk(label, data, key, changed) { var c = h('input', { type: 'checkbox', checked: !!data[key] }); c.addEventListener('change', function () { data[key] = c.checked; changed(); }); return h('label', { class: 'a-check mb-2' }, c, h('span', { text: label })); }

  /* ---------- WYSIWYG ---------- */
  AGP.rte = function (o) {
    var uid = AGP.uid('r'), saved = null;
    var area = h('div', { class: 'a-rte-area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': o.label || 'Content', tabindex: '0', spellcheck: 'true' });
    area.innerHTML = AGP.sanitize(o.value || '');
    var count = h('span', { class: 'a-counter', 'aria-live': 'off' });
    function upd() { var n = (area.textContent.trim().match(/\S+/g) || []).length; count.textContent = n + ' words • ~' + Math.max(1, Math.round(n / 220)) + ' min read'; }
    function saveSel() { var s = getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); }
    function restore() { area.focus(); if (saved) { var s = getSelection(); s.removeAllRanges(); s.addRange(saved); } }
    function cmd(c, v) { restore(); document.execCommand(c, false, v || null); fire(); }
    function fire() { upd(); if (o.onInput) o.onInput(); }
    function insertNode(n) { restore(); var s = getSelection(); if (!s.rangeCount) { area.appendChild(n); } else { var r = s.getRangeAt(0); r.deleteContents(); r.insertNode(n); r.setStartAfter(n); r.collapse(true); s.removeAllRanges(); s.addRange(r); } fire(); }
    var B = [
      ['bold', 'bold', 'Bold', function () { cmd('bold'); }, 'bold'], ['italic', 'italic', 'Italic', function () { cmd('italic'); }, 'italic'],
      ['h2', 'heading', 'Heading 2', function () { cmd('formatBlock', 'H2'); }], ['h3', null, 'Heading 3', function () { cmd('formatBlock', 'H3'); }],
      ['link', 'link', 'Insert link', function () {
        saveSel();
        AGP.formModal({ title: 'Insert link', submit: 'Insert', fields: [{ name: 'url', label: 'URL', type: 'url', required: true, placeholder: 'https://', validate: function (v) { return /^(https?:\/\/|mailto:|\/|#)/i.test(v) ? '' : 'Use http(s)://, mailto:, or a relative link.'; } }], onSubmit: function (v) { cmd('createLink', v.url); } });
      }],
      ['ul', 'list', 'Bullet list', function () { cmd('insertUnorderedList'); }], ['ol', null, 'Numbered list', function () { cmd('insertOrderedList'); }],
      ['quote', 'quote', 'Quote', function () { cmd('formatBlock', 'BLOCKQUOTE'); }],
      ['image', 'image', 'Insert image from library', function () {
        saveSel();
        AGP.pickMedia({ kind: 'image', title: 'Insert image' }).then(function (items) {
          items.forEach(function (m) { var src = m.src || AGP.blobs[m.id]; if (!src) { AGP.toast('This seeded item has no file in the prototype. Upload an image.', 'warn'); return; } insertNode(h('img', { src: src, alt: m.alt })); });
        });
      }],
      ['clear', null, 'Clear formatting', function () { cmd('removeFormat'); cmd('formatBlock', 'P'); }]
    ];
    var bar = h('div', { class: 'a-rte-bar', role: 'toolbar', 'aria-label': 'Text formatting', 'aria-controls': uid });
    var btns = B.map(function (b, i) {
      var el = h('button', { type: 'button', title: b[2], 'aria-label': b[2], tabindex: i ? '-1' : '0', 'data-c': b[4] || '' }, b[1] ? AGP.icon(b[1]) : h('span', { 'aria-hidden': 'true', text: b[0] === 'h2' ? 'H2' : b[0] === 'h3' ? 'H3' : b[0] === 'ol' ? '1.' : 'Tx' }));
      if (b[4]) el.setAttribute('aria-pressed', 'false');
      el.addEventListener('mousedown', function (e) { e.preventDefault(); saveSel(); });
      el.addEventListener('click', function () { b[3](); });
      bar.appendChild(el); return el;
    });
    bar.addEventListener('keydown', function (e) {
      var i = btns.indexOf(document.activeElement); if (i < 0) return;
      var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? btns.length - 1 : null;
      if (n === null) return; e.preventDefault(); n = (n + btns.length) % btns.length; btns.forEach(function (b) { b.tabIndex = -1; }); btns[n].tabIndex = 0; btns[n].focus();
    });
    area.id = uid;
    area.addEventListener('input', fire); area.addEventListener('keyup', saveSel); area.addEventListener('mouseup', saveSel); area.addEventListener('blur', saveSel);
    area.addEventListener('paste', function (e) {
      e.preventDefault(); var cd = e.clipboardData; if (!cd) return;
      var hh = cd.getData('text/html'), tx = cd.getData('text/plain');
      document.execCommand('insertHTML', false, hh ? AGP.sanitize(hh) : AGP.esc(tx).replace(/\n/g, '<br>')); fire();
    });
    area.addEventListener('drop', function (e) { e.preventDefault(); });
    document.addEventListener('selectionchange', function () {
      if (!area.isConnected || !area.contains(document.activeElement)) return;
      btns.forEach(function (b) { var c = b.getAttribute('data-c'); if (c) try { b.setAttribute('aria-pressed', document.queryCommandState(c) ? 'true' : 'false'); } catch (e) { /* ignore */ } });
    });
    var wrap = h('div', { class: 'a-rte' }, bar, area, h('div', { class: 'px-3 py-2 flex justify-between a-muted', style: null }, count, h('span', { class: 'a-counter', text: 'Swap point: TinyMCE / CKEditor' })));
    upd();
    return {
      el: wrap, area: area,
      getHTML: function () { return AGP.sanitize(area.innerHTML); },
      setHTML: function (x) { area.innerHTML = AGP.sanitize(x); upd(); },
      insertHTML: function (x) { area.insertAdjacentHTML('beforeend', AGP.sanitize(x)); fire(); },
      getSelectionText: function () { return saved ? saved.toString() : ''; },
      focus: function () { area.focus(); }
    };
  };

  /* ---------- player (unified HTML5 / embed preview) ---------- */
  AGP.parseMediaUrl = function (u, kind) {
    u = String(u || '').trim(); if (!u) return null; var m;
    if ((m = u.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/i))) return { type: 'embed', provider: 'YouTube', src: 'https://www.youtube-nocookie.com/embed/' + m[1] };
    if ((m = u.match(/vimeo\.com\/(?:video\/)?(\d+)/i))) return { type: 'embed', provider: 'Vimeo', src: 'https://player.vimeo.com/video/' + m[1] };
    if (/^https?:\/\/(www\.)?soundcloud\.com\/[\w\-./]+/i.test(u)) return { type: 'embed', provider: 'SoundCloud', src: 'https://w.soundcloud.com/player/?url=' + encodeURIComponent(u) };
    if (/^https?:\/\/.+\.(mp4|webm|ogv|mov)(\?.*)?$/i.test(u)) return { type: 'native', el: 'video', provider: 'MP4 file', src: u };
    if (/^https?:\/\/.+\.(mp3|wav|ogg|m4a|aac)(\?.*)?$/i.test(u)) return { type: 'native', el: 'audio', provider: 'Audio file', src: u };
    return { type: 'error' };
  };
  AGP.renderPlayer = function (cfg, kind) {
    var box = h('div', { class: 'a-player ' + (kind === 'audio' ? 'audio' : '') });
    if (!cfg) { box.appendChild(h('div', { class: 'a-empty', style: null, text: 'No source yet. The player preview appears here.' })); box.style.background = 'var(--surface-2)'; return box; }
    if (cfg.type === 'error') { box.appendChild(h('div', { class: 'a-empty', text: 'Unsupported URL. Use YouTube, Vimeo, SoundCloud or a direct .mp4 / .mp3 link.' })); box.style.background = 'var(--surface-2)'; return box; }
    if (cfg.type === 'embed') box.appendChild(h('iframe', { src: cfg.src, title: cfg.provider + ' player', loading: 'lazy', allowfullscreen: true, referrerpolicy: 'no-referrer', sandbox: 'allow-scripts allow-same-origin allow-presentation allow-popups' }));
    else { var m = h(cfg.el || (kind === 'audio' ? 'audio' : 'video'), { src: cfg.src, controls: true, preload: 'metadata', 'aria-label': 'Media preview' }); box.appendChild(m); }
    return box;
  };

  var F = (AGP.fmt = {});

  /* ----- Article ----- */
  F.article = function (c) {
    var d = c.data; d.toc = d.toc || false;
    return { el: h('div', {}, h('div', { class: 'a-grid2' }, fld('Deck (sub-headline)', inp(d, 'deck', c.changed)), fld('Dateline', inp(d, 'dateline', c.changed, { ph: 'Dakar, 19 October 2026' }))),
      h('div', { class: 'a-check-row' }, chk('Show an auto-generated table of contents (from H2/H3)', d, 'toc', c.changed), chk('Allow comments', d, 'comments', c.changed), chk('Mark as sponsored content', d, 'sponsored', c.changed))),
      validate: function () { return []; }, jsonld: function (p) { return { '@type': 'NewsArticle', headline: p.title, inLanguage: p.lang, datePublished: p.date, author: { '@type': 'Person', name: AGP.userName(p.author) }, description: p.seoDesc || p.excerpt }; } };
  };

  /* ----- Recipe ----- */
  F.recipe = function (c) {
    var d = c.data; d.method = d.method || '';
    var rte = AGP.rte({ label: 'Recipe method', value: d.method, onInput: function () { d.method = rte.getHTML(); c.changed(); } });
    var el = h('div', {}, h('div', { class: 'a-grid2' }, fld('Prep time (min)', inp(d, 'prep', c.changed, { type: 'number', min: 0, num: true })), fld('Cook time (min)', inp(d, 'cook', c.changed, { type: 'number', min: 0, num: true })),
      fld('Servings', inp(d, 'servings', c.changed, { type: 'number', min: 1, num: true })), fld('Difficulty', sel(d, 'difficulty', c.changed, [['easy', 'Easy'], ['medium', 'Medium'], ['hard', 'Hard']])),
      fld('Cuisine', inp(d, 'cuisine', c.changed)), fld('Calories (kcal / serving)', inp(d, 'kcal', c.changed, { type: 'number', min: 0, num: true }))),
      fld('Ingredients (one per line)', area(d, 'ingredients', c.changed, 6), 'Exported as recipeIngredient in JSON-LD.', true),
      h('p', { class: 'a-label', text: 'Method (WYSIWYG)' }), rte.el);
    return { el: el, validate: function () { return (d.ingredients || '').trim() ? [] : ['Recipe: add at least one ingredient.']; },
      jsonld: function (p) { return { '@type': 'Recipe', name: p.title, inLanguage: p.lang, author: { '@type': 'Person', name: AGP.userName(p.author) }, prepTime: 'PT' + (d.prep || 0) + 'M', cookTime: 'PT' + (d.cook || 0) + 'M', recipeYield: String(d.servings || ''), recipeCuisine: d.cuisine || undefined, nutrition: d.kcal ? { '@type': 'NutritionInformation', calories: d.kcal + ' kcal' } : undefined, recipeIngredient: (d.ingredients || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean), recipeInstructions: AGP.textOf(d.method).split(/(?<=[.!?])\s+/).filter(Boolean).map(function (t) { return { '@type': 'HowToStep', text: t }; }) }; } };
  };

  /* ----- Video / Audio ----- */
  function mediaBuilder(kind) {
    return function (c) {
      var d = c.data; d.source = d.source || 'url';
      var prev = h('div'), err = h('div', { class: 'a-err', role: 'alert', hidden: true });
      function cfg() { return d.source === 'url' ? AGP.parseMediaUrl(d.url, kind) : (d.fileData || AGP.blobs[d.fid]) ? { type: 'native', el: kind, src: d.fileData || AGP.blobs[d.fid] } : null; }
      function draw() { AGP.mount(prev, AGP.renderPlayer(cfg(), kind)); var k = d.source === 'url' ? AGP.parseMediaUrl(d.url, kind) : null; err.hidden = !(k && k.type === 'error'); err.textContent = err.hidden ? '' : 'Unsupported URL.'; }
      var url = inp(d, 'url', function () { draw(); c.changed(); }, { type: 'url', ph: kind === 'video' ? 'https://www.youtube.com/watch?v=… or https://vimeo.com/… or .mp4' : 'https://soundcloud.com/… or .mp3' });
      var file = h('input', { type: 'file', class: 'a-input', accept: kind + '/*' });
      var fileInfo = h('p', { class: 'a-hint', text: d.fileName ? 'Loaded: ' + d.fileName : 'Small files (up to 1.5 MB) are kept in browser storage; larger ones are previewed for this session only.' });
      file.addEventListener('change', function () {
        var f = file.files[0]; if (!f) return;
        if (f.type.indexOf(kind + '/') !== 0) { AGP.toast('Please choose a ' + kind + ' file.', 'err'); file.value = ''; return; }
        d.fileName = f.name; d.fileSize = f.size; d.fid = AGP.uid('v');
        if (f.size <= 1500000) AGP.readFile(f).then(function (u) { d.fileData = u; fileInfo.textContent = 'Loaded: ' + f.name + ' (' + AGP.fmtBytes(f.size) + ')'; draw(); c.changed(); });
        else { d.fileData = ''; AGP.blobs[d.fid] = URL.createObjectURL(f); fileInfo.textContent = 'Loaded for this session: ' + f.name + ' (' + AGP.fmtBytes(f.size) + '). Too large to persist in browser storage.'; draw(); c.changed(); }
      });
      var urlWrap = fld(kind === 'video' ? 'Video URL' : 'Audio URL', url, 'Supported: YouTube, Vimeo, SoundCloud, direct MP4/WebM/MP3/WAV.');
      var fileWrap = fld('Upload ' + kind + ' file', file); fileWrap.appendChild(fileInfo);
      var tabs = h('div', { class: 'a-tabs', role: 'radiogroup', 'aria-label': 'Source type' });
      [['url', 'URL / embed'], ['file', 'Upload local file']].forEach(function (o) {
        var b = h('button', { type: 'button', class: 'a-tab', role: 'radio', 'aria-checked': String(d.source === o[0]), 'aria-selected': String(d.source === o[0]), text: o[1] });
        b.addEventListener('click', function () { d.source = o[0]; $$('button', tabs).forEach(function (x, i) { var on = x === b; x.setAttribute('aria-checked', String(on)); x.setAttribute('aria-selected', String(on)); }); urlWrap.hidden = o[0] !== 'url'; fileWrap.hidden = o[0] !== 'file'; draw(); c.changed(); });
        tabs.appendChild(b);
      });
      urlWrap.hidden = d.source !== 'url'; fileWrap.hidden = d.source !== 'file';
      draw();
      return { el: h('div', {}, tabs, urlWrap, err, fileWrap, h('div', { class: 'a-grid2' }, fld('Duration (mm:ss)', inp(d, 'duration', c.changed, { ph: '03:45' })), fld('Credit / source', inp(d, 'credit', c.changed))), fld('Transcript (accessibility)', area(d, 'transcript', c.changed, 4), 'Recommended for WCAG 2.2 AA.'), h('p', { class: 'a-label', text: 'Player preview' }), prev),
        validate: function () { var k = cfg(); return !k ? [kind + ': add a URL or upload a file.'] : k.type === 'error' ? [kind + ': the URL is not supported.'] : []; },
        jsonld: function (p) { var k = cfg(); return { '@type': kind === 'video' ? 'VideoObject' : 'AudioObject', name: p.title, description: p.seoDesc || p.excerpt, uploadDate: p.date, duration: d.duration || undefined, embedUrl: k && k.type === 'embed' ? k.src : undefined, contentUrl: k && k.type === 'native' && /^http/.test(k.src) ? k.src : undefined }; } };
    };
  }
  F.video = mediaBuilder('video'); F.audio = mediaBuilder('audio');

  /* ----- Quiz & Poll (dynamic form + live preview) ----- */
  function quizBuilder(kind) {
    return function (c) {
      var d = c.data, isPoll = kind === 'poll'; d.mode = d.mode || 'trivia';
      d.questions = d.questions && d.questions.length ? d.questions : [newQ()];
      if (isPoll) d.questions = d.questions.slice(0, 1);
      function newQ() { return { id: AGP.uid('q'), text: '', explain: '', answers: [{ id: AGP.uid('a'), text: '', correct: true }, { id: AGP.uid('a'), text: '', correct: false }] }; }
      var list = h('div'), prev = h('div', { class: 'a-q', role: 'region', 'aria-label': 'Live preview' }), live = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
      var focusSel = null;
      function drawPrev() {
        AGP.clear(prev); prev.appendChild(h('p', { class: 'a-eyebrow', text: 'Live preview (interactive)' }));
        var score = 0, answered = 0, total = d.questions.length;
        d.questions.forEach(function (q, qi) {
          var box = h('fieldset', { class: 'mt-3', style: null }, h('legend', { class: 'a-serif', text: (isPoll ? '' : (qi + 1) + '. ') + (q.text || '(question text)') }));
          box.style.border = '0'; box.style.padding = '0'; box.style.margin = '12px 0 0';
          q.answers.forEach(function (a, ai) {
            var b = h('button', { type: 'button', class: 'a-opt', text: a.text || '(answer ' + (ai + 1) + ')' });
            b.addEventListener('click', function () {
              if (box.getAttribute('data-done')) return; box.setAttribute('data-done', '1'); answered++;
              if (isPoll) {
                var votes = q.answers.map(function (x, i) { return (x === a ? 1 : 0) + 3 + ((i * 7 + 5) % 9); }), sum = votes.reduce(function (s, v) { return s + v; }, 0);
                $$('.a-opt', box).forEach(function (ob, i) { ob.textContent = q.answers[i].text + ' — ' + Math.round(votes[i] / sum * 100) + '%'; ob.disabled = true; });
                live.textContent = 'Vote recorded. Showing results.';
              } else {
                var ok = !!a.correct; if (ok) score++;
                b.classList.add(ok ? 'right' : 'wrong'); $$('.a-opt', box).forEach(function (ob, i) { if (q.answers[i].correct) ob.classList.add('right'); ob.disabled = true; });
                live.textContent = ok ? 'Correct.' : 'Incorrect. The right answer is highlighted.';
                if (q.explain) box.appendChild(h('p', { class: 'a-hint', text: q.explain }));
                if (answered === total) box.appendChild(h('p', { class: 'a-badge flash', text: 'Score: ' + score + ' / ' + total }));
              }
            });
            box.appendChild(b);
          });
          prev.appendChild(box);
        });
      }
      var pv = AGP.debounce(drawPrev, 120);
      function draw() {
        AGP.clear(list);
        d.questions.forEach(function (q, qi) {
          var qid = AGP.uid('q'), qIn = h('input', { class: 'a-input', value: q.text, id: qid, placeholder: isPoll ? 'Poll question' : 'Question', 'aria-required': 'true' });
          qIn.addEventListener('input', function () { q.text = qIn.value; pv(); c.changed(); });
          var ans = h('div', { role: isPoll ? null : 'radiogroup', 'aria-label': isPoll ? null : 'Answers for question ' + (qi + 1) + ': mark the correct one' });
          q.answers.forEach(function (a, ai) {
            var tid = AGP.uid('a'), ti = h('input', { class: 'a-input', value: a.text, id: tid, 'aria-label': 'Answer ' + (ai + 1) + ' of question ' + (qi + 1), placeholder: 'Answer ' + (ai + 1) });
            ti.addEventListener('input', function () { a.text = ti.value; pv(); c.changed(); });
            var row = h('div', { class: 'a-ans' });
            if (!isPoll) {
              var r = h('input', { type: 'radio', name: 'cor' + q.id, checked: !!a.correct, 'aria-label': 'Mark answer ' + (ai + 1) + ' as correct' });
              r.style.width = '20px'; r.style.height = '20px'; r.addEventListener('change', function () { q.answers.forEach(function (x) { x.correct = x === a; }); pv(); c.changed(); });
              row.appendChild(r);
            }
            row.appendChild(ti);
            row.appendChild(h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Remove answer ' + (ai + 1), disabled: q.answers.length <= 2, on: { click: function () { var wasC = a.correct; q.answers.splice(ai, 1); if (wasC && !isPoll) q.answers[0].correct = true; focusSel = '[data-q="' + qi + '"] .add-ans'; draw(); pv(); c.changed(); } } }, AGP.icon('x')));
            ans.appendChild(row);
          });
          var ex = isPoll ? null : fld('Explanation (shown after answering)', bind(h('input', { class: 'a-input' }), q, 'explain', function () { pv(); c.changed(); }));
          var card = h('div', { class: 'a-q', 'data-q': String(qi) },
            h('div', { class: 'flex justify-between items-center mb-2' }, h('label', { class: 'a-label', for: qid, text: isPoll ? 'Poll question *' : 'Question ' + (qi + 1) + ' *' }),
              isPoll ? null : h('div', {}, h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Move question ' + (qi + 1) + ' up', disabled: qi === 0, on: { click: function () { d.questions.splice(qi - 1, 0, d.questions.splice(qi, 1)[0]); draw(); pv(); c.changed(); } } }, AGP.icon('up')),
                h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Move question ' + (qi + 1) + ' down', disabled: qi === d.questions.length - 1, on: { click: function () { d.questions.splice(qi + 1, 0, d.questions.splice(qi, 1)[0]); draw(); pv(); c.changed(); } } }, AGP.icon('down')),
                h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Remove question ' + (qi + 1), disabled: d.questions.length <= 1, on: { click: function () { d.questions.splice(qi, 1); draw(); pv(); c.changed(); AGP.toast('Question removed.', 'warn'); } } }, AGP.icon('trash')))),
            qIn, h('p', { class: 'a-label mt-3', text: isPoll ? 'Options' : 'Answers (select the correct one)' }), ans, ex,
            h('button', { type: 'button', class: 'a-btn a-btn-sm mt-3 add-ans', on: { click: function () { var na = { id: AGP.uid('a'), text: '', correct: false }; q.answers.push(na); draw(); pv(); c.changed(); var ins = $$('[data-q="' + qi + '"] .a-ans input.a-input'); ins[ins.length - 1].focus(); } } }, AGP.icon('plus'), 'Add answer'));
          list.appendChild(card);
        });
        if (!isPoll) list.appendChild(h('button', { type: 'button', class: 'a-btn a-btn-primary a-btn-sm', on: { click: function () { d.questions.push(newQ()); draw(); pv(); c.changed(); var qs = $$('.a-q[data-q] input.a-input'); var last = $$('[data-q="' + (d.questions.length - 1) + '"] input.a-input')[0]; last && last.focus(); } } }, AGP.icon('plus'), 'Add question'));
        if (focusSel) { var f = $(focusSel); f && f.focus(); focusSel = null; }
      }
      var opts = isPoll ? h('div', {}, chk('Allow multiple choices', d, 'multi', c.changed), chk('Show results after voting', d, 'showResults', c.changed), fld('Close poll on', inp(d, 'closes', c.changed, { type: 'date' }))) : h('div', {}, fld('Quiz type', sel(d, 'mode', c.changed, [['trivia', 'Trivia quiz (scored)']])), chk('Show correct answers at the end', d, 'reveal', c.changed));
      draw(); drawPrev();
      return { el: h('div', { class: 'a-grid2' }, h('div', { style: null }, opts, list), h('div', {}, prev, live)),
        validate: function () {
          var m = []; d.questions.forEach(function (q, i) {
            if (!q.text.trim()) m.push(kind + ': question ' + (i + 1) + ' needs text.');
            var f = q.answers.filter(function (a) { return a.text.trim(); }); if (f.length < 2) m.push(kind + ': question ' + (i + 1) + ' needs at least 2 filled answers.');
            if (!isPoll && !q.answers.some(function (a) { return a.correct && a.text.trim(); })) m.push('Quiz: question ' + (i + 1) + ' needs a correct answer.');
          }); return m;
        },
        jsonld: function (p) {
          if (isPoll) return { '@type': 'Article', headline: p.title, about: d.questions[0].text };
          return { '@type': 'Quiz', name: p.title, hasPart: d.questions.map(function (q) { return { '@type': 'Question', name: q.text, eduQuestionType: 'Multiple choice', suggestedAnswer: q.answers.filter(function (a) { return !a.correct; }).map(function (a) { return { '@type': 'Answer', text: a.text }; }), acceptedAnswer: q.answers.filter(function (a) { return a.correct; }).map(function (a) { return { '@type': 'Answer', text: a.text }; }) }; }) };
        } };
    };
  }
  F.quiz = quizBuilder('quiz'); F.poll = quizBuilder('poll');

  /* ----- Gallery ----- */
  F.gallery = function (c) {
    var d = c.data; d.items = d.items || []; d.layout = d.layout || 'grid';
    var list = h('div');
    function find(id) { return AGP.media().filter(function (m) { return m.id === id; })[0]; }
    function draw() {
      AGP.clear(list);
      if (!d.items.length) list.appendChild(h('div', { class: 'a-empty', text: 'No images yet. Add from the media library.' }));
      d.items.forEach(function (it, i) {
        var m = find(it.mid), pv = h('div', { class: 'a-thumb', style: null }, m ? AGP.mediaPreview(m) : h('span', { text: '?' })); pv.style.background = AGP.tint(it.mid); pv.style.width = '84px'; pv.style.height = '64px';
        var alt = h('input', { class: 'a-input', value: it.alt || '', 'aria-label': 'Alt text for image ' + (i + 1), placeholder: 'Alt text (required)', 'aria-required': 'true' }); alt.addEventListener('input', function () { it.alt = alt.value; c.changed(); });
        var cap = h('input', { class: 'a-input', value: it.caption || '', 'aria-label': 'Caption for image ' + (i + 1), placeholder: 'Caption' }); cap.addEventListener('input', function () { it.caption = cap.value; c.changed(); });
        list.appendChild(h('div', { class: 'a-q flex gap-3 items-start' }, pv, h('div', { class: 'flex-1 a-stack' }, alt, cap),
          h('div', { class: 'flex flex-col' }, h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Move image ' + (i + 1) + ' up', disabled: i === 0, on: { click: function () { d.items.splice(i - 1, 0, d.items.splice(i, 1)[0]); draw(); c.changed(); } } }, AGP.icon('up')),
            h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Move image ' + (i + 1) + ' down', disabled: i === d.items.length - 1, on: { click: function () { d.items.splice(i + 1, 0, d.items.splice(i, 1)[0]); draw(); c.changed(); } } }, AGP.icon('down')),
            h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Remove image ' + (i + 1), on: { click: function () { d.items.splice(i, 1); draw(); c.changed(); } } }, AGP.icon('trash')))));
      });
    }
    draw();
    return { el: h('div', {}, fld('Layout', sel(d, 'layout', c.changed, [['grid', 'Grid'], ['slider', 'Slider'], ['masonry', 'Masonry']])), list,
      AGP.btn('Add images', { icon: 'image', cls: 'a-btn-primary a-btn-sm', on: { click: function () { AGP.pickMedia({ kind: 'image', multi: true, title: 'Add gallery images' }).then(function (items) { items.forEach(function (m) { d.items.push({ mid: m.id, alt: m.alt, caption: '' }); }); draw(); c.changed(); }); } } })),
      validate: function () { var m = []; if (!d.items.length) m.push('Gallery: add at least one image.'); d.items.forEach(function (it, i) { if (!(it.alt || '').trim()) m.push('Gallery: image ' + (i + 1) + ' needs alt text.'); }); return m; },
      jsonld: function (p) { return { '@type': 'ImageGallery', name: p.title, image: d.items.map(function (it) { var m = find(it.mid); return { '@type': 'ImageObject', name: m ? m.name : '', caption: it.caption || undefined, description: it.alt }; }) }; } };
  };

  /* ----- Event ----- */
  F.event = function (c) {
    var d = c.data; d.mode = d.mode || 'offline'; d.status = d.status || 'scheduled';
    return { el: h('div', { class: 'a-grid2' }, fld('Starts', inp(d, 'start', c.changed, { type: 'datetime-local' }), '', true), fld('Ends', inp(d, 'end', c.changed, { type: 'datetime-local' })),
      fld('Attendance', sel(d, 'mode', c.changed, [['offline', 'In person'], ['online', 'Online'], ['mixed', 'Hybrid']])), fld('Status', sel(d, 'status', c.changed, [['scheduled', 'Scheduled'], ['postponed', 'Postponed'], ['cancelled', 'Cancelled']])),
      fld('Venue', inp(d, 'venue', c.changed)), fld('City / country', inp(d, 'city', c.changed)), fld('Address', inp(d, 'address', c.changed)), fld('Online URL', inp(d, 'online', c.changed, { type: 'url' })),
      fld('Ticket URL', inp(d, 'tickets', c.changed, { type: 'url' })), fld('Price (0 = free)', inp(d, 'price', c.changed, { type: 'number', min: 0, num: true })), fld('Organizer', inp(d, 'organizer', c.changed)), fld('Capacity', inp(d, 'capacity', c.changed, { type: 'number', min: 0, num: true }))),
      validate: function () { var m = []; if (!d.start) m.push('Event: start date is required.'); if (d.start && d.end && d.end < d.start) m.push('Event: end must be after start.'); return m; },
      jsonld: function (p) { return { '@type': 'Event', name: p.title, startDate: d.start || undefined, endDate: d.end || undefined, eventStatus: 'https://schema.org/Event' + (d.status === 'cancelled' ? 'Cancelled' : d.status === 'postponed' ? 'Postponed' : 'Scheduled'), eventAttendanceMode: 'https://schema.org/' + (d.mode === 'online' ? 'Online' : d.mode === 'mixed' ? 'Mixed' : 'Offline') + 'EventAttendanceMode', location: d.mode === 'online' ? { '@type': 'VirtualLocation', url: d.online } : { '@type': 'Place', name: d.venue, address: d.address || d.city }, organizer: d.organizer ? { '@type': 'Organization', name: d.organizer } : undefined, offers: d.tickets ? { '@type': 'Offer', url: d.tickets, price: d.price || 0, priceCurrency: 'EUR' } : undefined }; } };
  };
})();
