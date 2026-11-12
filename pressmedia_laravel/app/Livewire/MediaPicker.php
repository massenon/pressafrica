<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Media;
use App\Support\MediaStore;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Media library in a modal (spec EX-48): search, upload, choose. In "field" mode it fills a hidden form input
 * (`name`); in "insert" mode it sends the chosen image to the editor (browser event `media-picked`).
 */
class MediaPicker extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public string $name = 'media_id';

    #[Locked]
    public string $mode = 'field';

    public ?int $value = null;

    public bool $open = false;

    public string $q = '';

    public $upload;

    public string $uploadAlt = '';

    /** The editor's image button: only the insert-mode picker answers. */
    #[On('open-media-picker')]
    public function openForEditor(): void
    {
        if ($this->mode === 'insert') {
            $this->open = true;
        }
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function choose(int $id): void
    {
        $m = Media::where('mime', 'like', 'image/%')->findOrFail($id);
        if ($this->mode === 'insert') {
            // Root-relative: an article body must keep working when the site moves to another domain.
            $this->dispatch('media-picked', url: parse_url($m->url(), PHP_URL_PATH), alt: (string) $m->alt_fr, width: $m->width, height: $m->height);
        } else {
            $this->value = $m->id;
        }
        $this->open = false;
    }

    public function clear(): void
    {
        $this->value = null;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('media.create'), 403);
        $this->validate([
            'upload' => ['required', 'file', 'max:10240', function ($a, $f, $fail) {
                if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath()), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    $fail('JPEG, PNG, GIF or WebP images only.');
                }
            }],
            'uploadAlt' => ['required', 'string', 'max:255'],   // alt text is mandatory (EX-35)
        ]);
        $m = Media::create(MediaStore::store($this->upload) + ['alt_fr' => $this->uploadAlt, 'uploaded_by' => auth()->id()]);
        AuditLog::record('create', 'media', 'media:'.$m->id, $m->mime);
        $this->reset('upload', 'uploadAlt');
        $this->choose($m->id);
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('media.view'), 403);

        return view('livewire.media-picker', [
            'current' => $this->value ? Media::find($this->value) : null,
            'items' => $this->open ? Media::where('mime', 'like', 'image/%')
                ->when($this->q, fn ($q) => $q->where(fn ($w) => $w->where('alt_fr', 'like', "%{$this->q}%")->orWhere('alt_en', 'like', "%{$this->q}%")->orWhere('path', 'like', "%{$this->q}%")))
                ->latest('id')->paginate(18) : collect(),
        ]);
    }
}
