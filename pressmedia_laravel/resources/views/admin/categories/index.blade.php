@extends('admin.layout')
@section('title', 'Categories')
@section('content')
<div class="a-page-head"><div><h1>Categories</h1></div></div>
<div class="grid gap-6 lg:grid-cols-2">
<div class="a-card"><div class="a-tablewrap"><table class="a-table">
    <thead><tr><th>Name (FR / EN)</th><th>Posts</th><th></th></tr></thead>
    <tbody>
    @forelse ($tree as [$c, $depth])
        <tr>
            <td style="padding-left: {{ 0.5 + $depth * 1.25 }}rem">{{ $c->translation('fr')?->name }} <span class="a-muted">/ {{ $c->translations->firstWhere('locale', 'en')?->name ?? '—' }}</span></td>
            <td>{{ $c->posts_count }}</td>
            <td class="whitespace-nowrap">
                @can('categories.edit')<a class="ttl" href="{{ route('admin.categories.edit', $c) }}">Edit</a>@endcan
                @can('categories.delete')<form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="inline">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="3">No categories.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@if ($category->exists ? auth()->user()->can('categories.edit') : auth()->user()->can('categories.create'))
<form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="a-card a-card-b mb-4">
    @csrf @if ($category->exists) @method('PUT') @endif
    <h2 class="font-semibold">{{ $category->exists ? 'Edit category' : 'New category' }}</h2>
    <label class="a-label" for="key">Key</label><input class="a-input" id="key" name="key" value="{{ old('key', $category->key) }}" required>
    <label class="a-label" for="parent_id">Parent</label>
    <select class="a-select" id="parent_id" name="parent_id"><option value="">— none —</option>
        @foreach ($all->where('id', '!=', $category->id) as $p)<option value="{{ $p->id }}" @selected((int) old('parent_id', $category->parent_id) === $p->id)>{{ $p->translation('fr')?->name ?? $p->key }}</option>@endforeach
    </select>
    <label class="a-label" for="position">Position</label><input class="a-input" id="position" name="position" type="number" min="0" value="{{ old('position', $category->position ?? 0) }}">
    @foreach (['fr' => 'Français', 'en' => 'English'] as $l => $label)
        @php($t = $category->translations?->firstWhere('locale', $l))
        <fieldset class="a-card a-card-b" lang="{{ $l }}"><legend class="a-eyebrow">{{ $label }}</legend>
            @foreach (['name' => 'Name', 'slug' => 'Slug', 'seo_title' => 'SEO title', 'description' => 'Description'] as $f => $fl)
                <label class="a-label" for="c-{{ $l }}-{{ $f }}">{{ $fl }}</label>
                <input class="a-input" id="c-{{ $l }}-{{ $f }}" name="translations[{{ $l }}][{{ $f }}]" value="{{ old("translations.$l.$f", $t?->$f) }}">
            @endforeach
        </fieldset>
    @endforeach
    <button class="a-btn a-btn-primary" type="submit">Save</button>
</form>
@endif
</div>
@endsection
