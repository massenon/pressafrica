@extends('admin.layout')
@section('title', 'Posts')
@section('content')
<div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Posts</h1>
    @can('create', App\Models\Post::class)<a class="rounded bg-slate-900 px-3 py-2 text-sm font-semibold text-white" href="{{ route('admin.posts.create') }}">New post</a>@endcan
</div>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input class="rounded border px-2 py-1" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search titles" aria-label="Search titles">
    <select class="rounded border px-2 py-1" name="status" aria-label="Status"><option value="">All statuses</option>
        @foreach (App\Models\Post::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach
    </select>
    <button class="rounded border px-3 py-1" type="submit">Filter</button>
</form>
<table class="w-full border-collapse bg-white text-sm dark:bg-slate-800">
    <thead><tr class="border-b text-left"><th class="p-2">Title</th><th class="p-2">EN</th><th class="p-2">Author</th><th class="p-2">Status</th><th class="p-2">Date</th></tr></thead>
    <tbody>
    @forelse ($posts as $p)
        <tr class="border-b">
            <td class="p-2">@can('update', $p)<a class="underline" href="{{ route('admin.posts.edit', $p) }}">{{ $p->translation('fr')?->title }}</a>@else{{ $p->translation('fr')?->title }}@endcan</td>
            <td class="p-2">{{ $p->translations->contains('locale', 'en') ? '✓' : '—' }}</td>
            <td class="p-2">{{ $p->author?->name }}</td><td class="p-2">{{ $p->status }}</td>
            <td class="p-2 whitespace-nowrap">{{ $p->published_at?->format('Y-m-d H:i') }}</td>
        </tr>
    @empty
        <tr><td class="p-2" colspan="5">No posts.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
