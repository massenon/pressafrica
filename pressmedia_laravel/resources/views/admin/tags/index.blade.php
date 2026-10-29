@extends('admin.layout')
@section('title', 'Tags')
@section('content')
<div class="a-page-head"><div><h1>Tags</h1></div></div>
@can('tags.create')
<form method="POST" action="{{ route('admin.tags.store') }}" class="mb-4 flex gap-2 text-sm">@csrf
    <label class="sr-only" for="new-tag">New tag</label><input class="a-input" id="new-tag" name="name" placeholder="New tag" required>
    <button class="a-btn a-btn-sm" type="submit">Add</button></form>
@endcan
<div class="a-card"><div class="a-tablewrap"><table class="a-table">
    <thead><tr><th>Name</th><th>Posts</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse ($tags as $t)
        <tr>
            <td>
                @can('tags.edit')<form method="POST" action="{{ route('admin.tags.update', $t) }}" class="flex gap-1">@csrf @method('PUT')
                    <input class="a-input" name="name" value="{{ $t->name }}" aria-label="Rename {{ $t->name }}"><button class="a-btn a-btn-sm" type="submit">Rename</button></form>
                @else{{ $t->name }}@endcan
            </td>
            <td>{{ $t->posts_count }}</td>
            <td class="whitespace-nowrap">
                @can('tags.edit')<form method="POST" action="{{ route('admin.tags.merge', $t) }}" class="inline-flex gap-1">@csrf
                    <select class="a-select" name="into" aria-label="Merge {{ $t->name }} into">@foreach ($tags->where('id', '!=', $t->id) as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
                    <button class="a-btn a-btn-sm" type="submit">Merge</button></form>@endcan
                @can('tags.delete')<form method="POST" action="{{ route('admin.tags.destroy', $t) }}" class="inline">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="3">No tags.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
