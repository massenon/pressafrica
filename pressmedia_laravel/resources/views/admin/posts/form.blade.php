@extends('admin.layout')
@section('title', $post->exists ? 'Edit post' : 'New post')
@section('content')
<h1 class="mb-4 text-2xl font-bold">{{ $post->exists ? 'Edit post' : 'New post' }}</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<ul class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="space-y-6">
    @csrf @if ($post->exists) @method('PUT') @endif
    @foreach (['fr' => 'Français (required)', 'en' => 'English (optional)'] as $l => $label)
        @php($t = $post->exists ? $post->translation($l) : null)
        @php($t = $t?->locale === $l ? $t : null)
        <fieldset class="rounded border bg-white p-4 dark:bg-slate-800" lang="{{ $l }}">
            <legend class="px-1 font-semibold">{{ $label }}</legend>
            @foreach (['title' => 'Title', 'slug' => 'Slug (blank = from title)', 'excerpt' => 'Excerpt', 'body' => 'Body (HTML)', 'seo_title' => 'SEO title', 'seo_description' => 'SEO description'] as $f => $fl)
                <label class="mb-1 block text-sm font-medium" for="{{ $l }}-{{ $f }}">{{ $fl }}</label>
                @if (in_array($f, ['excerpt', 'body', 'seo_description']))
                    <textarea class="mb-3 w-full rounded border px-2 py-1" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" rows="{{ $f === 'body' ? 12 : 2 }}">{{ old("translations.$l.$f", $t?->$f) }}</textarea>
                @else
                    <input class="mb-3 w-full rounded border px-2 py-1" id="{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $t?->$f) }}">
                @endif
            @endforeach
        </fieldset>
    @endforeach
    <fieldset class="grid gap-4 rounded border bg-white p-4 sm:grid-cols-2 dark:bg-slate-800">
        <legend class="px-1 font-semibold">Publishing</legend>
        <div><label class="mb-1 block text-sm font-medium" for="status">Status</label>
            <select class="rounded border px-2 py-1" id="status" name="status">@foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $post->status) === $s)>{{ $s }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium" for="published_at">Publication date (future = scheduled)</label>
            <input class="rounded border px-2 py-1" id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="mb-1 block text-sm font-medium" for="categories">Categories</label>
            <select class="w-full rounded border px-2 py-1" id="categories" name="categories[]" multiple size="6">
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(collect(old('categories', $post->exists ? $post->categories->pluck('id')->all() : []))->contains($c->id))>{{ $c->translation('fr')?->name ?? $c->key }}</option>@endforeach
            </select></div>
        <div><label class="mb-1 block text-sm font-medium" for="tags">Tags (comma separated)</label>
            <input class="w-full rounded border px-2 py-1" id="tags" name="tags" value="{{ old('tags', $post->exists ? $post->tags->pluck('name')->implode(', ') : '') }}">
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" @checked(old('featured', $post->featured))> Featured</label></div>
    </fieldset>
    <button class="rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save</button>
</form>
@if ($post->exists)
    @can('delete', $post)
    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="mt-4">@csrf @method('DELETE')
        <button class="rounded border border-red-700 px-3 py-1 text-sm text-red-700" type="submit">Move to trash</button></form>
    @endcan
@endif
@endsection
