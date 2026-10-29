@extends('admin.layout')
@section('title', 'Categories')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Categories</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<ul class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
<div class="grid gap-6 lg:grid-cols-2">
<table class="w-full border-collapse self-start bg-white text-sm dark:bg-slate-800">
    <thead><tr class="border-b text-left"><th class="p-2">Name (FR / EN)</th><th class="p-2">Posts</th><th class="p-2"></th></tr></thead>
    <tbody>
    @forelse ($tree as [$c, $depth])
        <tr class="border-b">
            <td class="p-2" style="padding-left: {{ 0.5 + $depth * 1.25 }}rem">{{ $c->translation('fr')?->name }} <span class="text-slate-500">/ {{ $c->translations->firstWhere('locale', 'en')?->name ?? '—' }}</span></td>
            <td class="p-2">{{ $c->posts_count }}</td>
            <td class="p-2 whitespace-nowrap">
                @can('categories.edit')<a class="underline" href="{{ route('admin.categories.edit', $c) }}">Edit</a>@endcan
                @can('categories.delete')<form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="inline">@csrf @method('DELETE')<button class="ml-2 text-red-700" type="submit">Delete</button></form>@endcan
            </td>
        </tr>
    @empty
        <tr><td class="p-2" colspan="3">No categories.</td></tr>
    @endforelse
    </tbody>
</table>
@if ($category->exists ? auth()->user()->can('categories.edit') : auth()->user()->can('categories.create'))
<form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-3 rounded border bg-white p-4 text-sm dark:bg-slate-800">
    @csrf @if ($category->exists) @method('PUT') @endif
    <h2 class="font-semibold">{{ $category->exists ? 'Edit category' : 'New category' }}</h2>
    <label class="block" for="key">Key</label><input class="w-full rounded border px-2 py-1" id="key" name="key" value="{{ old('key', $category->key) }}" required>
    <label class="block" for="parent_id">Parent</label>
    <select class="w-full rounded border px-2 py-1" id="parent_id" name="parent_id"><option value="">— none —</option>
        @foreach ($all->where('id', '!=', $category->id) as $p)<option value="{{ $p->id }}" @selected((int) old('parent_id', $category->parent_id) === $p->id)>{{ $p->translation('fr')?->name ?? $p->key }}</option>@endforeach
    </select>
    <label class="block" for="position">Position</label><input class="w-24 rounded border px-2 py-1" id="position" name="position" type="number" min="0" value="{{ old('position', $category->position ?? 0) }}">
    @foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
        @php($t = $category->translations?->firstWhere('locale', $l))
        <fieldset class="rounded border p-3" lang="{{ $l }}"><legend class="px-1">{{ $label }}</legend>
            @foreach (['name' => 'Name', 'slug' => 'Slug', 'seo_title' => 'SEO title', 'description' => 'Description'] as $f => $fl)
                <label class="block" for="c-{{ $l }}-{{ $f }}">{{ $fl }}</label>
                <input class="mb-2 w-full rounded border px-2 py-1" id="c-{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $t?->$f) }}">
            @endforeach
        </fieldset>
    @endforeach
    <button class="rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save</button>
</form>
@endif
</div>
@endsection
