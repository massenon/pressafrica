/* Comments moderation: approve / reject / spam / delete, bulk actions, reply modal. */
(function () {
  'use strict';
  var AGP = window.AGP, h = AGP.h;
  AGP.page('comments', function (root) {
    var canPub = AGP.can('comments', 'publish'), canDel = AGP.can('comments', 'delete'), canEdit = AGP.can('comments', 'edit');
    var tEl = h('section', { 'aria-label': 'Comments' });
    /* Comments typed in the public article form are queued in the visitor's browser ('agp-comments'); the console pulls them in as pending. In production they are posted to the server. */
    (function importPublic() {
      var inbox; try { inbox = JSON.parse(localStorage.getItem('agp-comments') || '[]'); } catch (e) { inbox = []; }
      if (!inbox.length) return;
      var cur = AGP.store.get('comments', []), have = {}; cur.forEach(function (c) { have[c.id] = 1; });
      var add = inbox.filter(function (x) { return !have['pub' + x.at]; }).map(function (x) {
        return { id: 'pub' + x.at, postId: x.post, postTitle: x.postTitle || ('Article ' + x.post), author: x.author, email: x.email, text: x.text, date: String(x.at).slice(0, 16), status: 'pending', source: 'article form', lang: x.lang, consentAt: x.consent };
      });
      if (add.length) { AGP.store.set('comments', cur.concat(add)); AGP.audit('create', 'comments', 'public form', add.length + ' comment(s) imported, pending moderation'); setTimeout(function () { AGP.toast(add.length + ' new comment(s) from the site are waiting for moderation.', 'info'); }, 300); }
      try { localStorage.removeItem('agp-comments'); } catch (e) { /* ignore */ }
    })();
    function all() { return AGP.store.get('comments', []); }
    function setStatus(list, st) { var ids = list.map(function (c) { return c.id; }), a = all(); a.forEach(function (c) { if (ids.indexOf(c.id) > -1) c.status = st; }); AGP.store.set('comments', a); t.refresh(); AGP.toast(list.length + ' comment(s) ' + st + '.', st === 'approved' ? 'ok' : 'warn'); }
    function del(list) { var ids = list.map(function (c) { return c.id; }); return AGP.confirm({ title: 'Delete ' + ids.length + ' comment(s)?', text: 'This cannot be undone.', ok: 'Delete', danger: true }).then(function (ok) { if (ok) { AGP.store.set('comments', all().filter(function (c) { return ids.indexOf(c.id) < 0; })); t.refresh(); AGP.toast('Deleted.', 'warn'); } }); }
    function reply(c) {
      AGP.formModal({ title: 'Reply to ' + c.author, submit: 'Send reply', fields: [{ name: 'to', label: 'To', value: c.email, type: 'email', required: true }, { name: 'msg', label: 'Message', type: 'textarea', rows: 5, required: true, value: '' }],
        onSubmit: function () { AGP.toast('Reply queued (mock: no email is sent from the prototype).', 'ok'); } });
    }
    var t = AGP.table(tEl, {
      emptyState: { icon: 'msg', title: 'No comments to moderate', text: 'New reader comments will queue here for approval.' }, id: 'id', label: 'Comments', rows: all, rowLabel: function (r) { return 'comment by ' + r.author; }, search: function (r) { return r.author + ' ' + r.text + ' ' + r.postTitle + ' ' + r.email; }, sort: { key: 'date', dir: 'desc' }, pageSize: 10,
      initial: { f: { status: AGP.params().get('status') || '' } },
      filters: [{ key: 'status', label: 'Status', options: [['pending', 'Pending'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['spam', 'Spam']], test: function (r, v) { return r.status === v; } }],
      columns: [
        { key: 'author', label: 'Author', sort: true, render: function (r) { return h('div', {}, h('b', { text: r.author }), h('span', { class: 'a-muted block text-xs', text: r.email })); } },
        { key: 'text', label: 'Comment', render: function (r) { return h('div', { class: 'max-w-md' }, h('p', { class: 'm-0', text: r.text }), h('span', { class: 'a-muted text-xs', text: 'On: ' + r.postTitle.slice(0, 60) })); } },
        { key: 'status', label: 'Status', sort: true, render: function (r) { return AGP.badge(r.status); } },
        { key: 'date', label: 'Date', sort: true, render: function (r) { return AGP.fmtDate(r.date, true); } }],
      actions: function (r) { var a = []; if (canPub && r.status !== 'approved') a.push({ label: 'Approve', icon: 'check', run: function (c) { setStatus([c], 'approved'); } }); if (canPub && r.status !== 'rejected') a.push({ label: 'Reject', icon: 'x', run: function (c) { setStatus([c], 'rejected'); } }); if (canEdit) a.push({ label: 'Reply', icon: 'mail', run: reply }); if (canDel) a.push({ label: 'Delete', icon: 'trash', run: function (c) { del([c]); } }); return a; },
      bulk: [].concat(canPub ? [{ label: 'Approve', icon: 'check', run: function (l) { setStatus(l, 'approved'); } }, { label: 'Reject', icon: 'x', run: function (l) { setStatus(l, 'rejected'); } }, { label: 'Mark spam', run: function (l) { setStatus(l, 'spam'); } }] : [], canDel ? [{ label: 'Delete', icon: 'trash', cls: 'a-btn-danger', run: del }] : [])
    });
    AGP.mount(root, AGP.head({ eyebrow: 'Audience', title: 'Comments', sub: 'Approval workflow for reader comments. Toggle "require approval" in Settings.' }), tEl);
  });
})();
