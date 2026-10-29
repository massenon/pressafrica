@extends('admin.layout')
@section('title', 'Featured')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h1 class="text-2xl font-bold">Featured · home carousel</h1>
<div class="flex gap-2">
@can('featured.create')<a class="rounded bg-slate-900 px-3 py-2 text-sm font-semibold text-white" href="{{ route('admin.featured.create') }}">New slide</a>@endcan
@can('featured.restore')<form method="POST" action="{{ route('admin.featured.restore') }}" onsubmit="return confirm('Replace the whole carousel with the default 4 slides?')">@csrf<button class="rounded border border-red-700 px-3 py-2 text-sm text-red-700" type="submit">Restore default carousel</button></form>@endcan
</div></div>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($live->isEmpty())<p class="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900" role="alert">No slide is on air: the home page shows no carousel.</p>@endif
<p class="mb-3 text-sm text-slate-600">At most {{ App\Models\HeroSlide::MAX_LIVE }} published slides. Times are Benin time ({{ config('app.timezone') }}).</p>
<ol class="space-y-2">
@forelse ($slides as $s)
@php($v = $s->view('fr'))
<li class="flex flex-wrap items-center gap-3 rounded border bg-white p-2 text-sm dark:bg-slate-800">
    @if ($v['image'])<img src="{{ $v['image']->url() }}" alt="{{ $v['alt'] }}" class="h-14 w-24 rounded object-cover">@else<span class="flex h-14 w-24 items-center justify-center rounded bg-slate-100 text-xs">no image</span>@endif
    <div class="min-w-48 flex-1"><b>{{ $loop->iteration }}. {{ $v['title'] }}</b>
        <div class="text-slate-600">{{ $s->post_id ? 'Article' : 'Free' }} · {{ $s->status }}@if ($live->contains($s->id)) · <span class="text-green-700">on air</span>@endif
        @if ($s->starts_at || $s->ends_at) · {{ $s->starts_at?->format('Y-m-d H:i') ?? '…' }} → {{ $s->ends_at?->format('Y-m-d H:i') ?? '…' }}@endif
        @if (! $s->translations->firstWhere('locale', 'en')?->title && ! $s->post_id) · <span class="text-amber-700">no English text (French shown)</span>@endif
        @if ($s->post_id && ! $s->post?->published_at) · <span class="text-red-700">article not published: hidden</span>@endif</div></div>
    @can('featured.edit')
    <form method="POST" action="{{ route('admin.featured.move', $s) }}">@csrf<input type="hidden" name="dir" value="up"><button class="rounded border px-2" type="submit" aria-label="Move {{ $v['title'] }} up" @disabled($loop->first)>↑</button></form>
    <form method="POST" action="{{ route('admin.featured.move', $s) }}">@csrf<input type="hidden" name="dir" value="down"><button class="rounded border px-2" type="submit" aria-label="Move {{ $v['title'] }} down" @disabled($loop->last)>↓</button></form>
    <a class="underline" href="{{ route('admin.featured.edit', $s) }}">Edit</a>
    @endcan
    @can('featured.delete')<form method="POST" action="{{ route('admin.featured.destroy', $s) }}" onsubmit="return confirm('Delete this slide?')">@csrf @method('DELETE')<button class="text-red-700" type="submit">Delete</button></form>@endcan
</li>
@empty
<li class="text-sm">No slide.</li>
@endforelse
</ol>
@endsection
