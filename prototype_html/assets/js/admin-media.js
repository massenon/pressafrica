/* Media library: file manager grid, upload via FileReader (auto-resize + WebP), mandatory alt/title. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  AGP.page('media', function (root) {
    var canCreate = AGP.can('media', 'create'), canEdit = AGP.can('media', 'edit'), canDel = AGP.can('media', 'delete');
    var q = '', kind = '', grid = h('div', { class: 'a-media-grid' }), info = h('p', { class: 'a-muted', role: 'status', 'aria-live': 'polite' });
    var file = h('input', { type: 'file', multiple: true, class: 'a-vh', tabindex: '-1', 'aria-hidden': 'true', accept: 'image/*,video/*,audio/*,.pdf,.zip,.doc,.docx' });
    function items() { return AGP.media().filter(function (m) { return (!kind || m.kind === kind) && (!q || (m.name + ' ' + m.title + ' ' + m.alt).toLowerCase().indexOf(q) > -1); }); }
    function draw() {
      AGP.clear(grid); var list = items(), all = AGP.media();
      info.textContent = list.length + ' of ' + all.length + ' files • ' + AGP.fmtBytes(all.reduce(function (a, m) { return a + (m.size || 0); }, 0));
      if (!list.length) grid.appendChild(AGP.emptyState(all.length ? { icon: 'search', title: 'No files found', text: 'Nothing matches this search or type filter.' } : { icon: 'image', title: 'Your library is empty', text: 'Upload images, audio, video or documents. Alt text and title are required.', action: canCreate ? { label: 'Upload files', icon: 'upload', run: function () { file.click(); } } : null }));
      list.forEach(function (m) {
        var pv = h('div', { class: 'pv', style: null }, AGP.mediaPreview(m)); pv.style.background = AGP.tint(m.name);
        var b = h('button', { type: 'button', class: 'a-media-item', 'aria-label': 'Open details: ' + (m.title || m.name), on: { click: function () { detail(m); } } }, pv, h('div', { class: 'mt' }, h('b', { text: m.title || m.name }), h('span', { class: 'a-muted', text: m.kind + ' • ' + AGP.fmtBytes(m.size) })));
        grid.appendChild(b);
      });
    }
    function detail(m) {
      var pv = m.kind === 'video' || m.kind === 'audio' ? AGP.renderPlayer({ type: 'native', el: m.kind, src: m.src || AGP.blobs[m.id] }, m.kind) : (function () { var d = h('div', { class: 'a-og' }, h('div', { class: 'im', style: null }, AGP.mediaPreview(m))); var im = d.querySelector('img'); if (im) { im.style.width = '100%'; im.style.maxHeight = '320px'; im.style.objectFit = 'contain'; } d.firstChild.style.height = 'auto'; d.firstChild.style.minHeight = '140px'; d.firstChild.style.background = m.src ? 'var(--surface-2)' : AGP.tint(m.name); return d; })();
      var form = h('div', {});
      var alt = h('input', { class: 'a-input', value: m.alt || '', id: 'mdAlt', 'aria-required': 'true', disabled: !canEdit }), ti = h('input', { class: 'a-input', value: m.title || '', id: 'mdT', 'aria-required': 'true', disabled: !canEdit }), er = h('div', { class: 'a-err', hidden: true, role: 'alert' });
      AGP.mount(form, pv, h('p', { class: 'a-hint mt-2', text: m.name + ' • ' + AGP.fmtBytes(m.size) + ' • ' + AGP.fmtDate(m.date) + (m.seed ? ' • Seeded placeholder (original lives in pressmedia_images/)' : '') + (m.session ? ' • Session only (too large to persist)' : '') }),
        h('div', { class: 'a-field mt-3' }, h('label', { class: 'a-label', for: 'mdAlt', text: m.kind === 'image' ? 'Alt text *' : 'Description' }), alt), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'mdT', text: 'Title *' }), ti), er);
      var acts = [{ label: 'Close', cls: 'a-btn-ghost' }];
      if (canDel) acts.push({ label: 'Delete', cls: 'a-btn-danger', run: function () { AGP.confirm({ title: 'Delete this file?', text: 'Posts using it will show a placeholder.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) { AGP.store.set('media', AGP.media().filter(function (x) { return x.id !== m.id; })); draw(); AGP.toast('File deleted.', 'warn'); } }); } });
      if (canEdit) acts.push({ label: 'Save', cls: 'a-btn-primary', keep: true, run: function (mm) {
        var msg = m.kind === 'image' && !alt.value.trim() ? 'Alt text is required for images.' : !ti.value.trim() ? 'Title is required.' : '';
        er.hidden = !msg; er.textContent = msg; if (msg) { (m.kind === 'image' && !alt.value.trim() ? alt : ti).focus(); return false; }
        var all = AGP.media(); all.forEach(function (x) { if (x.id === m.id) { x.alt = alt.value.trim(); x.title = ti.value.trim(); } }); AGP.store.set('media', all); draw(); AGP.toast('Saved.', 'ok'); mm.close(); return false;
      } });
      AGP.modal({ title: 'File details', size: 'lg', body: form, actions: acts });
    }
    function upload(files) { AGP.uploadFiles(files).then(draw); }
    file.addEventListener('change', function () { upload(file.files); file.value = ''; });
    var dz = h('div', { class: 'a-dz', role: 'button', tabindex: '0', 'aria-label': 'Upload files: drop here or press Enter to browse' }, AGP.icon('upload'), h('p', { text: 'Drop files here or click to browse. Images are resized and converted to WebP automatically.' }));
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('over'); }); });
    dz.addEventListener('drop', function (e) { upload(e.dataTransfer.files); });
    dz.addEventListener('click', function () { file.click(); }); dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); file.click(); } });
    var search = h('input', { class: 'a-input', type: 'search', id: 'mq', placeholder: 'Search files…' }); search.addEventListener('input', AGP.debounce(function () { q = search.value.toLowerCase(); draw(); }, 120));
    var kinds = h('select', { class: 'a-select', id: 'mk' }, [['', 'All types'], ['image', 'Images'], ['video', 'Video'], ['audio', 'Audio'], ['file', 'Documents']].map(function (o) { return h('option', { value: o[0], text: o[1] }); })); kinds.addEventListener('change', function () { kind = kinds.value; draw(); });
    var tools = h('div', { class: 'a-tools' }, h('div', { class: 'a-field flex-1' }, h('label', { class: 'a-label', for: 'mq', text: 'Search' }), search), h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: 'mk', text: 'Type' }), kinds), info);
    AGP.mount(root, AGP.head({ eyebrow: 'Newsroom', title: 'Media library', sub: 'Central file manager. Alt text and title are mandatory.', actions: canCreate ? [AGP.btn('Upload', { icon: 'upload', cls: 'a-btn-primary', on: { click: function () { file.click(); } } }), file] : [] }),
      canCreate ? dz : null, h('div', { class: 'a-card mt-4' }, tools, grid));
    draw();
  });
})();
