/* Settings: general, appearance (colors with AA contrast check, typography, custom font), languages, cookies/GDPR, security, maintenance, PWA, AI keys. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h, $$ = AGP.$$;
  function lum(hex) { var c = hex.replace('#', ''); if (c.length === 3) c = c.replace(/(.)/g, '$1$1'); var v = [0, 2, 4].map(function (i) { var x = parseInt(c.substr(i, 2), 16) / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; }
  function ratio(a, b) { var x = lum(a), y = lum(b), hi = Math.max(x, y), lo = Math.min(x, y); return (hi + 0.05) / (lo + 0.05); }
  var FONTS = ['Alegreya', 'Encode Sans', 'Georgia', 'system-ui', 'Merriweather', 'Source Serif 4', 'Inter'];

  AGP.page('settings', function (root) {
    var canEdit = AGP.can('settings', 'edit');
    var S = AGP.store.get('settings', {}), tabs = h('div', { class: 'a-tabs', role: 'tablist', 'aria-label': 'Settings sections' }), panels = {}, inputs = {};
    function field(k, label, type, o) {
      o = o || {}; var id = 'set-' + k, c;
      if (type === 'select') c = h('select', { class: 'a-select', id: id }, o.options.map(function (op) { return h('option', { value: op[0], text: op[1], selected: String(S[k]) === String(op[0]) ? true : null }); }));
      else if (type === 'textarea') { c = h('textarea', { class: 'a-textarea', id: id, rows: o.rows || 3 }); c.value = S[k] || ''; }
      else if (type === 'checkbox') { c = h('input', { type: 'checkbox', id: id, checked: !!S[k] }); }
      else c = h('input', { class: 'a-input', id: id, type: type === 'password' ? 'password' : type, value: S[k] == null ? '' : String(S[k]), min: o.min, max: o.max, autocomplete: type === 'password' ? 'off' : null });
      if (!canEdit) c.disabled = true;
      inputs[k] = { el: c, type: type };
      var wrap = type === 'checkbox'
        ? h('div', { class: 'a-field' }, h('label', { class: 'a-switch', for: id }, c, h('i'), h('span', { text: label })), o.hint ? h('div', { class: 'a-hint', text: o.hint }) : null)
        : h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: id, text: label }), c, o.hint ? h('div', { class: 'a-hint', id: id + 'h', text: o.hint }) : null);
      if (o.hint && type !== 'checkbox') c.setAttribute('aria-describedby', id + 'h');
      return wrap;
    }
    function panel(id, title, kids) { var p = h('section', { role: 'tabpanel', id: 'tp-' + id, 'aria-labelledby': 'tb-' + id, hidden: true, class: 'a-card' }, h('div', { class: 'a-card-h' }, h('h2', { text: title })), h('div', { class: 'a-card-b' }, kids)); panels[id] = p; return p; }

    /* appearance preview + contrast */
    var pv = h('div', { class: 'a-card a-card-b', 'aria-live': 'polite' }), cInfo = h('div');
    function drawPrev() {
      var p = inputs.colorPrimary.el.value, s = inputs.colorSecondary.el.value, r1 = ratio('#ffffff', p), r2 = ratio('#000000', s);
      pv.style.borderTop = '6px solid ' + s;
      AGP.mount(pv, h('p', { class: 'a-eyebrow', text: 'Live preview' }), h('h3', { text: S.siteTitle || 'Site title', style: null }), h('p', { class: 'a-muted', text: 'Sample paragraph in the body font.' }), (function () { var b = h('span', { class: 'a-btn', text: 'Primary button' }); b.style.background = p; b.style.color = '#fff'; b.style.borderColor = p; var f = h('span', { class: 'a-btn ml-2', text: 'Flash button' }); f.style.background = s; f.style.color = '#000'; f.style.borderColor = s; return h('div', {}, b, f); })());
      pv.firstChild.nextSibling.style.fontFamily = inputs.fontHeading.el.value + ',serif'; pv.lastChild.previousSibling.style.fontFamily = inputs.fontBody.el.value + ',sans-serif';
      AGP.mount(cInfo, h('p', {}, h('span', { class: 'a-badge ' + (r1 >= 4.5 ? 'ok' : 'bad'), text: (r1 >= 4.5 ? 'AA pass' : 'AA fail') }), ' White text on primary: ' + r1.toFixed(2) + ':1'), h('p', {}, h('span', { class: 'a-badge ' + (r2 >= 4.5 ? 'ok' : 'bad'), text: (r2 >= 4.5 ? 'AA pass' : 'AA fail') }), ' Black text on secondary: ' + r2.toFixed(2) + ':1'));
    }
    var logoPrev = h('div', { class: 'a-vh' }), fontInfo = h('p', { class: 'a-hint', text: S.customFontName ? 'Custom font: ' + S.customFontName : 'No custom font uploaded.' });
    var logoIn = h('input', { type: 'file', class: 'a-input', accept: 'image/*', id: 'logoF', disabled: !canEdit }), fontIn = h('input', { type: 'file', class: 'a-input', accept: '.woff2,.woff,.ttf,.otf', id: 'fontF', disabled: !canEdit });
    logoIn.addEventListener('change', function () { var f = logoIn.files[0]; if (!f) return; if (f.size > 600000) { AGP.toast('Logo must be under 600 KB.', 'err'); logoIn.value = ''; return; } AGP.readFile(f).then(function (u) { S.logoData = u; AGP.mount(logoPrev, h('img', { src: u, alt: 'Current logo preview', style: null })); logoPrev.className = 'mt-2'; logoPrev.firstChild.style.maxHeight = '56px'; AGP.toast('Logo loaded. Save settings to keep it.', 'ok'); }); });
    fontIn.addEventListener('change', function () { var f = fontIn.files[0]; if (!f) return; if (f.size > 400000) { AGP.toast('Font file too large for the prototype (400 KB max).', 'err'); fontIn.value = ''; return; } AGP.readFile(f).then(function (u) { S.customFontName = f.name; S.customFontData = u; fontInfo.textContent = 'Custom font: ' + f.name + ' (applied on the public site build, stored locally for offline rendering).'; }); });
    if (S.logoData) { AGP.mount(logoPrev, h('img', { src: S.logoData, alt: 'Current logo preview' })); logoPrev.className = 'mt-2'; logoPrev.firstChild.style.maxHeight = '56px'; }

    var P = [];
    P.push(panel('general', 'General', [field('siteTitle', 'Site title', 'text'), field('tagline', 'Tagline', 'text'), field('logoText', 'Logo text (fallback)', 'text'), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'logoF', text: 'Logo / favicon image' }), logoIn, logoPrev), field('newsSitemap', 'Google News sitemap (48 h) and Publisher Center RSS', 'checkbox'), field('rssFeed', 'Public RSS feed', 'checkbox'), field('popup', 'Newsletter popup', 'checkbox')]));
    P.push(panel('appearance', 'Appearance & typography', [h('div', { class: 'a-grid2' }, field('colorPrimary', 'Primary color', 'color', { hint: 'Brand default #013079' }), field('colorSecondary', 'Secondary / accent color', 'color', { hint: 'Default flash yellow #ffec00' })), cInfo,
      h('div', { class: 'a-grid2' }, field('fontHeading', 'Heading font', 'select', { options: FONTS.map(function (f) { return [f, f]; }) }), field('fontBody', 'Body font', 'select', { options: FONTS.map(function (f) { return [f, f]; }) }), field('baseSize', 'Base font size (px)', 'number', { min: 14, max: 24 })),
      h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'fontF', text: 'Upload a custom font (woff2 / ttf)' }), fontIn, fontInfo), pv]));
    P.push(panel('languages', 'Languages & URLs', [h('fieldset', { class: 'a-field' }, h('legend', { class: 'a-label', text: 'Enabled languages' }), ['fr', 'en'].map(function (l) { var c = h('input', { type: 'checkbox', id: 'lg-' + l, checked: (S.langs || []).indexOf(l) > -1, disabled: !canEdit }); inputs['lang_' + l] = { el: c, type: 'checkbox' }; return h('label', { class: 'a-check mr-4', for: 'lg-' + l }, c, h('span', { text: l === 'fr' ? 'Français' : 'English' })); })),
      field('defaultLang', 'Default language', 'select', { options: [['fr', 'Français'], ['en', 'English']] }), field('urlStructure', 'URL structure (SEO friendly)', 'select', { options: [['subdir', '/fr/ and /en/ sub-directories'], ['subdomain', 'fr.example.org / en.example.org'], ['query', '?lang=fr (not recommended)']] })]));
    P.push(panel('privacy', 'Privacy & cookies (GDPR)', [field('cookiesOn', 'Show cookie consent banner', 'checkbox'), field('blockThirdParty', 'Block third-party scripts until accepted', 'checkbox', { hint: 'No native tracking cookies are set by the platform.' }), field('cookieText', 'Banner text', 'textarea'), field('commentsApproval', 'Require approval before comments appear', 'checkbox'), field('nofollow', 'Add nofollow to external links in user content', 'checkbox')]));
    P.push(panel('security', 'Security', [h('div', { class: 'a-grid2' }, field('minPassword', 'Minimum password length', 'number', { min: 8, max: 64 }), field('maxAttempts', 'Failed logins before lockout', 'number', { min: 3, max: 20 }), field('lockMinutes', 'Lockout minutes', 'number', { min: 1, max: 240 })), field('passComplexity', 'Require upper/lower case, number and symbol', 'checkbox')]));
    P.push(panel('maintenance', 'Maintenance mode', [h('p', { class: 'a-badge warn', text: 'When enabled, public visitors see the message below. Administrators keep full access.' }), field('maintenance', 'Enable maintenance mode', 'checkbox'), field('maintenanceMsg', 'Public message', 'textarea'), field('maintenanceIps', 'Allowed IPs (comma separated)', 'text')]));
    P.push(panel('pwa', 'Progressive Web App', [field('pwa', 'Enable PWA (installable, offline shell)', 'checkbox'), h('div', { class: 'a-grid2' }, field('pwaName', 'App name', 'text'), field('pwaShort', 'Short name', 'text'), field('pwaTheme', 'Theme color', 'color'))]));
    P.push(panel('ai', 'AI content engine', [h('p', { class: 'a-badge info', text: 'Prototype only: keys are stored in this browser. A real deployment keeps them server-side, encrypted.' }), field('aiProvider', 'Provider', 'select', { options: [['openai', 'OpenAI (ChatGPT)'], ['gemini', 'Google Gemini']] }), field('aiModel', 'Model', 'text'), field('aiOpenaiKey', 'OpenAI API key', 'password'), field('aiGeminiKey', 'Gemini API key', 'password'), h('p', { class: 'a-hint', text: 'Until a key is connected, the editor AI Writer returns simulated text.' })]));
    /* analytics & tracking: IDs are validated, scripts stay gated by consent, and the generated snippet is a preview that is never executed */
    var snip = h('textarea', { class: 'a-textarea a-mono', id: 'anSnip', rows: 12, readonly: true, 'aria-label': 'Generated tracking snippet (preview)' });
    function drawSnip() {
      var ga = inputs.gaId.el.value.trim(), gtm = inputs.gtmId.el.value.trim(), px = inputs.pixelId.el.value.trim(), out = [];
      var denied = inputs.consentDenied.el.checked ? 'denied' : 'granted';
      if (inputs.gaOn.el.checked && ga) out.push('<!-- Google Analytics 4: runs only after the "analytics" consent -->', '<script type="text/plain" data-consent-cat="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=' + ga + '"></script>',
        '<script type="text/plain" data-consent-cat="analytics">', '  window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);}',
        "  gtag('consent', 'default', { ad_storage: '" + denied + "', analytics_storage: '" + denied + "', ad_user_data: '" + denied + "', ad_personalization: '" + denied + "' });",
        "  gtag('js', new Date()); gtag('config', '" + ga + "', { anonymize_ip: " + (inputs.anonIp.el.checked ? 'true' : 'false') + ', allow_google_signals: false });', '</script>');
      if (inputs.gtmOn.el.checked && gtm) out.push('', '<!-- Google Tag Manager: "analytics" consent -->', '<script type="text/plain" data-consent-cat="analytics">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":Date.now(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' + gtm + '");</script>');
      if (inputs.pixelOn.el.checked && px) out.push('', '<!-- Meta Pixel: runs only after the "marketing" consent -->', '<script type="text/plain" data-consent-cat="marketing">', "  !function(f,b,e,v,n,t,s){/* Meta Pixel base code */}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');", "  fbq('init', '" + px + "'); fbq('track', 'PageView');", '</script>');
      var gsc = inputs.gscToken.el.value.trim(), bing = inputs.bingToken.el.value.trim();
      if (gsc) out.push('', '<meta name="google-site-verification" content="' + gsc + '">'); if (bing) out.push('<meta name="msvalidate.01" content="' + bing + '">');
      snip.value = out.length ? out.join('\n') : '(Nothing enabled yet. Turn a service on and enter its ID.)';
    }
    var ANALYTICS_RULES = [['gaId', /^G-[A-Z0-9]{6,12}$/, 'GA4 Measurement ID looks like G-XXXXXXXXXX.'], ['gtmId', /^GTM-[A-Z0-9]{4,10}$/, 'GTM container ID looks like GTM-XXXXXXX.'],
      ['pixelId', /^\d{8,20}$/, 'Meta Pixel ID is 8 to 20 digits.'], ['gscToken', /^[A-Za-z0-9_-]{20,80}$/, 'Search Console token: 20 to 80 letters, digits, - or _.'], ['bingToken', /^[A-Fa-f0-9]{20,40}$/, 'Bing token: 20 to 40 hex characters.']];
    P.push(panel('analytics', 'Analytics & tracking', [
      h('p', { class: 'a-note info', text: 'Every tracking script stays inactive until the visitor accepts its cookie category. IDs below are validated; the snippet is a preview, never executed in the console.' }),
      h('h3', { class: 'mt-3', text: 'Google' }), h('div', { class: 'a-grid2' }, field('gaOn', 'Enable Google Analytics 4', 'checkbox'), field('gaId', 'GA4 Measurement ID', 'text', { hint: 'G-XXXXXXXXXX' }), field('gtmOn', 'Enable Google Tag Manager', 'checkbox'), field('gtmId', 'GTM container ID', 'text', { hint: 'GTM-XXXXXXX' })),
      field('gscToken', 'Search Console verification token', 'text', { hint: 'Content of the google-site-verification meta tag. Also verify the sitemap URL in Search Console.' }), field('bingToken', 'Bing Webmaster verification token', 'text', { hint: 'Content of the msvalidate.01 meta tag.' }),
      h('h3', { class: 'mt-3', text: 'Meta' }), h('div', { class: 'a-grid2' }, field('pixelOn', 'Enable Meta Pixel', 'checkbox'), field('pixelId', 'Meta Pixel ID', 'text', { hint: '8 to 20 digits. Loaded only after the "marketing" consent.' })),
      h('h3', { class: 'mt-3', text: 'Privacy (GDPR)' }), field('consentDenied', 'Consent Mode: deny storage by default until the visitor chooses', 'checkbox', { hint: 'ad_storage, analytics_storage, ad_user_data and ad_personalization start as denied.' }),
      field('anonIp', 'Anonymise IP addresses', 'checkbox'), field('respectDnt', 'Respect the browser "Do Not Track" signal', 'checkbox'), field('excludeStaff', 'Exclude signed-in staff from measurement', 'checkbox'),
      h('div', { class: 'a-grid2' }, field('gaRetention', 'GA4 data retention', 'select', { options: [['2', '2 months'], ['14', '14 months']], hint: 'Google offers 2 or 14 months for event data. Set with your data protection officer.' }), field('logRetentionDays', 'First-party log retention (days)', 'number', { min: 1, max: 730, hint: 'Value to be set with your data protection officer.' })),
      h('div', { class: 'flex gap-2 flex-wrap' }, AGP.btn('Open Analytics', { icon: 'chart', href: 'analytics.html' }), AGP.btn('Privacy settings', { icon: 'shield', cls: 'a-btn-ghost', on: { click: function () { show('privacy'); } } })),
      h('div', { class: 'a-field mt-3' }, h('label', { class: 'a-label', for: 'anSnip', text: 'Generated snippet (preview)' }), snip)]));
    var ids = [['general', 'General'], ['appearance', 'Appearance'], ['languages', 'Languages'], ['privacy', 'Privacy'], ['security', 'Security'], ['maintenance', 'Maintenance'], ['analytics', 'Analytics'], ['pwa', 'PWA'], ['ai', 'AI']];
    function show(id) { ids.forEach(function (x) { var on = x[0] === id; panels[x[0]].hidden = !on; var b = document.getElementById('tb-' + x[0]); b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; }); }
    ids.forEach(function (x) { tabs.appendChild(h('button', { type: 'button', role: 'tab', class: 'a-tab', id: 'tb-' + x[0], 'aria-controls': 'tp-' + x[0], text: x[1], on: { click: function () { show(x[0]); history.replaceState(null, '', '#' + x[0]); } } })); });
    tabs.addEventListener('keydown', function (e) { if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return; var t = $$('.a-tab', tabs), i = t.indexOf(document.activeElement); if (i < 0) return; var n = t[(i + (e.key === 'ArrowRight' ? 1 : -1) + t.length) % t.length]; n.focus(); n.click(); });
    var form = h('form', { novalidate: 'novalidate' }, tabs, ids.map(function (x) { return panels[x[0]]; }));
    var bar = h('div', { class: 'flex gap-2 mt-4' });
    if (canEdit) bar.appendChild(h('button', { type: 'submit', class: 'a-btn a-btn-primary' }, AGP.icon('check'), 'Save settings'));
    form.appendChild(bar);
    form.addEventListener('submit', function (e) {
      e.preventDefault(); if (!canEdit) return;
      var langs = ['fr', 'en'].filter(function (l) { return inputs['lang_' + l].el.checked; });
      if (!langs.length) { AGP.toast('Enable at least one language.', 'err'); show('languages'); return; }
      for (var ai = 0; ai < ANALYTICS_RULES.length; ai++) { var rule = ANALYTICS_RULES[ai], rv = inputs[rule[0]].el.value.trim(); if (rv && !rule[1].test(rv)) { AGP.toast(rule[2], 'err'); show('analytics'); inputs[rule[0]].el.setAttribute('aria-invalid', 'true'); inputs[rule[0]].el.focus(); return; } inputs[rule[0]].el.removeAttribute('aria-invalid'); }
      var dl = inputs.defaultLang.el.value; if (langs.indexOf(dl) < 0) { AGP.toast('The default language must be enabled.', 'err'); show('languages'); inputs.defaultLang.el.focus(); return; }
      Object.keys(inputs).forEach(function (k) { if (k.indexOf('lang_') === 0) return; var i = inputs[k]; S[k] = i.type === 'checkbox' ? i.el.checked : i.type === 'number' ? +i.el.value : i.el.value; });
      S.langs = langs; AGP.store.set('settings', S); AGP.toast('Settings saved.', 'ok');
    });
    ['colorPrimary', 'colorSecondary', 'fontHeading', 'fontBody'].forEach(function (k) { inputs[k].el.addEventListener('input', drawPrev); inputs[k].el.addEventListener('change', drawPrev); });
    AGP.mount(root, AGP.head({ eyebrow: 'Site', title: 'Settings', sub: 'Brand, languages, privacy, analytics and tracking, security, maintenance, PWA and AI.' }), form);
    drawPrev();
    ['gaOn', 'gaId', 'gtmOn', 'gtmId', 'pixelOn', 'pixelId', 'gscToken', 'bingToken', 'consentDenied', 'anonIp'].forEach(function (k) { inputs[k].el.addEventListener('input', drawSnip); inputs[k].el.addEventListener('change', drawSnip); });
    drawSnip();
    var hid = (location.hash || '').slice(1); show(panels[hid] ? hid : 'general');
  });
})();
