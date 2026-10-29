<?php

namespace App\Models;

use App\Support\MediaStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['path', 'mime', 'size', 'width', 'height', 'alt_fr', 'alt_en', 'uploaded_by'];

    public function url(): string
    {
        return MediaStore::url($this->path);
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
