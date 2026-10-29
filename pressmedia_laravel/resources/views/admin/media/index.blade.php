@extends('admin.layout')
@section('title', 'Media library')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Media library</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<ul class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
@can('media.create')
<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="mb-6 flex flex-wrap items-end gap-2 rounded border bg-white p-4 text-sm dark:bg-slate-800">@csrf
    <div><label class="block" for="files">Files (JPEG/PNG become WebP; GIF, WebP, PDF; 10 MB max)</label><input id="files" name="files[]" type="file" multiple required accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"></div>
    <div><label class="block" for="alt_fr">Alt (FR)</label><input class="rounded border px-2 py-1" id="alt_fr" name="alt_fr"></div>
    <div><label class="block" for="alt_en">Alt (EN)</label><input class="rounded border px-2 py-1" id="alt_en" name="alt_en"></div>
    <button class="rounded bg-slate-900 px-3 py-2 font-semibold text-white" type="submit">Upload</button>
</form>
@endcan
<form method="GET" class="mb-4 text-sm"><input class="rounded border px-2 py-1" name="q" value="{{ $q }}" placeholder="Search alt text" aria-label="Search alt text"></form>
<ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
@forelse ($media as $m)
    <li class="rounded border bg-white p-2 text-xs dark:bg-slate-800">
        @if ($m->isImage())<img src="{{ $m->url() }}" alt="{{ $m->alt_fr }}" loading="lazy" class="mb-2 aspect-video w-full object-cover">@else<a class="mb-2 block underline" href="{{ $m->url() }}">{{ strtoupper(\Illuminate\Support\Str::afterLast($m->path, '.')) }} file</a>@endif
        <p class="text-slate-500">{{ $m->width ? $m->width.'×'.$m->height.' · ' : '' }}{{ number_format($m->size / 1024) }} kB</p>
        @can('media.edit')
        <form method="POST" action="{{ route('admin.media.update', $m) }}" class="mt-1 space-y-1">@csrf @method('PUT')
            <input class="w-full rounded border px-1" name="alt_fr" value="{{ $m->alt_fr }}" placeholder="Alt FR" aria-label="Alt FR">
            <input class="w-full rounded border px-1" name="alt_en" value="{{ $m->alt_en }}" placeholder="Alt EN" aria-label="Alt EN">
            <button class="rounded border px-2" type="submit">Save</button></form>
        @endcan
        @can('media.delete')<form method="POST" action="{{ route('admin.media.destroy', $m) }}" class="mt-1">@csrf @method('DELETE')<button class="text-red-700" type="submit">Delete</button></form>@endcan
    </li>
@empty
    <li>No files.</li>
@endforelse
</ul>
<div class="mt-4">{{ $media->links() }}</div>
@endsection
