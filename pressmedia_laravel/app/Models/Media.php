<?php

namespace App\Models;

use App\Support\MediaStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['path', 'mime', 'size', 'width', 'height', 'alt_fr', 'alt_en', 'uploaded_by'];

    public function url(): string
    {
        return MediaStore::url($this->path);
    }

    /**
     * srcset when the smaller widths exist next to the file (imported covers come as -420/-860/-1280.webp,
     * like the prototype's assets/img/posts); null otherwise.
     */
    public function srcset(): ?string
    {
        if (! preg_match('/-1280\.webp$/', $this->path)) {
            return null;
        }
        $parts = [];
        foreach ([420, 860, 1280] as $w) {
            $p = preg_replace('/-1280\.webp$/', "-$w.webp", $this->path);
            if (Storage::disk(MediaStore::DISK)->exists($p)) {
                $parts[] = MediaStore::url($p)." {$w}w";
            }
        }

        return $parts ? implode(', ', $parts) : null;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
