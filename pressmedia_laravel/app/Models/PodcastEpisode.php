<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Translatable model: text columns live in podcast_episode_translations; translation($locale) falls back to the default locale. */
class PodcastEpisode extends Model
{
    protected $guarded = [];

    protected $casts = ['published_on' => 'date'];

    public function translations(): HasMany
    {
        return $this->hasMany(PodcastEpisodeTranslation::class);
    }

    public function translation(?string $locale = null): ?PodcastEpisodeTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(PodcastShow::class, 'podcast_show_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }
}
