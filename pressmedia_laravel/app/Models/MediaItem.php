<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Media section entry; one row per podcast show/episode, video, magazine issue, radio station or TV channel. */
class MediaItem extends Model
{
    /**
     * Per type: permission module, public section key, and the editable fields stored in `data`
     * (field => input type). Translated fields: title/description (+ file for podcasts) on the translations table.
     */
    public const TYPES = [
        'podcast_show' => ['module' => 'podcasts', 'section' => 'podcasts', 'translated' => ['title', 'description'], 'data' => []],
        'podcast' => ['module' => 'podcasts', 'section' => 'podcasts', 'translated' => ['title', 'description', 'file'], 'data' => ['show' => 'show', 'duration' => 'number']],
        'video' => ['module' => 'videos', 'section' => 'videos', 'translated' => ['title', 'description'], 'data' => ['topic' => 'topic', 'duration' => 'number', 'file' => 'file', 'poster' => 'file']],
        'magazine' => ['module' => 'magazine', 'section' => 'magazine', 'translated' => ['title', 'description'], 'data' => ['number' => 'number']],
        'radio' => ['module' => 'radios', 'section' => 'radios', 'translated' => ['description'], 'data' => ['name' => 'text', 'country' => 'text', 'language' => 'text', 'genre' => 'genre', 'website' => 'url']],
        'tv' => ['module' => 'television', 'section' => 'television', 'translated' => ['description'], 'data' => ['name' => 'text', 'country' => 'text', 'language' => 'text', 'genre' => 'genre', 'website' => 'url']],
    ];

    protected $guarded = [];

    protected $casts = ['data' => 'array', 'published_on' => 'date'];

    public function translations(): HasMany
    {
        return $this->hasMany(MediaItemTranslation::class);
    }

    public function translation(?string $locale = null): ?MediaItemTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', config('app.content_locale'));
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** Magazine contents in issue order. */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withPivot('position')->orderByPivot('position');
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function get(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }

    /** Files imported from the prototype stay under /assets; uploads live on the public disk. */
    public static function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'assets/') ? url($path) : Storage::disk('public')->url($path);
    }
}
