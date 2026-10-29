@extends('admin.layout')
@section('title', $post->exists ? 'Edit post' : 'New post')
@section('page', 'post-edit')
@push('head')<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">@endpush
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Editor</p><h1>{{ $post->exists ? 'Edit post' : 'New post' }}</h1><p>French is required; English is optional (the French version is shown until it exists).</p></div></div>
<form method="POST" data-rte-form action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="space-y-6">
    @csrf @if ($post->exists) @method('PUT') @endif
    @foreach (['fr' => 'Français (required)', 'en' => 'English (optional)'] as $l => $label)
        @php($t = $post->exists ? $post->translation($l) : null)
        @php($t = $t?->locale === $l ? $t : null)
        <fieldset class="a-card a-card-b mb-4" lang="{{ $l }}">
            <legend class="a-eyebrow">{{ $label }}</legend>
            @foreach (['title' => 'Title', 'slug' => 'Slug (blank = from title)', 'excerpt' => 'Excerpt', 'body' => 'Body', 'seo_title' => 'SEO title', 'seo_description' => 'SEO description'] as $f => $fl)
                <label class="a-label" for="{{ $l }}-{{ $f }}">{{ $fl }}</label>
                @if ($f === 'body')
                    {{-- Quill rich-text editor (spec EX-48); the hidden textarea carries its semantic HTML, sanitised by Purifier on save. --}}
                    <div class="a-rte mb-3"><div data-rte data-target="{{ $l }}-body" lang="{{ $l }}" style="min-height:320px">{!! clean(old("translations.$l.body", $t?->body)) !!}</div></div>
                    <textarea id="{{ $l }}-body" name="translations[{{ $l }}][body]" hidden>{{ old("translations.$l.body", $t?->body) }}</textarea>
                @elseif (in_array($f, ['excerpt', 'seo_description']))
                    <textarea class="a-textarea" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" rows="{{ $f === 'body' ? 12 : 2 }}">{{ old("translations.$l.$f", $t?->$f) }}</textarea>
                @else
                    <input class="a-input" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $t?->$f) }}">
                @endif
            @endforeach
        </fieldset>
    @endforeach
    <fieldset class="a-card a-card-b a-grid2">
        <legend class="a-eyebrow">Publishing</legend>
        <div class="sm:col-span-2"><span class="a-label">Cover image</span><livewire:media-picker name="cover_id" :value="old('cover_id', $post->cover_id)" /></div>
        <div><label class="a-label" for="status">Status</label>
            <select class="a-select" id="status" name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $post->status) === $s)>{{ $s }}</option>@endforeach</select></div>
        <div><label class="a-label" for="published_at">Publication date (future = scheduled)</label>
            <input class="a-input" id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="a-label" for="categories">Categories</label>
            <select class="a-select" id="categories" name="categories[]" multiple size="6">
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(collect(old('categories', $post->exists ? $post->categories->pluck('id')->all() : []))->contains($c->id))>{{ $c->translation('fr')?->name ?? $c->key }}</option>@endforeach
            </select></div>
        <div><label class="a-label" for="tags">Tags (comma separated)</label>
            <input class="a-input" id="tags" name="tags" value="{{ old('tags', $post->exists ? $post->tags->pluck('name')->implode(', ') : '') }}">
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked(old('featured', $post->featured))> Featured</label></div>
    </fieldset>
    <button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@if ($post->exists)
    @can('delete', $post)
    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="mt-4">@csrf @method('DELETE')
        <button class="a-btn a-btn-sm a-btn-danger" type="submit">Move to trash</button></form>
    @endcan
@endif
<livewire:media-picker mode="insert" wire:key="rte-insert" />
@endsection
@push('scripts')
<script>
(function () {
  // Quill is loaded here, not with a <script src>: after a wire:navigate visit an inline script can run before an
  // external one has loaded, so the editor is created only once the library is there.
  function withQuill(cb) {
    if (window.Quill) return cb();
    var s = document.querySelector('script[data-quill]');
    if (!s) { s = document.createElement('script'); s.src = '/assets/vendor/quill/quill.js'; s.dataset.quill = '1'; document.head.appendChild(s); }
    s.addEventListener('load', cb);
  }
  // One Quill per language; the image button opens the media library (insert mode) instead of asking for a URL.
  var editors = [], active = null;
  function init() {
    document.querySelectorAll('[data-rte]').forEach(function (el) {
      if (el.__quill) return;
      var q = new Quill(el, { theme: 'snow', modules: { toolbar: { container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], ['blockquote', { list: 'ordered' }, { list: 'bullet' }], ['image', 'clean']],
        handlers: { image: function () { active = q; window.Livewire.dispatch('open-media-picker'); } } } } });
      el.__quill = q; editors.push(q);
      // Quill's toolbar has icons only: give every control an accessible name (axe: aria-command-name, button-name).
      var tb = q.getModule('toolbar').container, names = { 'ql-bold': 'Bold', 'ql-italic': 'Italic', 'ql-underline': 'Underline', 'ql-link': 'Link',
        'ql-blockquote': 'Quote', 'ql-image': 'Insert image from the media library', 'ql-clean': 'Clear formatting' };
      tb.setAttribute('aria-label', 'Text formatting (' + el.lang + ')');
      tb.querySelectorAll('button').forEach(function (bt) {
        var c = [].find.call(bt.classList, function (k) { return names[k]; });
        bt.setAttribute('aria-label', c ? names[c] : (bt.value === 'ordered' ? 'Numbered list' : bt.value === 'bullet' ? 'Bulleted list' : 'Format'));
      });
      tb.querySelectorAll('.ql-picker-label').forEach(function (l) { l.setAttribute('aria-label', 'Paragraph style'); });
      q.on('text-change', function () { active = q; });
    });
    var form = document.querySelector('[data-rte-form]');
    if (form && !form.__rte) {
      form.__rte = true;
      form.addEventListener('submit', function () {
        document.querySelectorAll('[data-rte]').forEach(function (el) {
          // Semantic HTML (real <ul>/<ol>, no Quill classes): what Purifier and the public .prose styles expect.
          document.getElementById(el.dataset.target).value = el.__quill.getLength() > 1 ? el.__quill.getSemanticHTML() : '';
        });
      });
    }
  }
  window.addEventListener('media-picked', function (e) {
    var q = active || editors[0]; if (!q) return;
    var r = q.getSelection(true) || { index: q.getLength() };
    q.insertEmbed(r.index, 'image', e.detail.url, 'user');
    var img = q.root.querySelector('img[src="' + e.detail.url + '"]'); if (img && e.detail.alt) img.setAttribute('alt', e.detail.alt);
  });
  var start = function () { withQuill(init); };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
  document.addEventListener('livewire:navigated', start);
})();
</script>
@endpush
