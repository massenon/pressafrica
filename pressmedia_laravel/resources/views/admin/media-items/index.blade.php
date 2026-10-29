@extends('admin.layout')
@section('title', $type)
@section('content')
@php($module = App\Models\MediaItem::TYPES[$type]['module'])
<div class="mb-4 flex items-center justify-between"><h1 class="text-2xl font-bold">{{ str_replace('_', ' ', ucfirst($type)) }}</h1>
@can("$module.create")<a class="rounded bg-slate-900 px-3 py-2 text-sm font-semibold text-white" href="{{ route('admin.media-items.create', $type) }}">New</a>@endcan</div>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($type === 'podcast')<p class="mb-3 text-sm"><a class="underline" href="{{ route('admin.media-items.index', 'podcast_show') }}">Manage shows</a></p>@endif
@if (in_array($type, ['magazine', 'radio', 'tv']))<p class="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900">Demonstration content until the editor-in-chief validates it (PRD EX-02, Q4).</p>@endif
<nav class="mb-4 flex gap-2 text-sm" aria-label="Status"><a href="{{ route('admin.media-items.index', $type) }}" @class(['rounded border px-3 py-1', 'bg-slate-900 text-white' => ! $status])>all</a>@foreach (App\Http\Controllers\Admin\MediaItemController::STATUSES as $s)<a href="{{ route('admin.media-items.index', [$type, 'status' => $s]) }}" @class(['rounded border px-3 py-1', 'bg-slate-900 text-white' => $status === $s])>{{ $s }}</a>@endforeach</nav>
<table class="w-full border-collapse bg-white text-sm dark:bg-slate-800">
<thead><tr class="border-b text-left"><th class="p-2">Key</th><th class="p-2">Title / name (FR)</th><th class="p-2">Status</th><th class="p-2">Date</th></tr></thead>
<tbody>
@forelse ($items as $i)
<tr class="border-b"><td class="p-2">@can("$module.edit")<a class="underline" href="{{ route('admin.media-items.edit', [$type, $i]) }}">{{ $i->key }}</a>@else{{ $i->key }}@endcan</td><td class="p-2">{{ $i->translation('fr')?->title ?? $i->get('name') }}</td><td class="p-2">{{ $i->status }}</td><td class="p-2">{{ $i->published_on?->toDateString() }}</td></tr>
@empty
<tr><td class="p-2" colspan="4">Nothing yet.</td></tr>
@endforelse
</tbody></table>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
