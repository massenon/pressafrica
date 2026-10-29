@extends('admin.layout')
@section('title', $type)
@section('content')
@php($module = App\Models\MediaItem::TYPES[$type]['module'])
<div class="a-page-head"><div><h1>{{ str_replace('_', ' ', ucfirst($type)) }}</h1></div>
@can("$module.create")<a class="a-btn a-btn-primary" href="{{ route('admin.media-items.create', $type) }}">New</a>@endcan</div>
@if ($type === 'podcast')<p class="mb-3 text-sm"><a class="ttl" href="{{ route('admin.media-items.index', 'podcast_show') }}">Manage shows</a></p>@endif
@if (in_array($type, ['magazine', 'radio', 'tv']))<p class="a-badge warn mb-3">Demonstration content until the editor-in-chief validates it (PRD EX-02, Q4).</p>@endif
<nav class="mb-4 flex gap-2 text-sm" aria-label="Status"><a href="{{ route('admin.media-items.index', $type) }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => ! $status])>all</a>@foreach (App\Http\Controllers\Admin\MediaItemController::STATUSES as $s)<a href="{{ route('admin.media-items.index', [$type, 'status' => $s]) }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => $status === $s])>{{ $s }}</a>@endforeach</nav>
<div class="a-card"><div class="a-tablewrap"><table class="a-table">
<thead><tr><th>Key</th><th>Title / name (FR)</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
@forelse ($items as $i)
<tr><td>@can("$module.edit")<a class="ttl" href="{{ route('admin.media-items.edit', [$type, $i]) }}">{{ $i->key }}</a>@else{{ $i->key }}@endcan</td><td>{{ $i->translation('fr')?->title ?? $i->get('name') }}</td><td>{{ $i->status }}</td><td>{{ $i->published_on?->toDateString() }}</td></tr>
@empty
<tr><td colspan="4">Nothing yet.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
