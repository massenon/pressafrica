/* Backups page: manual backup, automatic schedule, download, restore (from the list or from a file), delete.
   The snapshot logic (AGP.backupNow / backupDue / backupRestore) lives in admin-lib.js so every admin page can run the automatic backup. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  var TYPE = { auto: 'ok', manual: 'info', 'pre-restore': 'warn' };

  AGP.page('backups', function (root) {
    var canC = AGP.can('backups', 'create'), canE = AGP.can('backups', 'edit'), canD = AGP.can('backups', 'delete'), canR = AGP.can('backups', 'publish');
    var tEl = h('section', { 'aria-label': 'Backups' }), cfgEl = h('section', { class: 'a-card mb-4', 'aria-labelledby': 'bkCfg' });
    function rows() { return AGP.store.get('backups', []); }
    function stateOf(r) { return r.demo ? 'Demo history' : r.data ? 'Stored in browser' : 'Download only'; }

    function runBackup() {
      var res = AGP.backupNow('manual'); t.refresh();
      AGP.download('agp-backup-' + res.entry.at.slice(0, 19).replace(/[:T]/g, '-') + '.json', res.text, 'application/json');
      AGP.toast('Backup created and downloaded (' + AGP.fmtBytes(res.entry.size) + ').', 'ok');
    }
    function download(r) {
      var d = AGP.store.get('bk.' + r.id, null); if (!d) { AGP.toast('This entry has no stored data.', 'warn'); return; }
      AGP.download('agp-backup-' + r.at.slice(0, 19).replace(/[:T]/g, '-') + '.json', JSON.stringify(d), 'application/json'); AGP.audit('export', 'backups', r.id, 'download');
    }
    function restore(snap, label) {
      AGP.confirm({ title: 'Restore ' + label + '?', text: 'Content, settings and roles are replaced by this backup. A safety backup of the current state is taken first. The audit log is kept.', ok: 'Restore', danger: true }).then(function (ok) {
        if (!ok) return; var n = AGP.backupRestore(snap); AGP.toast('Restored ' + n + ' data sets. Reloading…', 'ok'); setTimeout(function () { location.reload(); }, 900);
      });
    }
    var t = AGP.table(tEl, {
      emptyState: { icon: 'db', title: 'No backups yet', text: 'Create the first backup, or enable the schedule.', action: canC ? { label: 'Back up now', icon: 'plus', run: runBackup } : null },
      id: 'id', label: 'Backups', rows: rows, rowLabel: function (r) { return 'backup of ' + r.at; }, sort: { key: 'at', dir: 'desc' }, pageSize: 10,
      search: function (r) { return [r.type, r.by, r.checksum].join(' '); },
      filters: [{ key: 'type', label: 'Type', options: [['auto', 'Automatic'], ['manual', 'Manual'], ['pre-restore', 'Before restore']], test: function (r, v) { return r.type === v; } }],
      columns: [
        { key: 'at', label: 'When', sort: true, render: function (r) { return AGP.fmtDate(r.at, true); } },
        { key: 'type', label: 'Type', sort: true, render: function (r) { return h('span', { class: 'a-badge ' + (TYPE[r.type] || ''), text: r.type }); } },
        { key: 'by', label: 'By', sort: true }, { key: 'size', label: 'Size', sort: true, render: function (r) { return document.createTextNode(AGP.fmtBytes(r.size)); } },
        { key: 'keys', label: 'Data sets', sort: true }, { key: 'checksum', label: 'Checksum', render: function (r) { return h('span', { class: 'a-mono a-muted', text: r.checksum }); } },
        { key: 'data', label: 'State', render: function (r) { return h('span', { class: 'a-badge ' + (r.data ? 'ok' : ''), text: stateOf(r) }); } }],
      actions: function (r) {
        var a = []; if (r.data) a.push({ label: 'Download', icon: 'download', run: function () { download(r); } });
        if (r.data && canR) a.push({ label: 'Restore', icon: 'refresh', run: function () { restore(AGP.store.get('bk.' + r.id, null), 'the backup of ' + AGP.fmtDate(r.at, true)); } });
        if (canD) a.push({ label: 'Delete', icon: 'trash', run: function () {
          AGP.confirm({ title: 'Delete this backup?', text: 'The stored copy is removed. This cannot be undone.', ok: 'Delete', danger: true }).then(function (ok) {
            if (!ok) return; AGP.store.del('bk.' + r.id); AGP.store.set('backups', rows().filter(function (x) { return x.id !== r.id; })); AGP.audit('backup_delete', 'backups', r.id, r.type); t.refresh(); AGP.toast('Backup deleted.', 'warn');
          });
        } });
        return a;
      }
    });

    /* ---- schedule ---- */
    function drawCfg() {
      var c = AGP.backupCfg(), f = {};
      function row(id, label, el, hint) { return h('div', { class: 'a-field' }, h('label', { class: 'a-label', for: id, text: label }), el, hint ? h('div', { class: 'a-hint', text: hint }) : null); }
      f.enabled = h('input', { type: 'checkbox', id: 'bkOn', checked: !!c.enabled, disabled: !canE });
      f.frequency = h('select', { class: 'a-select', id: 'bkFreq', disabled: !canE }, [['daily', 'Every day'], ['weekly', 'Every week']].map(function (o) { return h('option', { value: o[0], text: o[1], selected: c.frequency === o[0] ? true : null }); }));
      f.retention = h('input', { class: 'a-input', type: 'number', id: 'bkKeep', min: 1, max: 10, value: String(c.retention), disabled: !canE });
      f.dest = h('select', { class: 'a-select', id: 'bkDest', disabled: !canE }, [['browser', 'This browser (prototype)'], ['sftp', 'SFTP server (production)'], ['s3', 'S3-compatible bucket (production)']].map(function (o) { return h('option', { value: o[0], text: o[1], selected: c.destination === o[0] ? true : null }); }));
      var save = h('button', { type: 'submit', class: 'a-btn a-btn-primary', disabled: !canE }, AGP.icon('check'), 'Save schedule');
      var form = h('form', { novalidate: 'novalidate', class: 'a-card-b' },
        h('div', { class: 'a-field' }, h('label', { class: 'a-switch', for: 'bkOn' }, f.enabled, h('i'), h('span', { text: 'Automatic backups' }))),
        h('div', { class: 'a-grid2' }, row('bkFreq', 'Frequency', f.frequency), row('bkKeep', 'Copies kept in the browser', f.retention, 'Older copies lose their stored data; their entry stays in the list.'), row('bkDest', 'Destination', f.dest, 'SFTP and S3 need a server: the prototype only keeps copies in this browser.')),
        h('p', { class: 'a-hint', text: 'Prototype: the automatic backup runs when an administrator opens any console page and a backup is due. Production runs it from the server scheduler and encrypts it at rest.' }), save);
      form.addEventListener('submit', function (e) {
        e.preventDefault(); if (!canE) return; var keep = +f.retention.value;
        if (!(keep >= 1 && keep <= 10)) { AGP.toast('Copies kept must be between 1 and 10.', 'err'); f.retention.focus(); return; }
        AGP.store.set('backupcfg', { enabled: f.enabled.checked, frequency: f.frequency.value, retention: keep, destination: f.dest.value }); AGP.toast('Backup schedule saved.', 'ok');
      });
      AGP.mount(cfgEl, h('div', { class: 'a-card-h' }, h('h2', { id: 'bkCfg', text: 'Automatic schedule' })), form);
    }
    drawCfg();

    var fileIn = h('input', { type: 'file', accept: 'application/json,.json', class: 'a-vh', id: 'bkFile', tabindex: '-1' });
    fileIn.addEventListener('change', function () {
      var f = fileIn.files[0]; if (!f) return; fileIn.value = '';
      AGP.readFile(f, 'text').then(function (txt) {
        var snap; try { snap = JSON.parse(txt); } catch (e) { snap = null; }
        if (!snap || snap.format !== 'agp-backup' || !snap.keys) { AGP.toast('This file is not an Africa Global Press backup.', 'err'); return; }
        restore(snap, 'the file ' + f.name);
      });
    });
    var acts = [];
    if (canC) acts.push(AGP.btn('Back up now', { icon: 'download', on: { click: runBackup } }));
    if (canR) acts.push(AGP.btn('Restore from file', { icon: 'upload', cls: 'a-btn-ghost', on: { click: function () { fileIn.click(); } } }));
    AGP.mount(root, AGP.head({ eyebrow: 'System', title: 'Backups', sub: 'Manual and automatic snapshots of content, settings and roles. Download a copy, or restore one after confirmation.', actions: acts }), fileIn, cfgEl, tEl);
  });
})();
