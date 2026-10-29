@extends('admin.layout')
@section('title', 'Comments')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Comments</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
<nav class="mb-4 flex gap-2 text-sm" aria-label="Comment status">
    @foreach (App\Models\Comment::STATUSES as $s)
        <a href="{{ route('admin.comments.index', ['status' => $s]) }}" @class(['rounded border px-3 py-1', 'bg-slate-900 text-white' => $status === $s]) @if ($status === $s) aria-current="page" @endif>{{ $s }} ({{ $counts[$s] ?? 0 }})</a>
    @endforeach
</nav>
<ul class="space-y-3">
@forelse ($comments as $c)
    <li class="rounded border bg-white p-3 text-sm dark:bg-slate-800">
        <p class="mb-1 text-slate-500">{{ $c->author }} &lt;{{ $c->email }}&gt; · {{ $c->created_at->format('Y-m-d H:i') }} · {{ strtoupper($c->locale) }} · {{ $c->post?->translation($c->locale)?->title }}</p>
        <p class="mb-2 whitespace-pre-line">{{ $c->body }}</p>
        <div class="flex gap-2">
            @if ($c->status !== 'approved')@can('comments.publish')<form method="POST" action="{{ route('admin.comments.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="rounded border px-2" type="submit">Approve</button></form>@endcan @endif
            @if ($c->status !== 'spam')@can('comments.edit')<form method="POST" action="{{ route('admin.comments.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="status" value="spam"><button class="rounded border px-2" type="submit">Spam</button></form>@endcan @endif
            @can('comments.delete')<form method="POST" action="{{ route('admin.comments.destroy', $c) }}">@csrf @method('DELETE')<button class="rounded border border-red-700 px-2 text-red-700" type="submit">Delete</button></form>@endcan
        </div>
    </li>
@empty
    <li class="text-sm">No {{ $status }} comments.</li>
@endforelse
</ul>
<div class="mt-4">{{ $comments->links() }}</div>
@endsection
