@extends('admin.layout')
@section('title', 'Slide')
@section('content')
@php($kind = old('kind', $slide->exists && ! $slide->post_id ? 'free' : 'article'))
<h1 class="mb-4 text-2xl font-bold">{{ $slide->exists ? 'Edit slide' : 'New slide' }}</h1>
@if ($errors->any())<ul class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
<form method="POST" enctype="multipart/form-data" action="{{ $slide->exists ? route('admin.featured.update', $slide) : route('admin.featured.store') }}" class="space-y-6 text-sm">
@csrf @if ($slide->exists) @method('PUT') @endif
<fieldset class="grid gap-3 rounded border bg-white p-4 sm:grid-cols-2 dark:bg-slate-800"><legend class="px-1 font-semibold">Slide</legend>
<div class="sm:col-span-2"><span class="block font-medium">Kind</span>
<label class="mr-4"><input type="radio" name="kind" value="article" @checked($kind === 'article')> From an article (image, title, summary and link taken from it; fields below override)</label>
<label><input type="radio" name="kind" value="free" @checked($kind === 'free')> Free slide (own image and texts)</label></div>
<div class="sm:col-span-2"><label class="block font-medium" for="post_id">Article</label><select class="w-full rounded border px-2 py-1" id="post_id" name="post_id"><option value="">—</option>@foreach ($posts as $p)<option value="{{ $p->id }}" @selected((int) old('post_id', $slide->post_id) === $p->id)>{{ $p->published_at?->format('Y-m-d') }} · {{ $p->translation('fr')?->title }}</option>@endforeach</select></div>
<div><label class="block font-medium" for="media_id">Image from the library (free slide, or to replace the article cover)</label><select class="w-full rounded border px-2 py-1" id="media_id" name="media_id"><option value="">— article cover —</option>@foreach ($images as $m)<option value="{{ $m->id }}" @selected((int) old('media_id', $slide->media_id) === $m->id)>#{{ $m->id }} {{ $m->alt_fr ?: basename($m->path) }}</option>@endforeach</select></div>
<div><label class="block font-medium" for="upload">…or upload a new image (JPEG, PNG, WebP; converted to WebP)</label><input id="upload" name="upload" type="file" accept="image/jpeg,image/png,image/webp"></div>
<div><label class="block font-medium" for="status">Status</label><select class="rounded border px-2 py-1" id="status" name="status"><option value="draft" @selected(old('status', $slide->status) === 'draft')>draft (hidden)</option>@if ($canPublish || $slide->status === 'published')<option value="published" @selected(old('status', $slide->status) === 'published')>published</option>@endif</select></div>
<div class="flex gap-3"><div><label class="block font-medium" for="starts_at">Show from (Benin time)</label><input class="rounded border px-2 py-1" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $slide->starts_at?->format('Y-m-d\TH:i')) }}"></div>
<div><label class="block font-medium" for="ends_at">Until</label><input class="rounded border px-2 py-1" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $slide->ends_at?->format('Y-m-d\TH:i')) }}"></div></div>
</fieldset>
@foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
@php($tr = $slide->exists ? $slide->translations->firstWhere('locale', $l) : null)
<fieldset class="rounded border bg-white p-4 dark:bg-slate-800" lang="{{ $l }}"><legend class="px-1 font-semibold">{{ $label }} {{ $l === 'en' ? '(optional: French is shown when empty)' : '' }}</legend>
@foreach (['title' => 'Title', 'text' => 'Text', 'cta' => 'Button label', 'url' => 'Button link (/fr/... or https://...)', 'alt' => 'Image alternative text'] as $f => $fl)
<label class="block font-medium" for="{{ $l }}-{{ $f }}">{{ $fl }}</label>
@if ($f === 'text')<textarea class="mb-2 w-full rounded border px-2 py-1" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" rows="2">{{ old("translations.$l.$f", $tr?->$f) }}</textarea>
@else<input class="mb-2 w-full rounded border px-2 py-1" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $tr?->$f) }}">@endif
@endforeach
</fieldset>
@endforeach
<button class="rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save</button>
</form>
@endsection
