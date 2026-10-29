@extends('admin.layout')
@section('title', 'Posts')
@section('page', 'posts')
@section('content')
@php($badge = ['published' => 'ok', 'scheduled' => 'info', 'pending' => 'warn', 'draft' => ''])
<div class="a-page-head"><div><p class="a-eyebrow">Newsroom</p><h1>Posts</h1><p>Search, filter, review and publish.</p></div>
<div class="flex flex-wrap gap-2">@can('create', App\Models\Post::class)<a class="a-btn a-btn-primary" href="{{ route('admin.posts.create') }}" wire:navigate><span>New post</span></a>@endcan</div></div>
<section aria-label="Posts list" class="a-card">
<form method="GET" class="a-tools">
    <div class="a-field" style="min-width:220px;flex:1 1 0%"><label class="a-label" for="pq">Search</label><input class="a-input" type="search" id="pq" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by title…" autocomplete="off"></div>
    <div class="a-field"><label class="a-label" for="pstatus">Status</label><select class="a-select" id="pstatus" name="status" onchange="this.form.requestSubmit()"><option value="">All</option>@foreach (App\Models\Post::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="a-field" style="align-self:end"><button class="a-btn" type="submit">Filter</button></div>
</form>
<div class="a-tablewrap"><table class="a-table" aria-label="Posts">
<thead><tr><th scope="col">Post</th><th scope="col" class="text-sm">Author</th><th scope="col">Categories</th><th scope="col">Lang</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col" class="text-right"><span class="a-vh">Actions</span></th></tr></thead>
<tbody>
@forelse ($posts as $p)
@php($t = $p->translation('fr'))
<tr><td><div class="flex gap-3 items-center"><span class="a-thumb" aria-hidden="true">@if ($p->cover)<img src="{{ $p->cover->url() }}" alt="" loading="lazy" class="w-full h-full object-cover">@endif</span><div>@can('update', $p)<a class="ttl" href="{{ route('admin.posts.edit', $p) }}" wire:navigate>{{ $t?->title }}</a>@else<span class="ttl">{{ $t?->title }}</span>@endcan<span class="a-muted text-xs">/{{ $t?->slug }}</span></div></div></td>
<td class="text-sm">{{ $p->author?->name }}</td>
<td>{{ $p->categories->map(fn ($c) => $c->translation('fr')?->name)->implode(', ') }}</td>
<td>@foreach ($p->translations->sortBy('locale') as $tr)<span class="a-badge">{{ strtoupper($tr->locale) }}</span> @endforeach</td>
<td><span class="a-badge {{ $badge[$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span></td>
<td class="whitespace-nowrap">{{ $p->published_at?->format('j M Y') }}</td>
<td class="text-right whitespace-nowrap">@if ($p->status === 'published' && $p->url('fr'))<a class="a-btn a-btn-sm a-btn-ghost" href="{{ $p->url('fr') }}" target="_blank" rel="noopener">View</a>@endif @can('update', $p)<a class="a-btn a-btn-sm" href="{{ route('admin.posts.edit', $p) }}" wire:navigate>Edit</a>@endcan</td></tr>
@empty
<tr><td colspan="7">No posts.</td></tr>
@endforelse
</tbody></table></div></section>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
