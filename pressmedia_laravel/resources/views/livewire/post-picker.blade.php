<div class="a-picker">
    @if ($multiple)
        @foreach ($selected as $id)<input type="hidden" name="{{ $name }}[]" value="{{ $id }}">@endforeach
    @else
        <input type="hidden" name="{{ $name }}" value="{{ $selected[0] ?? '' }}">
    @endif
    @if ($chosen->isNotEmpty())
    <ol class="a-list mb-2">
        @foreach ($chosen as $p)
        <li class="flex items-center gap-2"><span class="flex-1">{{ $p->translation('fr')?->title }} <span class="a-muted text-xs">{{ $p->published_at?->format('j M Y') }} · {{ $p->status }}</span></span>
            @if ($multiple)<button type="button" class="a-btn a-btn-sm a-btn-ghost" wire:click="move({{ $p->id }}, -1)" aria-label="Move up" @disabled($loop->first)>↑</button><button type="button" class="a-btn a-btn-sm a-btn-ghost" wire:click="move({{ $p->id }}, 1)" aria-label="Move down" @disabled($loop->last)>↓</button>@endif
            <button type="button" class="a-btn a-btn-sm a-btn-danger" wire:click="remove({{ $p->id }})" aria-label="Remove {{ $p->translation('fr')?->title }}">✕</button></li>
        @endforeach
    </ol>
    @endif
    @if ($multiple || $chosen->isEmpty())
    <input class="a-input" type="search" wire:model.live.debounce.300ms="q" placeholder="Type 2 letters of a title…" aria-label="Search an article by title" autocomplete="off">
    @if ($results->isNotEmpty())
    <ul class="a-card mt-1" role="listbox" style="list-style:none;padding:.25rem">
        @foreach ($results as $p)<li><button type="button" class="a-btn a-btn-ghost a-btn-sm" style="width:100%;justify-content:flex-start" wire:click="add({{ $p->id }})">{{ $p->translation('fr')?->title }} <span class="a-muted text-xs">· {{ $p->published_at?->format('j M Y') }} · {{ $p->status }}</span></button></li>@endforeach
    </ul>
    @endif
    @endif
</div>
