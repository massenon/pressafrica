/* Post editor: formats, workflow, scheduling, FAQ, SEO panel + JSON-LD, categories, tags, featured image, mock AI writer. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $ = AGP.$, $$ = AGP.$$;

  function blank(me) {
    return { id: AGP.uid('p'), title: '', slug: '', status: 'draft', lang: 'fr', format: 'article', categoryIds: [], author: me.id, date: AGP.nowLocal(), modified: AGP.nowLocal(), excerpt: '', content: '', image: { mid: '', alt: '', path: '' }, tags: [], seoTitle: '', seoDesc: '', views: 0, comments: 0, faq: [], fd: {}, featured: false, noindex: false, canonical: '' };
  }

  AGP.page('post-edit', function (root) {
    var me = AGP.user(), P = AGP.params(), id = P.get('id');
    var existing = id && AGP.posts().filter(function (p) { return p.id === id; })[0];
    var isNew = !existing;
    if (isNew && !AGP.can('posts', 'create')) return AGP.denied(root);
    if (!isNew && !AGP.can('posts', 'edit')) return AGP.denied(root);
    if (!isNew && AGP.role().id === 'author' && existing.author !== me.id) return AGP.denied(root);
    var S = existing ? JSON.parse(JSON.stringify(existing)) : blank(me);
    S.fd = S.fd || {}; S.faq = S.faq || []; S.image = S.image || { mid: '', alt: '' }; S.tags = S.tags || [];
    var canPub = AGP.can('posts', 'publish'), dirty = false, slugTouched = !isNew, cur = null;
    function changed() { dirty = true; seoRefresh(); }
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    /* ----- title + slug ----- */
    var title = h('input', { class: 'a-input a-title-in', value: S.title, placeholder: 'Post title', 'aria-label': 'Post title', 'aria-required': 'true', maxlength: '180' });
    var slug = h('input', { class: 'a-input', value: S.slug, 'aria-label': 'Slug' });
    var permalink = h('span', { class: 'a-mono' });
    title.addEventListener('input', function () { S.title = title.value; if (!slugTouched) { S.slug = AGP.slugify(S.title); slug.value = S.slug; } changed(); });
    slug.addEventListener('input', function () { slugTouched = true; S.slug = AGP.slugify(slug.value); changed(); });

    /* ----- body ----- */
    var bodyLabel = h('h2', { class: 'text-xl', text: 'Content' });
    var rte = AGP.rte({ label: 'Post content', value: S.content, onInput: function () { S.content = rte.getHTML(); changed(); } });
    var excerpt = h('textarea', { class: 'a-textarea', rows: 2, 'aria-label': 'Excerpt' }); excerpt.value = S.excerpt;
    excerpt.addEventListener('input', function () { S.excerpt = excerpt.value; changed(); });

    /* ----- formats ----- */
    var fmtPanel = h('div'), fmtTitle = h('h2', { class: 'text-xl' });
    var fmtDefs = [['article', 'Article', 'file'], ['recipe', 'Recipe', 'utensils'], ['video', 'Video', 'video'], ['audio', 'Audio', 'mic'], ['quiz', 'Quiz', 'quiz'], ['poll', 'Poll', 'chart'], ['gallery', 'Gallery', 'layers'], ['event', 'Event', 'cal']];
    function setFormat(k) {
      S.format = k; S.fd[k] = S.fd[k] || {};
      cur = AGP.fmt[k]({ data: S.fd[k], post: S, changed: changed });
      AGP.mount(fmtPanel, cur.el);
      var def = fmtDefs.filter(function (f) { return f[0] === k; })[0];
      fmtTitle.textContent = def[1] + ' settings';
      bodyLabel.textContent = k === 'recipe' ? 'Introduction' : k === 'article' ? 'Content' : 'Story / description';
      seoRefresh();
    }
    var fmtSwitch = h('fieldset', { class: 'a-formats' }, h('legend', { class: 'a-vh', text: 'Post format' }));
    fmtDefs.forEach(function (f) {
      var r = h('input', { type: 'radio', name: 'fmt', value: f[0], checked: S.format === f[0] });
      r.addEventListener('change', function () { setFormat(f[0]); dirty = true; });
      fmtSwitch.appendChild(h('label', {}, r, h('span', {}, AGP.icon(f[2]), f[1])));
    });

    /* ----- FAQ ----- */
    var faqList = h('div');
    function drawFaq() {
      AGP.clear(faqList);
      if (!S.faq.length) faqList.appendChild(h('p', { class: 'a-muted', text: 'No FAQ yet. Questions are output as FAQPage JSON-LD.' }));
      S.faq.forEach(function (f, i) {
        var q = h('input', { class: 'a-input', value: f.q, 'aria-label': 'FAQ question ' + (i + 1), placeholder: 'Question' }), a = h('textarea', { class: 'a-textarea', rows: 2, 'aria-label': 'FAQ answer ' + (i + 1), placeholder: 'Answer' }); a.value = f.a;
        q.addEventListener('input', function () { f.q = q.value; changed(); }); a.addEventListener('input', function () { f.a = a.value; changed(); });
        faqList.appendChild(h('div', { class: 'a-acc p-3' }, h('div', { class: 'flex gap-2 mb-2' }, q,
          h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Move FAQ ' + (i + 1) + ' up', disabled: i === 0, on: { click: function () { S.faq.splice(i - 1, 0, S.faq.splice(i, 1)[0]); drawFaq(); changed(); } } }, AGP.icon('up')),
          h('button', { type: 'button', class: 'a-iconbtn sm', 'aria-label': 'Remove FAQ ' + (i + 1), on: { click: function () { S.faq.splice(i, 1); drawFaq(); changed(); } } }, AGP.icon('trash'))), a));
      });
    }
    drawFaq();

    /* ----- SEO ----- */
    var mt = h('input', { class: 'a-input', value: S.seoTitle, 'aria-describedby': 'mtc' }), md = h('textarea', { class: 'a-textarea', rows: 3, 'aria-describedby': 'mdc' }); md.value = S.seoDesc;
    var mtc = h('span', { class: 'a-counter', id: 'mtc' }), mdc = h('span', { class: 'a-counter', id: 'mdc' });
    var serp = h('div', { class: 'a-serp' }), og = h('div', { class: 'a-og' }), ldPre = h('pre', { class: 'a-pre a-mono', tabindex: '0', 'aria-label': 'JSON-LD preview' }), checks = h('ul', { class: 'a-list' });
    var canon = h('input', { class: 'a-input', type: 'url', value: S.canonical || '', placeholder: 'Optional canonical URL' });
    canon.addEventListener('input', function () { S.canonical = canon.value; dirty = true; });
    mt.addEventListener('input', function () { S.seoTitle = mt.value; changed(); }); md.addEventListener('input', function () { S.seoDesc = md.value; changed(); });
    function counter(el, n, min, max) { el.textContent = n + ' / ' + max + (n > max ? ' (too long)' : n && n < min ? ' (short)' : n ? ' (good)' : ''); el.className = 'a-counter ' + (n > max ? 'bad' : n >= min ? 'ok' : ''); }
    function jsonld() {
      var base = cur ? cur.jsonld(S) : { '@type': 'Article' };
      var m = S.image && S.image.mid ? AGP.media().filter(function (x) { return x.id === S.image.mid; })[0] : null;
      if (m) base.image = m.name;
      var g = [Object.assign({ '@id': '#post' }, base)];
      var faq = S.faq.filter(function (f) { return f.q.trim() && f.a.trim(); });
      if (faq.length) g.push({ '@type': 'FAQPage', mainEntity: faq.map(function (f) { return { '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } }; }) });
      return JSON.stringify({ '@context': 'https://schema.org', '@graph': g }, null, 2);
    }
    function seoRefresh() {
      var t = S.seoTitle || S.title || 'Untitled', d = S.seoDesc || S.excerpt || AGP.textOf(S.content).slice(0, 160);
      counter(mtc, S.seoTitle.length, 30, 60); counter(mdc, S.seoDesc.length, 120, 160);
      permalink.textContent = '/' + S.lang + '/' + (S.slug || '…') + '.html';
      AGP.mount(serp, h('div', { class: 'u', text: 'pressafrica.example › ' + S.lang + ' › ' + (S.slug || '…') }), h('div', { class: 't', text: t.slice(0, 62) }), h('div', { class: 'd', text: d.slice(0, 165) }));
      var m = S.image && S.image.mid ? AGP.media().filter(function (x) { return x.id === S.image.mid; })[0] : null;
      var im = h('div', { class: 'im' }); if (m && (m.src || AGP.blobs[m.id])) { var i2 = h('img', { src: m.src || AGP.blobs[m.id], alt: '' }); i2.style.width = '100%'; i2.style.height = '100%'; i2.style.objectFit = 'cover'; im.appendChild(i2); } else im.textContent = AGP.initials(S.title || 'AGP');
      AGP.mount(og, im, h('div', { class: 'tx' }, h('small', { text: 'pressafrica.example' }), h('b', { class: 'block', text: t.slice(0, 70) }), h('span', { class: 'a-muted text-sm', text: d.slice(0, 110) })));
      ldPre.textContent = jsonld();
      var C = [[S.seoTitle.length >= 30 && S.seoTitle.length <= 60, 'Meta title between 30 and 60 characters'], [S.seoDesc.length >= 120 && S.seoDesc.length <= 160, 'Meta description between 120 and 160 characters'], [S.slug && S.slug.length <= 70, 'Short slug (70 characters or fewer)'], [!!(m && S.image.alt), 'Featured image with alt text'], [/<h2/i.test(S.content), 'Content has at least one H2 heading'], [AGP.textOf(S.content).split(' ').length >= 150, 'At least 150 words of content']];
      AGP.mount(checks, C.map(function (c) { return h('li', {}, h('span', { class: 'a-badge ' + (c[0] ? 'ok' : 'warn'), text: c[0] ? 'Pass' : 'To do' }), h('span', { text: c[1] })); }));
    }

    /* ----- sidebar: publish ----- */
    var status = h('select', { class: 'a-select', id: 'stSel' }, [['draft', 'Draft'], ['pending', 'Pending review']].concat(canPub ? [['published', 'Published'], ['scheduled', 'Scheduled']] : []).map(function (o) { return h('option', { value: o[0], text: o[1], selected: S.status === o[0] ? true : null }); }));
    if (!canPub && (S.status === 'published' || S.status === 'scheduled')) { status.appendChild(h('option', { value: S.status, text: S.status === 'published' ? 'Published' : 'Scheduled', selected: true })); status.disabled = true; }
    var dateIn = h('input', { class: 'a-input', type: 'datetime-local', id: 'dtIn', value: (S.date || '').slice(0, 16) });
    var dateLbl = h('label', { class: 'a-label', for: 'dtIn' });
    var pubBtn = h('button', { type: 'button', class: 'a-btn a-btn-primary w-full' });
    function wf() {
      S.status = status.value; var map = { draft: 'Save draft', pending: 'Submit for review', published: 'Publish now', scheduled: 'Schedule' };
      pubBtn.textContent = (S.status === 'published' && !isNew && existing.status === 'published') ? 'Update' : map[S.status];
      dateLbl.textContent = S.status === 'scheduled' ? 'Schedule for *' : 'Publish date';
      dateIn.setAttribute('aria-required', S.status === 'scheduled' ? 'true' : 'false');
    }
    status.addEventListener('change', function () { wf(); dirty = true; }); dateIn.addEventListener('change', function () { S.date = dateIn.value; dirty = true; }); wf();
    var errBox = h('div', { class: 'a-card a-card-b', role: 'alert', hidden: true, tabindex: '-1' });
    var lang = h('select', { class: 'a-select', id: 'lgSel' }, [['fr', 'Français'], ['en', 'English']].map(function (o) { return h('option', { value: o[0], text: o[1], selected: S.lang === o[0] ? true : null }); }));
    lang.addEventListener('change', function () { S.lang = lang.value; changed(); });
    var authorSel = h('select', { class: 'a-select', id: 'auSel', disabled: AGP.role().id === 'author' }, AGP.users().map(function (u) { return h('option', { value: u.id, text: u.name, selected: S.author === u.id ? true : null }); }));
    authorSel.addEventListener('change', function () { S.author = authorSel.value; dirty = true; });

    function validate() {
      var m = [];
      if (!S.title.trim()) m.push(['Title is required.', title]);
      if (!S.slug) m.push(['Slug is required.', slug]);
      else if (AGP.posts().some(function (p) { return p.slug === S.slug && p.lang === S.lang && p.id !== S.id; })) m.push(['Another ' + S.lang.toUpperCase() + ' post already uses this slug.', slug]);
      if (S.status === 'scheduled' && (!S.date || new Date(S.date) <= new Date())) m.push(['Scheduled date must be in the future.', dateIn]);
      if (S.image && S.image.mid && !(S.image.alt || '').trim()) m.push(['Featured image needs alt text.', null]);
      if (cur) cur.validate().forEach(function (x) { m.push([x, null]); });
      return m;
    }
    function save() {
      S.content = rte.getHTML(); S.modified = AGP.nowLocal(); if (!S.excerpt) S.excerpt = AGP.textOf(S.content).slice(0, 180);
      var errs = validate();
      if (errs.length) {
        errBox.hidden = false; AGP.mount(errBox, h('h2', { class: 'text-lg', text: 'Please fix ' + errs.length + ' issue(s) before saving' }), h('ul', {}, errs.map(function (e) { return h('li', {}, e[1] ? h('a', { href: '#', text: e[0], on: { click: function (ev) { ev.preventDefault(); e[1].focus(); } } }) : e[0]); })));
        errBox.focus(); return;
      }
      errBox.hidden = true;
      var all = AGP.posts(), i = all.map(function (p) { return p.id; }).indexOf(S.id);
      if (i < 0) all.unshift(S); else all[i] = S;
      if (AGP.savePosts(all)) { dirty = false; AGP.toast(S.status === 'published' ? 'Post published.' : S.status === 'scheduled' ? 'Scheduled for ' + AGP.fmtDate(S.date, true) + '.' : S.status === 'pending' ? 'Submitted for review.' : 'Draft saved.', 'ok'); }
      if (isNew) { isNew = false; existing = S; history.replaceState(null, '', 'post-edit.html?id=' + encodeURIComponent(S.id)); heading.textContent = 'Edit post'; }
      lastSaved.textContent = 'Saved ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }
    pubBtn.addEventListener('click', save);
    var lastSaved = h('p', { class: 'a-hint', role: 'status', text: isNew ? 'Not saved yet' : 'Last modified ' + AGP.fmtDate(S.modified, true) });
    function preview() {
      var c = h('div', { class: 'a-rte-area' }); c.innerHTML = AGP.sanitize(rte.getHTML());
      var m = S.image && S.image.mid ? AGP.media().filter(function (x) { return x.id === S.image.mid; })[0] : null;
      var faq = S.faq.filter(function (f) { return f.q; });
      AGP.modal({ title: 'Preview: ' + (S.title || 'Untitled'), size: 'lg', body: h('article', {}, h('p', { class: 'a-eyebrow', text: S.lang.toUpperCase() + ' • ' + S.format }), m && (m.src || AGP.blobs[m.id]) ? h('img', { src: m.src || AGP.blobs[m.id], alt: S.image.alt || '', style: null }) : null, S.excerpt ? h('p', { class: 'a-muted', text: S.excerpt }) : null, c, faq.length ? h('section', {}, h('h2', { text: 'FAQ' }), faq.map(function (f) { return h('details', { class: 'a-acc p-3' }, h('summary', { text: f.q }), h('p', { text: f.a })); })) : null), actions: [{ label: 'Close', cls: 'a-btn-ghost' }] });
    }

    /* ----- categories ----- */
    var catBox = h('div', { role: 'group', 'aria-label': 'Categories' });
    catBox.style.maxHeight = '220px'; catBox.style.overflow = 'auto';
    AGP.catFlat().forEach(function (f) {
      var cb = h('input', { type: 'checkbox', checked: S.categoryIds.indexOf(f.c.id) > -1, value: f.c.id });
      cb.addEventListener('change', function () { S.categoryIds = $$('input:checked', catBox).map(function (x) { return x.value; }); dirty = true; });
      var l = h('label', { class: 'a-check py-1', style: null }, cb, h('span', { text: AGP.catName(f.c) })); l.style.paddingLeft = (f.depth * 18) + 'px'; l.style.display = 'flex';
      catBox.appendChild(l);
    });

    /* ----- tags ----- */
    var tagList = h('div', { class: 'flex flex-wrap gap-2 mb-2' }), tagIn = h('input', { class: 'a-input', placeholder: 'Add tag, press Enter', 'aria-label': 'Add tag' });
    function drawTags() { AGP.mount(tagList, S.tags.map(function (t, i) { return h('span', { class: 'a-badge' }, t, h('button', { type: 'button', 'aria-label': 'Remove tag ' + t, class: 'a-iconbtn sm', on: { click: function () { S.tags.splice(i, 1); drawTags(); dirty = true; } } }, AGP.icon('x'))); })); }
    tagIn.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ',') && tagIn.value.trim()) { e.preventDefault(); var v = tagIn.value.trim().replace(/,$/, ''); if (S.tags.indexOf(v) < 0) S.tags.push(v.slice(0, 40)); tagIn.value = ''; drawTags(); dirty = true; } });
    drawTags();

    /* ----- featured image ----- */
    var imgBox = h('div');
    function drawImg() {
      var m = S.image && S.image.mid ? AGP.media().filter(function (x) { return x.id === S.image.mid; })[0] : null;
      var alt = h('input', { class: 'a-input', value: (S.image && S.image.alt) || '', 'aria-label': 'Featured image alt text', placeholder: 'Alt text (required)', 'aria-required': 'true' }); alt.addEventListener('input', function () { S.image.alt = alt.value; changed(); });
      var pv = h('div', { class: 'a-og' }, h('div', { class: 'im', style: null }, m ? AGP.mediaPreview(m) : h('span', { text: 'No image' })));
      pv.firstChild.style.background = m ? AGP.tint(m.name) : ''; pv.firstChild.style.height = '130px'; if (m && pv.firstChild.querySelector('img')) { var im = pv.firstChild.querySelector('img'); im.style.width = '100%'; im.style.height = '100%'; im.style.objectFit = 'cover'; }
      AGP.mount(imgBox, pv, m ? h('div', { class: 'mt-2' }, alt) : null, h('div', { class: 'flex gap-2 mt-2' }, AGP.btn(m ? 'Change' : 'Choose image', { icon: 'image', cls: 'a-btn-sm', on: { click: function () { AGP.pickMedia({ kind: 'image', title: 'Featured image' }).then(function (it) { if (it[0]) { S.image = { mid: it[0].id, alt: it[0].alt || S.image.alt || '', path: '' }; drawImg(); changed(); } }); } } }), m ? AGP.btn('Remove', { cls: 'a-btn-sm a-btn-ghost', on: { click: function () { S.image = { mid: '', alt: '', path: '' }; drawImg(); changed(); } } }) : null));
    }
    drawImg();

    /* ----- AI writer (mock) ----- */
    function aiPanel() {
      if (!AGP.can('ai', 'view')) return null;
      var prompt = h('textarea', { class: 'a-textarea', rows: 3, id: 'aiP', placeholder: 'e.g. Write an article on solar micro-grids in rural Senegal' });
      var tone = h('select', { class: 'a-select', id: 'aiT' }, ['Neutral newsroom', 'Explanatory', 'Concise', 'Engaging'].map(function (t) { return h('option', { value: t, text: t }); }));
      var out = h('div', { class: 'a-stream', role: 'region', 'aria-busy': 'false', tabindex: '0', 'aria-label': 'AI output' }), live = h('p', { class: 'a-vh', role: 'status', 'aria-live': 'polite' });
      var timer = null, text = '', btns = {};
      function stop(msg) { clearInterval(timer); timer = null; out.classList.remove('live'); out.setAttribute('aria-busy', 'false'); Object.keys(btns).forEach(function (k) { btns[k].disabled = false; }); btns.stop.hidden = true; if (msg) live.textContent = msg; }
      function stream(full) {
        stop(); text = ''; out.textContent = ''; out.classList.add('live'); out.setAttribute('aria-busy', 'true');
        Object.keys(btns).forEach(function (k) { btns[k].disabled = true; }); btns.stop.disabled = false; btns.stop.hidden = false; live.textContent = 'Generating (mock)';
        var words = full.split(/(\s+)/), i = 0;
        timer = setInterval(function () { var n = 3; while (n-- && i < words.length) text += words[i++]; out.textContent = text; out.scrollTop = out.scrollHeight; if (i >= words.length) { stop('Generation finished. Review before inserting.'); btns.ins.disabled = false; btns.rep.disabled = false; } }, 30);
      }
      var fr = function () { return S.lang === 'fr'; };
      function gen() {
        var p = prompt.value.trim() || S.title.trim(); if (!p) { AGP.toast('Write a prompt or a title first.', 'warn'); prompt.focus(); return; }
        var t = tone.value;
        stream(fr() ? 'Sujet : ' + p + '\n\nDakar, ' + new Date().toLocaleDateString('fr-FR', { dateStyle: 'long' }) + ' — Le sujet « ' + p + ' » s’impose dans le débat public. Voici les trois points à retenir pour les lecteurs.\n\nPremièrement, le contexte : les faits connus, les chiffres vérifiés et les acteurs concernés. Deuxièmement, les enjeux pour les populations et pour les institutions. Enfin, les prochaines étapes attendues et les questions encore ouvertes.\n\n[Texte simulé, ton : ' + t + '. Connectez une clé OpenAI ou Gemini dans les réglages pour une vraie génération. Vérifiez tous les faits.]'
          : 'Topic: ' + p + '\n\nDakar, ' + new Date().toLocaleDateString('en-GB', { dateStyle: 'long' }) + ' — The question of “' + p + '” is gaining ground in public debate. Here are the three points readers need.\n\nFirst, the context: the known facts, verified figures and the actors involved. Second, what is at stake for communities and institutions. Finally, the next expected steps and the questions that remain open.\n\n[Mock text, tone: ' + t + '. Connect an OpenAI or Gemini key in Settings for real generation. Fact-check everything.]');
      }
      function src() { var s = rte.getSelectionText() || AGP.textOf(rte.getHTML()); return s.trim(); }
      function rewrite() { var s = src(); if (!s) { AGP.toast('Write or select some text in the editor first.', 'warn'); return; } stream((fr() ? 'Reformulation (' + tone.value + ') :\n\n' : 'Rewrite (' + tone.value + '):\n\n') + s.split(/(?<=[.!?])\s+/).slice(0, 6).map(function (x) { return x.replace(/^(\w)/, function (c) { return c.toUpperCase(); }); }).join(' ') + (fr() ? '\n\n[Simulation : aucun modèle connecté.]' : '\n\n[Simulation: no model connected.]')); }
      function enhance() { var s = src(); if (!s) { AGP.toast('Write or select some text in the editor first.', 'warn'); return; } stream((fr() ? 'Version enrichie :\n\nEn bref : ' + s.slice(0, 120) + '…\n\n' : 'Enhanced version:\n\nIn brief: ' + s.slice(0, 120) + '…\n\n') + s.slice(0, 600) + (fr() ? '\n\nPour aller plus loin : ajoutez une citation, un chiffre sourcé et un lien interne.\n\n[Simulation.]' : '\n\nGo further: add a quote, a sourced figure and an internal link.\n\n[Mock output.]')); }
      function mk(k, label, icon, fn, cls) { btns[k] = h('button', { type: 'button', class: 'a-btn a-btn-sm ' + (cls || ''), on: { click: fn } }, icon ? AGP.icon(icon) : null, label); return btns[k]; }
      function toHtml(t) { return t.split(/\n{2,}/).map(function (p) { return '<p>' + AGP.esc(p).replace(/\n/g, '<br>') + '</p>'; }).join(''); }
      var acts = h('div', { class: 'flex flex-wrap gap-2 mt-2' }, mk('gen', 'Generate', 'spark', gen, 'a-btn-primary'), mk('rw', 'Rewrite', 'refresh', rewrite), mk('en', 'Enhance', 'spark', enhance), mk('stop', 'Stop', 'x', function () { stop('Stopped.'); }, 'a-btn-danger'));
      btns.stop.hidden = true;
      var ins = mk('ins', 'Insert', 'plus', function () { rte.insertHTML(toHtml(text)); AGP.toast('Inserted into the editor.', 'ok'); }, 'a-btn-flash'), rep = mk('rep', 'Replace content', null, function () { rte.setHTML(toHtml(text)); S.content = rte.getHTML(); changed(); AGP.toast('Content replaced.', 'ok'); });
      ins.disabled = rep.disabled = true;
      return h('section', { class: 'a-card a-ai', 'aria-labelledby': 'aiH' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'aiH', text: 'AI Writer' }), h('span', { class: 'a-badge info', text: 'Mock' })),
        h('div', { class: 'a-card-b' }, h('p', { class: 'a-hint mb-2' }, 'Simulated output. ', h('a', { href: 'settings.html#ai', text: 'Connect your OpenAI / Gemini key in Settings' }), ' to enable real generation.'),
          h('label', { class: 'a-label', for: 'aiP', text: 'Prompt' }), prompt, h('label', { class: 'a-label mt-2', for: 'aiT', text: 'Tone' }), tone, acts, out, live, h('div', { class: 'flex gap-2 mt-2 flex-wrap' }, ins, rep)));
    }

    /* ----- assemble ----- */
    var heading = null;
    setFormat(S.format);
    var main = h('div', { class: 'a-stack' },
      errBox,
      h('div', { class: 'a-card a-card-b' }, title, h('div', { class: 'flex flex-wrap items-center gap-2 mt-3' }, h('span', { class: 'a-hint', text: 'Permalink' }), permalink, h('div', { class: 'flex-1', style: null }, slug))),
      h('div', { class: 'a-card a-card-b' }, h('p', { class: 'a-label', text: 'Post format' }), fmtSwitch),
      h('section', { class: 'a-card', 'aria-live': 'off' }, h('div', { class: 'a-card-h' }, fmtTitle), h('div', { class: 'a-card-b' }, fmtPanel)),
      h('section', { class: 'a-card' }, h('div', { class: 'a-card-h' }, bodyLabel), h('div', { class: 'a-card-b' }, rte.el, h('label', { class: 'a-label mt-3', for: 'excI', text: 'Excerpt' }), excerpt)),
      h('section', { class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { class: 'text-xl', text: 'FAQ (per post)' }), AGP.btn('Add question', { icon: 'plus', cls: 'a-btn-sm', on: { click: function () { S.faq.push({ q: '', a: '' }); drawFaq(); changed(); var ins = $$('input', faqList); ins[ins.length - 1].focus(); } } })), h('div', { class: 'a-card-b' }, faqList)),
      h('section', { class: 'a-card', 'aria-labelledby': 'seoH' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'seoH', text: 'SEO & social' })), h('div', { class: 'a-card-b' },
        h('div', { class: 'a-field' }, h('div', { class: 'flex justify-between' }, h('label', { class: 'a-label', for: 'mtI', text: 'Meta title' }), mtc), mt),
        h('div', { class: 'a-field' }, h('div', { class: 'flex justify-between' }, h('label', { class: 'a-label', for: 'mdI', text: 'Meta description' }), mdc), md),
        h('div', { class: 'a-grid2' }, h('div', { class: 'a-field' }, h('label', { class: 'a-label', text: 'Canonical URL' }), canon), h('div', { class: 'a-field' }, h('label', { class: 'a-check', style: null }, (function () { var c = h('input', { type: 'checkbox', checked: !!S.noindex }); c.addEventListener('change', function () { S.noindex = c.checked; dirty = true; }); return c; })(), h('span', { text: 'noindex (hide from search engines)' })))),
        h('div', { class: 'a-grid2' }, h('div', {}, h('p', { class: 'a-label', text: 'Search result preview' }), serp), h('div', {}, h('p', { class: 'a-label', text: 'Open Graph preview' }), og)),
        h('p', { class: 'a-label mt-4', text: 'Checklist' }), checks, h('p', { class: 'a-label mt-4', text: 'JSON-LD structured data (generated)' }), ldPre)));
    mt.id = 'mtI'; md.id = 'mdI'; excerpt.id = 'excI'; excerpt.removeAttribute('aria-label');
    var side = h('div', { class: 'a-stack' },
      h('section', { class: 'a-card', 'aria-labelledby': 'pbH' }, h('div', { class: 'a-card-h' }, h('h2', { id: 'pbH', text: 'Publish' }), AGP.badge(S.status)),
        h('div', { class: 'a-card-b' }, h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'stSel', text: 'Workflow status' }), status, h('p', { class: 'a-hint', text: canPub ? 'Draft → Pending review → Published, or schedule a release.' : 'Authors submit for review; an editor publishes.' })),
          h('div', { class: 'a-field' }, dateLbl, dateIn), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'lgSel', text: 'Language' }), lang), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'auSel', text: 'Author' }), authorSel),
          pubBtn, h('div', { class: 'flex gap-2 mt-2' }, AGP.btn('Preview', { icon: 'eye', cls: 'a-btn-sm flex-1', on: { click: preview } }), AGP.btn('Back', { cls: 'a-btn-sm a-btn-ghost', href: 'posts.html' })), lastSaved)),
      h('section', { class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { text: 'Categories' })), h('div', { class: 'a-card-b' }, catBox)),
      h('section', { class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { text: 'Featured image' })), h('div', { class: 'a-card-b' }, imgBox)),
      h('section', { class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { text: 'Tags' })), h('div', { class: 'a-card-b' }, tagList, tagIn)),
      aiPanel());
    AGP.mount(root, AGP.head({ eyebrow: 'Editor', title: isNew ? 'New post' : 'Edit post', sub: 'Choose a format, write, add FAQ and SEO, then move it through the workflow.' }), h('div', { class: 'a-edit-grid' }, main, side));
    heading = $('h1', root);
    seoRefresh();
  });
})();
