@extends('admin.layout')
@section('title', 'Featured')
@section('content')
<div class="a-page-head"><div><h1>Featured · home carousel</h1></div>
<div class="flex gap-2">
@can('featured.create')<a class="a-btn a-btn-primary" href="{{ route('admin.featured.create') }}">New slide</a>@endcan
@can('featured.restore')<form method="POST" action="{{ route('admin.featured.restore') }}" onsubmit="return confirm('Replace the whole carousel with the default 4 slides?')">@csrf<button class="a-btn a-btn-sm a-btn-danger" type="submit">Restore default carousel</button></form>@endcan
</div></div>
@if ($live->isEmpty())<p class="a-badge warn mb-3" role="alert">No slide is on air: the home page shows no carousel.</p>@endif
<p class="mb-3 text-sm a-muted">At most {{ App\Models\HeroSlide::MAX_LIVE }} published slides. Times are Benin time ({{ config('app.timezone') }}).</p>
<ol class="space-y-2">
@forelse ($slides as $s)
@php($v = $s->view('fr'))
<li class="a-card a-card-b mb-4">
    @if ($v['image'])<img src="{{ $v['image']->url() }}" alt="{{ $v['alt'] }}" class="a-thumb object-cover">@else<span class="a-thumb">no image</span>@endif
    <div class="min-w-48 flex-1"><b>{{ $loop->iteration }}. {{ $v['title'] }}</b>
        <div class="a-muted">{{ $s->post_id ? 'Article' : 'Free' }} · {{ $s->status }}@if ($live->contains($s->id)) · <span class="a-badge ok">on air</span>@endif
        @if ($s->starts_at || $s->ends_at) · {{ $s->starts_at?->format('Y-m-d H:i') ?? '…' }} → {{ $s->ends_at?->format('Y-m-d H:i') ?? '…' }}@endif
        @if (! $s->translations->firstWhere('locale', 'en')?->title && ! $s->post_id) · <span class="a-badge warn">no English text (French shown)</span>@endif
        @if ($s->post_id && ! $s->post?->published_at) · <span class="a-badge bad">article not published: hidden</span>@endif</div></div>
    @can('featured.edit')
    <form method="POST" action="{{ route('admin.featured.move', $s) }}">@csrf<input type="hidden" name="dir" value="up"><button class="a-btn a-btn-sm" type="submit" aria-label="Move {{ $v['title'] }} up" @disabled($loop->first)>↑</button></form>
    <form method="POST" action="{{ route('admin.featured.move', $s) }}">@csrf<input type="hidden" name="dir" value="down"><button class="a-btn a-btn-sm" type="submit" aria-label="Move {{ $v['title'] }} down" @disabled($loop->last)>↓</button></form>
    <a class="ttl" href="{{ route('admin.featured.edit', $s) }}">Edit</a>
    @endcan
    @can('featured.delete')<form method="POST" action="{{ route('admin.featured.destroy', $s) }}" onsubmit="return confirm('Delete this slide?')">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan
</li>
@empty
<li class="text-sm">No slide.</li>
@endforelse
</ol>
@endsection
