@extends('admin.layout')
@section('title', 'Tags')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Tags</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<p class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>@endif
@can('tags.create')
<form method="POST" action="{{ route('admin.tags.store') }}" class="mb-4 flex gap-2 text-sm">@csrf
    <label class="sr-only" for="new-tag">New tag</label><input class="rounded border px-2 py-1" id="new-tag" name="name" placeholder="New tag" required>
    <button class="rounded border px-3 py-1" type="submit">Add</button></form>
@endcan
<table class="w-full border-collapse bg-white text-sm dark:bg-slate-800">
    <thead><tr class="border-b text-left"><th class="p-2">Name</th><th class="p-2">Posts</th><th class="p-2">Actions</th></tr></thead>
    <tbody>
    @forelse ($tags as $t)
        <tr class="border-b">
            <td class="p-2">
                @can('tags.edit')<form method="POST" action="{{ route('admin.tags.update', $t) }}" class="flex gap-1">@csrf @method('PUT')
                    <input class="rounded border px-2 py-1" name="name" value="{{ $t->name }}" aria-label="Rename {{ $t->name }}"><button class="rounded border px-2" type="submit">Rename</button></form>
                @else{{ $t->name }}@endcan
            </td>
            <td class="p-2">{{ $t->posts_count }}</td>
            <td class="p-2 whitespace-nowrap">
                @can('tags.edit')<form method="POST" action="{{ route('admin.tags.merge', $t) }}" class="inline-flex gap-1">@csrf
                    <select class="rounded border px-1" name="into" aria-label="Merge {{ $t->name }} into">@foreach ($tags->where('id', '!=', $t->id) as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
                    <button class="rounded border px-2" type="submit">Merge</button></form>@endcan
                @can('tags.delete')<form method="POST" action="{{ route('admin.tags.destroy', $t) }}" class="inline">@csrf @method('DELETE')<button class="ml-2 text-red-700" type="submit">Delete</button></form>@endcan
            </td>
        </tr>
    @empty
        <tr><td class="p-2" colspan="3">No tags.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
