@extends('admin.layout')
@section('title', 'Media library')
@section('content')
<div class="a-page-head"><div><h1>Media library</h1></div></div>
@can('media.create')
<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="a-card a-card-b mb-4">@csrf
    <div><label class="a-label" for="files">Files (JPEG/PNG become WebP; GIF, WebP, PDF; 10 MB max)</label><input id="files" name="files[]" type="file" multiple required accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"></div>
    <div><label class="a-label" for="alt_fr">Alt (FR)</label><input class="a-input" id="alt_fr" name="alt_fr"></div>
    <div><label class="a-label" for="alt_en">Alt (EN)</label><input class="a-input" id="alt_en" name="alt_en"></div>
    <button class="a-btn a-btn-primary" type="submit">Upload</button>
</form>
@endcan
<form method="GET" class="mb-4 text-sm"><input class="a-input" name="q" value="{{ $q }}" placeholder="Search alt text" aria-label="Search alt text"></form>
<ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
@forelse ($media as $m)
    <li class="a-card a-card-b mb-4">
        @if ($m->isImage())<img src="{{ $m->url() }}" alt="{{ $m->alt_fr }}" loading="lazy" class="mb-2 aspect-video w-full object-cover">@else<a class="mb-2 block underline" href="{{ $m->url() }}">{{ strtoupper(\Illuminate\Support\Str::afterLast($m->path, '.')) }} file</a>@endif
        <p class="a-muted">{{ $m->width ? $m->width.'×'.$m->height.' · ' : '' }}{{ number_format($m->size / 1024) }} kB</p>
        @can('media.edit')
        <form method="POST" action="{{ route('admin.media.update', $m) }}" class="mt-1 space-y-1">@csrf @method('PUT')
            <input class="a-input" name="alt_fr" value="{{ $m->alt_fr }}" placeholder="Alt FR" aria-label="Alt FR">
            <input class="a-input" name="alt_en" value="{{ $m->alt_en }}" placeholder="Alt EN" aria-label="Alt EN">
            <button class="a-btn a-btn-sm" type="submit">Save</button></form>
        @endcan
        @can('media.delete')<form method="POST" action="{{ route('admin.media.destroy', $m) }}" class="mt-1">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan
    </li>
@empty
    <li>No files.</li>
@endforelse
</ul>
<div class="mt-4">{{ $media->links() }}</div>
@endsection
