@extends('admin.layout')
@section('title', $type)
@section('content')
@php($module = $def['module'])
<h1 class="mb-4 text-2xl font-bold">{{ $item->exists ? 'Edit' : 'New' }} {{ str_replace('_', ' ', $type) }}</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<ul class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
<form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.media-items.update', [$type, $item]) : route('admin.media-items.store', $type) }}" class="space-y-6 text-sm">
@csrf @if ($item->exists) @method('PUT') @endif
<fieldset class="grid gap-3 rounded border bg-white p-4 sm:grid-cols-2 dark:bg-slate-800"><legend class="px-1 font-semibold">General</legend>
<div><label class="block font-medium" for="key">Key</label><input class="w-full rounded border px-2 py-1" id="key" name="key" value="{{ old('key', $item->key) }}" required></div>
<div><label class="block font-medium" for="status">Status</label><select class="rounded border px-2 py-1" id="status" name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $item->status) === $s)>{{ $s }}</option>@endforeach</select></div>
@unless (in_array($type, ['radio', 'tv', 'podcast_show']))
<div><label class="block font-medium" for="published_on">Date</label><input class="rounded border px-2 py-1" id="published_on" name="published_on" type="date" value="{{ old('published_on', $item->published_on?->toDateString()) }}"></div>
<div><label class="block font-medium" for="post_id">{{ $type === 'magazine' ? 'Cover story' : 'Linked article' }}</label><select class="w-full rounded border px-2 py-1" id="post_id" name="post_id"><option value="">—</option>@foreach ($posts as $p)<option value="{{ $p->id }}" @selected((int) old('post_id', $item->post_id) === $p->id)>#{{ $p->id }} {{ $p->translation('fr')?->title }}</option>@endforeach</select></div>
@endunless
@foreach ($def['data'] as $field => $input)
<div><label class="block font-medium" for="d-{{ $field }}">{{ ucfirst($field) }}</label>
@if ($input === 'show')
<select class="rounded border px-2 py-1" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach ($shows as $s)<option value="{{ $s->key }}" @selected(old("data.$field", $item->get($field)) === $s->key)>{{ $s->translation('fr')?->title }}</option>@endforeach</select>
@elseif ($input === 'topic')
<select class="rounded border px-2 py-1" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach (__('media.topics', [], 'fr') as $k => $label)<option value="{{ $k }}" @selected(old("data.$field", $item->get($field)) === $k)>{{ $label }}</option>@endforeach</select>
@elseif ($input === 'genre')
<select class="rounded border px-2 py-1" id="d-{{ $field }}" name="data[{{ $field }}]">@foreach (__('media.genres', [], 'fr') as $k => $label)<option value="{{ $k }}" @selected(old("data.$field", $item->get($field)) === $k)>{{ $label }}</option>@endforeach</select>
@else
<input class="w-full rounded border px-2 py-1" id="d-{{ $field }}" name="data[{{ $field }}]" type="{{ ['number' => 'number', 'url' => 'url'][$input] ?? 'text' }}" value="{{ old("data.$field", $item->get($field)) }}">
@if ($input === 'file')<input class="mt-1" name="upload[{{ $field }}]" type="file" aria-label="Upload {{ $field }}">@endif
@endif
</div>
@endforeach
@if ($type === 'magazine')
<div class="sm:col-span-2"><label class="block font-medium" for="articles">Contents: post ids in order (e.g. 12, 5, 7)</label><input class="w-full rounded border px-2 py-1" id="articles" name="articles" value="{{ old('articles', $item->exists ? $item->posts->pluck('id')->implode(', ') : '') }}"></div>
@endif
</fieldset>
@foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
@php($tr = $item->exists ? $item->translations->firstWhere('locale', $l) : null)
<fieldset class="rounded border bg-white p-4 dark:bg-slate-800" lang="{{ $l }}"><legend class="px-1 font-semibold">{{ $label }}</legend>
@if (in_array('title', $def['translated']))<label class="block font-medium" for="{{ $l }}-title">Title</label><input class="mb-2 w-full rounded border px-2 py-1" id="{{ $l }}-title" name="translations[{{ $l }}][title]" value="{{ old("translations.$l.title", $tr?->title) }}">@endif
<label class="block font-medium" for="{{ $l }}-description">Description</label><textarea class="mb-2 w-full rounded border px-2 py-1" id="{{ $l }}-description" name="translations[{{ $l }}][description]" rows="3">{{ old("translations.$l.description", $tr?->description) }}</textarea>
@if (in_array('file', $def['translated']))<label class="block font-medium" for="{{ $l }}-file">Audio file ({{ $l }})</label><input class="w-full rounded border px-2 py-1" id="{{ $l }}-file" name="translations[{{ $l }}][file]" value="{{ old("translations.$l.file", $tr?->file) }}"><input class="mt-1" name="upload_{{ $l }}" type="file" accept="audio/mpeg" aria-label="Upload audio {{ $l }}">@endif
</fieldset>
@endforeach
<button class="rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save</button>
</form>
@if ($item->exists)@can("$module.delete")<form method="POST" action="{{ route('admin.media-items.destroy', [$type, $item]) }}" class="mt-4">@csrf @method('DELETE')<button class="rounded border border-red-700 px-3 py-1 text-sm text-red-700" type="submit">Delete</button></form>@endcan @endif
@endsection
