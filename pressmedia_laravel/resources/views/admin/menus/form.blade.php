@extends('admin.layout')
@section('title', 'Menu entry')
@section('page', 'menus')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Menus</p><h1>{{ $item->exists ? 'Edit entry' : 'New entry' }}</h1></div></div>
<form method="POST" action="{{ $item->exists ? route('admin.menus.update', $item) : route('admin.menus.store') }}">
@csrf @if ($item->exists) @method('PUT') @endif
<section class="a-card a-card-b mb-4"><div class="a-grid2">
<div class="a-field"><label class="a-label" for="key">Key (panel id)</label><input class="a-input" id="key" name="key" value="{{ old('key', $item->key) }}" required></div>
<div class="a-field"><label class="a-label" for="panel">Drop-down panel</label><select class="a-select" id="panel" name="panel">@foreach (['none' => 'None (plain link)', 'articles' => 'Latest articles', 'podcasts' => 'Latest podcast episodes', 'videos' => 'Latest videos', 'press' => 'Press: intro, picks and latest'] as $v => $l)<option value="{{ $v }}" @selected(old('panel', $item->panel) === $v)>{{ $l }}</option>@endforeach</select></div>
<div class="a-field"><label class="a-label" for="category_id">Section (link and article source)</label><select class="a-select" id="category_id" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $item->category_id) === $c->id)>{{ $c->translation('fr')?->name }}</option>@endforeach</select></div>
<div class="a-field"><label class="a-check"><input type="hidden" name="from_category" value="0"><input type="checkbox" name="from_category" value="1" @checked(old('from_category', $item->data['from_category'] ?? false))><span>Latest articles of this section only (else all sections)</span></label>
<label class="a-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $item->active))><span>Shown on the site</span></label></div>
<div class="a-field" style="grid-column:1/-1"><span class="a-label">"Best news" picks of the press panel (up to 3)</span><livewire:post-picker name="picks" :multiple="true" :value="old('picks', $item->data['picks'] ?? [])" /></div>
</div></section>
@foreach (['fr' => 'Français', 'en' => 'English (optional: French shown when empty)'] as $l => $label)
@php($tr = $item->exists ? $item->translations->firstWhere('locale', $l) : null)
<section class="a-card a-card-b mb-4" lang="{{ $l }}"><p class="a-eyebrow">{{ $label }}</p><div class="a-grid2">
<div class="a-field"><label class="a-label" for="{{ $l }}-label">Label</label><input class="a-input" id="{{ $l }}-label" name="translations[{{ $l }}][label]" value="{{ old("translations.$l.label", $tr?->label) }}"></div>
<div class="a-field"><label class="a-label" for="{{ $l }}-url">Link (blank = the section page; /{{ $l }}/... or https://...)</label><input class="a-input" id="{{ $l }}-url" name="translations[{{ $l }}][url]" value="{{ old("translations.$l.url", $tr?->url) }}"></div>
</div></section>
@endforeach
<button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@endsection
