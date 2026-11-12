@extends('admin.layout')
@section('title', 'Slide')
@section('content')
@php($kind = old('kind', $slide->exists && ! $slide->post_id ? 'free' : 'article'))
<div class="a-page-head"><div><h1>{{ $slide->exists ? 'Edit slide' : 'New slide' }}</h1></div></div>
<form method="POST" enctype="multipart/form-data" action="{{ $slide->exists ? route('admin.featured.update', $slide) : route('admin.featured.store') }}" class="space-y-6 text-sm">
@csrf @if ($slide->exists) @method('PUT') @endif
<fieldset class="a-card a-card-b a-grid2"><legend class="a-eyebrow">Slide</legend>
<div class="sm:col-span-2"><span class="a-label">Kind</span>
<label class="mr-4"><input type="radio" name="kind" value="article" @checked($kind === 'article')> From an article (image, title, summary and link taken from it; fields below override)</label>
<label><input type="radio" name="kind" value="free" @checked($kind === 'free')> Free slide (own image and texts)</label></div>
<div class="sm:col-span-2"><span class="a-label">Article (search by title)</span><livewire:post-picker name="post_id" :value="old('post_id', $slide->post_id)" /></div>
<div class="sm:col-span-2"><span class="a-label">Image (free slide, or to replace the article cover)</span><livewire:media-picker name="media_id" :value="old('media_id', $slide->media_id)" /></div>
<div><label class="a-label" for="status">Status</label><select class="a-select" id="status" name="status"><option value="draft" @selected(old('status', $slide->status) === 'draft')>draft (hidden)</option>@if ($canPublish || $slide->status === 'published')<option value="published" @selected(old('status', $slide->status) === 'published')>published</option>@endif</select></div>
<div class="flex gap-3"><div><label class="a-label" for="starts_at">Show from (Benin time)</label><input class="a-input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $slide->starts_at?->format('Y-m-d\TH:i')) }}"></div>
<div><label class="a-label" for="ends_at">Until</label><input class="a-input" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $slide->ends_at?->format('Y-m-d\TH:i')) }}"></div></div>
</fieldset>
@foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
@php($tr = $slide->exists ? $slide->translations->firstWhere('locale', $l) : null)
<fieldset class="a-card a-card-b mb-4" lang="{{ $l }}"><legend class="a-eyebrow">{{ $label }} {{ $l === 'en' ? '(optional: French is shown when empty)' : '' }}</legend>
@foreach (['title' => 'Title', 'text' => 'Text', 'cta' => 'Button label', 'url' => 'Button link (/fr/... or https://...)', 'alt' => 'Image alternative text'] as $f => $fl)
<label class="a-label" for="{{ $l }}-{{ $f }}">{{ $fl }}</label>
@if ($f === 'text')<textarea class="a-textarea" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" rows="2">{{ old("translations.$l.$f", $tr?->$f) }}</textarea>
@else<input class="a-input" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $tr?->$f) }}">@endif
@endforeach
</fieldset>
@endforeach
<button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@endsection
