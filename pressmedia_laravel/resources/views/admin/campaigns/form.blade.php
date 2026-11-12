@extends('admin.layout')
@section('title', 'Campaign')
@section('page', 'newsletter')
@push('head')<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">@endpush
@section('content')
@php($editable = ! $c->exists || in_array($c->status, ['draft', 'cancelled'], true))
<div class="a-page-head"><div><p class="a-eyebrow">Newsletter</p><h1>{{ $c->exists ? $c->subject : 'New campaign' }}</h1>@if ($c->exists)<p>Status: <span class="a-badge">{{ $c->status }}</span> · {{ $c->sent_count }} sent · {{ $c->failed_count }} failed</p>@endif</div></div>
<form method="POST" action="{{ $c->exists ? route('admin.campaigns.update', $c) : route('admin.campaigns.store') }}" data-rte-form>@csrf @if ($c->exists) @method('PUT') @endif
<fieldset @disabled(! $editable) style="border:0;padding:0;margin:0">
<section class="a-card a-card-b mb-4"><div class="a-field"><label class="a-label" for="subject">Subject</label><input class="a-input" id="subject" name="subject" value="{{ old('subject', $c->subject) }}" required maxlength="200"></div>
<div class="a-grid2"><div class="a-field"><label class="a-label" for="locale">Audience language</label><select class="a-select" id="locale" name="locale">@foreach (['all' => 'All subscribers', 'fr' => 'French subscribers', 'en' => 'English subscribers'] as $v => $l)<option value="{{ $v }}" @selected(old('locale', $c->locale) === $v)>{{ $l }}</option>@endforeach</select></div>
<div class="a-field"><span class="a-label">Topics (none ticked = every subscriber)</span>@foreach (['daily' => 'Daily Stories', 'alerts' => 'Alerts', 'full' => 'Full Access'] as $v => $l)<label class="a-check"><input type="checkbox" name="topics[]" value="{{ $v }}" @checked(in_array($v, old('topics', $c->topics ?? []), true))><span>{{ $l }}</span></label>@endforeach</div></div></section>
<section class="a-card a-card-b mb-4"><span class="a-label">Content</span><div class="a-rte"><div data-rte data-target="body" style="min-height:320px">{!! clean(old('body', $c->body)) !!}</div></div><textarea id="body" name="body" hidden>{{ old('body', $c->body) }}</textarea></section>
@if ($editable)<button class="a-btn a-btn-primary" type="submit">Save draft</button>@endif
</fieldset></form>
@if ($c->exists)
<div class="flex flex-wrap gap-2 mt-4">
<form method="POST" action="{{ route('admin.campaigns.test', $c) }}">@csrf<button class="a-btn" type="submit">Send me a test</button></form>
@if ($editable)@can('newsletter.publish')<form method="POST" action="{{ route('admin.campaigns.schedule', $c) }}" class="flex gap-2 items-end" data-confirm="Send this campaign to the subscribers?">@csrf<div class="a-field"><label class="a-label" for="scheduled_at">Send at (Benin time, empty = now)</label><input class="a-input" id="scheduled_at" name="scheduled_at" type="datetime-local"></div><button class="a-btn a-btn-flash" type="submit">Schedule sending</button></form>@endcan @endif
</div>
@endif
@endsection
@push('scripts')
<script>
(function () {
  function withQuill(cb) { if (window.Quill) return cb(); var s = document.querySelector('script[data-quill]'); if (!s) { s = document.createElement('script'); s.src = '/assets/vendor/quill/quill.js'; s.dataset.quill = '1'; document.head.appendChild(s); } s.addEventListener('load', cb); }
  function init() {
    var el = document.querySelector('[data-rte]'); if (!el || el.__quill) return;
    var q = new Quill(el, { theme: 'snow', readOnly: !!el.closest('fieldset[disabled]'), modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], ['blockquote', { list: 'ordered' }, { list: 'bullet' }], ['clean']] } });
    el.__quill = q;
    var tb = q.getModule('toolbar').container; tb.setAttribute('aria-label', 'Text formatting');
    tb.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-label', (b.className.replace('ql-', '') + ' ' + (b.value || '')).trim()); });
    tb.querySelectorAll('.ql-picker-label').forEach(function (l) { l.setAttribute('aria-label', 'Paragraph style'); });
    var f = document.querySelector('[data-rte-form]');
    f && f.addEventListener('submit', function () { document.getElementById('body').value = q.getLength() > 1 ? q.getSemanticHTML() : ''; });
  }
  var start = function () { withQuill(init); };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
  document.addEventListener('livewire:navigated', start);
})();
</script>
@endpush
