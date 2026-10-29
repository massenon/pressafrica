@extends('admin.layout')
@section('title', $type)
@section('content')
@php($module = $def['module'])
<div class="a-page-head"><div><h1>{{ $item->exists ? 'Edit' : 'New' }} {{ str_replace('_', ' ', $type) }}</h1></div></div>
<form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.media-items.update', [$type, $item]) : route('admin.media-items.store', $type) }}" class="space-y-6 text-sm">
@csrf @if ($item->exists) @method('PUT') @endif
<fieldset class="a-card a-card-b a-grid2"><legend class="a-eyebrow">General</legend>
<div><label class="a-label" for="key">Key</label><input class="a-input" id="key" name="key" value="{{ old('key', $item->key) }}" required></div>
<div><label class="a-label" for="status">Status</label><select class="a-select" id="status" name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $item->status) === $s)>{{ $s }}</option>@endforeach</select></div>
@unless (in_array($type, ['radio', 'tv', 'podcast_show']))
<div><label class="a-label" for="published_on">Date</label><input class="a-input" id="published_on" name="published_on" type="date" value="{{ old('published_on', $item->published_on?->toDateString()) }}"></div>
<div class="sm:col-span-2"><span class="a-label">{{ $type === 'magazine' ? 'Cover story' : 'Linked article' }} (search by title)</span><livewire:post-picker name="post_id" :value="old('post_id', $item->post_id)" /></div>
@endunless
@foreach ($def['data'] as $field => $input)
<div><label class="a-label" for="d-{{ $field }}">{{ ucfirst($field) }}</label>
@if ($input === 'show')
<select class="a-select" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach ($shows as $s)<option value="{{ $s->key }}" @selected(old("data.$field", $item->get($field)) === $s->key)>{{ $s->translation('fr')?->title }}</option>@endforeach</select>
@elseif ($input === 'topic')
<select class="a-select" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach (__('media.topics', [], 'fr') as $k => $label)<option value="{{ $k }}" @selected(old("data.$field", $item->get($field)) === $k)>{{ $label }}</option>@endforeach</select>
@elseif ($input === 'genre')
<select class="a-select" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach (__('media.genres', [], 'fr') as $k => $label)<option value="{{ $k }}" @selected(old("data.$field", $item->get($field)) === $k)>{{ $label }}</option>@endforeach</select>
@else
<input class="a-input" id="d-{{ $field }}" name="data[{{ $field }}]" type="{{ ['number' => 'number', 'url' => 'url'][$input] ?? 'text' }}" value="{{ old("data.$field", $item->get($field)) }}">
@if ($input === 'file')<input class="mt-1" name="upload[{{ $field }}]" type="file" aria-label="Upload {{ $field }}">@endif
@endif
</div>
@endforeach
@if ($type === 'magazine')
<div class="sm:col-span-2"><span class="a-label">Contents, in order (search by title)</span><livewire:post-picker name="articles" :multiple="true" :value="old('articles', $item->exists ? $item->posts->pluck('id')->all() : [])" /></div>
@endif
</fieldset>
@foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
@php($tr = $item->exists ? $item->translations->firstWhere('locale', $l) : null)
<fieldset class="a-card a-card-b mb-4" lang="{{ $l }}"><legend class="a-eyebrow">{{ $label }}</legend>
@if (in_array('title', $def['translated']))<label class="a-label" for="{{ $l }}-title">Title</label><input class="a-input" id="{{ $l }}-title" name="translations[{{ $l }}][title]" value="{{ old("translations.$l.title", $tr?->title) }}">@endif
<label class="a-label" for="{{ $l }}-description">Description</label><textarea class="a-textarea" id="{{ $l }}-description" name="translations[{{ $l }}][description]" rows="3">{{ old("translations.$l.description", $tr?->description) }}</textarea>
@if (in_array('file', $def['translated']))<label class="a-label" for="{{ $l }}-file">Audio file ({{ $l }})</label><input class="a-input" id="{{ $l }}-file" name="translations[{{ $l }}][file]" value="{{ old("translations.$l.file", $tr?->file) }}"><input class="mt-1" name="upload_{{ $l }}" type="file" accept="audio/mpeg" aria-label="Upload audio {{ $l }}">@endif
</fieldset>
@endforeach
<button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@if ($item->exists)@can("$module.delete")<form method="POST" action="{{ route('admin.media-items.destroy', [$type, $item]) }}" class="mt-4">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan @endif
@endsection
