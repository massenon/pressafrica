@extends('admin.layout')
@section('title', 'Menus')
@section('page', 'menus')
@section('content')
<div class="a-page-head"><div><p class="a-eyebrow">Site</p><h1>Menus</h1><p>Main menu of the public site and its drop-down panels.</p></div>
<div class="flex flex-wrap gap-2">@can('menus.create')<a class="a-btn a-btn-primary" href="{{ route('admin.menus.create') }}" wire:navigate><span>New entry</span></a>@endcan
@can('menus.edit')<form method="POST" action="{{ route('admin.menus.restore') }}" data-confirm="Replace the menu with the default 4 entries?">@csrf<button class="a-btn a-btn-danger" type="submit">Restore default menu</button></form>@endcan</div></div>
<div class="a-card"><div class="a-tablewrap"><table class="a-table" aria-label="Menu entries"><thead><tr><th scope="col">#</th><th scope="col">Label (FR / EN)</th><th scope="col">Panel</th><th scope="col">Links to</th><th scope="col">Status</th><th scope="col" class="text-right"><span class="a-vh">Actions</span></th></tr></thead><tbody>
@forelse ($items as $m)
<tr><td>{{ $loop->iteration }}</td><td>{{ $m->label('fr') }} <span class="a-muted">/ {{ $m->translations->firstWhere('locale', 'en')?->label ?? '—' }}</span></td><td><span class="a-badge">{{ $m->panel }}</span></td><td class="text-sm">{{ $m->href('fr') }}</td><td><span class="a-badge {{ $m->active ? 'ok' : '' }}">{{ $m->active ? 'shown' : 'hidden' }}</span></td>
<td class="text-right whitespace-nowrap">@can('menus.edit')<form method="POST" action="{{ route('admin.menus.move', $m) }}" class="inline">@csrf<input type="hidden" name="dir" value="up"><button class="a-btn a-btn-sm a-btn-ghost" type="submit" aria-label="Move {{ $m->label('fr') }} up" @disabled($loop->first)>↑</button></form><form method="POST" action="{{ route('admin.menus.move', $m) }}" class="inline">@csrf<input type="hidden" name="dir" value="down"><button class="a-btn a-btn-sm a-btn-ghost" type="submit" aria-label="Move {{ $m->label('fr') }} down" @disabled($loop->last)>↓</button></form><a class="a-btn a-btn-sm" href="{{ route('admin.menus.edit', $m) }}" wire:navigate>Edit</a>@endcan
@can('menus.delete')<form method="POST" action="{{ route('admin.menus.destroy', $m) }}" class="inline" data-confirm="Delete this menu entry?">@csrf @method('DELETE')<button class="a-btn a-btn-sm a-btn-danger" type="submit">Delete</button></form>@endcan</td></tr>
@empty
<tr><td colspan="6">No entry: the public menu is empty.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
