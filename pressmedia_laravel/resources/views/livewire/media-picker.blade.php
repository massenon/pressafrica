<div class="a-picker">
    @if ($mode === 'field')
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        <div class="flex items-center gap-3">
            <span class="a-thumb" aria-hidden="true">@if ($current)<img src="{{ $current->url() }}" alt="" class="w-full h-full object-cover">@endif</span>
            <span class="a-muted text-sm">{{ $current ? ($current->alt_fr ?: basename($current->path)) : 'No image chosen' }}</span>
            <button type="button" class="a-btn a-btn-sm" wire:click="$set('open', true)">Choose image</button>
            @if ($current)<button type="button" class="a-btn a-btn-sm a-btn-ghost" wire:click="clear">Remove</button>@endif
        </div>
    @endif
    @if ($open)
    <div class="a-modal" role="dialog" aria-modal="true" aria-label="Media library" style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;background:rgba(0,10,30,.55)" wire:keydown.escape="$set('open', false)">
        <div class="a-card" style="width:min(960px,calc(100vw - 32px));max-height:calc(100vh - 48px);overflow:auto">
            <div class="a-card-h flex items-center justify-between"><h2 class="text-xl">Media library</h2><button type="button" class="a-btn a-btn-sm a-btn-ghost" wire:click="$set('open', false)">Close</button></div>
            <div class="a-card-b">
                <div class="a-tools"><div class="a-field" style="flex:1"><label class="a-label" for="mp-q-{{ $this->getId() }}">Search</label><input class="a-input" id="mp-q-{{ $this->getId() }}" type="search" wire:model.live.debounce.300ms="q" placeholder="Alt text or file name" autofocus></div></div>
                <ul class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr));list-style:none;padding:0">
                    @forelse ($items as $m)
                        <li><button type="button" class="a-card" style="width:100%;padding:0;overflow:hidden;text-align:left" wire:click="choose({{ $m->id }})" aria-label="Choose {{ $m->alt_fr ?: basename($m->path) }}"><img src="{{ $m->url() }}" alt="" loading="lazy" style="aspect-ratio:4/3;width:100%;object-fit:cover"><span class="a-muted text-xs" style="display:block;padding:.25rem .5rem">{{ \Illuminate\Support\Str::limit($m->alt_fr ?: basename($m->path), 40) }}</span></button></li>
                    @empty
                        <li class="a-muted">No image.</li>
                    @endforelse
                </ul>
                @if (method_exists($items, 'links'))<div class="mt-3">{{ $items->links() }}</div>@endif
                @can('media.create')
                <form wire:submit="save" class="a-card a-card-b mt-4"><p class="a-label">Upload a new image (converted to WebP)</p>
                    <div class="a-grid2"><div class="a-field"><input type="file" wire:model="upload" accept="image/jpeg,image/png,image/gif,image/webp" aria-label="Image file"></div>
                    <div class="a-field"><label class="a-label" for="mp-alt-{{ $this->getId() }}">Alternative text (French)</label><input class="a-input" id="mp-alt-{{ $this->getId() }}" wire:model="uploadAlt"></div></div>
                    @error('upload')<span class="a-badge bad">{{ $message }}</span>@enderror @error('uploadAlt')<span class="a-badge bad">{{ $message }}</span>@enderror
                    <button class="a-btn a-btn-primary mt-2" type="submit" wire:loading.attr="disabled">Upload and choose</button></form>
                @endcan
            </div>
        </div>
    </div>
    @endif
</div>
