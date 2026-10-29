@extends('admin.layout')
@section('title', 'Comments')
@section('content')
<div class="a-page-head"><div><h1>Comments</h1></div></div>
<nav class="mb-4 flex gap-2 text-sm" aria-label="Comment status">
    @foreach (App\Models\Comment::STATUSES as $s)
        <a href="{{ route('admin.comments.index', ['status' => $s]) }}" @class(['a-btn a-btn-sm', 'a-btn-primary' => $status === $s]) @if ($status === $s) aria-current="page" @endif>{{ $s }} ({{ $counts[$s] ?? 0 }})</a>
    @endforeach
</nav>
<ul class="space-y-3">
@forelse ($comments as $c)
    <li class="a-card a-card-b mb-4">
        <p class="mb-1 a-muted">{{ $c->author }} &lt;{{ $c->email }}&gt; · {{ $c->created_at->format('Y-m-d H:i') }} · {{ strtoupper($c->locale) }} · {{ $c->post?->translation($c->locale)?->title }}</p>
        @if ($c->parent)<p class="mb-1 text-xs a-muted">↳ Reply to {{ $c->parent->author }}: « {{ \Illuminate\Support\Str::limit($c->parent->body, 80) }} »</p>@endif
        @if ($c->isStaff())<p class="a-badge info mb-1">Newsroom reply</p>@endif
        <p class="mb-2 whitespace-pre-line">{{ $c->body }}</p>
        @if ($c->notify_consent_at)<p class="mb-1 text-xs a-muted">Reader asked to be e-mailed on a newsroom reply{{ $c->notified_at ? ' (e-mailed '.$c->notified_at->format('Y-m-d H:i').')' : '' }}.</p>@endif
        @foreach ($c->replies as $r)<p class="a-card a-card-b text-sm mb-2"><b>{{ $r->author }} · Newsroom</b>: {{ $r->body }}</p>@endforeach
        <div class="flex flex-wrap gap-2">
            @if ($c->status !== 'approved')@can('comments.publish')<form method="POST" action="{{ route('admin.comments.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="a-btn a-btn-sm" type="submit">Approve</button></form>@endcan @endif
            @if ($c->status !== 'spam')@can('comments.edit')<form method="POST" action="{{ route('admin.comments.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="status" value="spam"><button class="a-btn a-btn-sm" type="submit">Spam</button></form>@endcan @endif
            @if (! $c->parent_id && ! $c->isStaff() && $c->status !== 'spam')@can('comments.publish')
            <details class="w-full"><summary class="a-btn a-btn-sm">Reply publicly</summary>
                <form method="POST" action="{{ route('admin.comments.reply', $c) }}" class="mt-2" @if ($c->status !== 'approved') onsubmit="return confirm('Replying also approves this comment. Continue?')" @endif>@csrf
                    <label class="sr-only" for="reply-{{ $c->id }}">Reply</label><textarea class="a-textarea" id="reply-{{ $c->id }}" name="body" rows="3" required></textarea>
                    <button class="a-btn a-btn-primary" type="submit">Publish reply as {{ auth()->user()->name }} (Newsroom)</button></form></details>
            @endcan @endif
            @can('comments.delete')<form method="POST" action="{{ route('admin.comments.destroy', $c) }}">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan
        </div>
    </li>
@empty
    <li class="text-sm">No {{ $status }} comments.</li>
@endforelse
</ul>
<div class="mt-4">{{ $comments->links() }}</div>
@endsection
