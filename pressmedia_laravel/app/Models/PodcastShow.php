<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Translatable model: text columns live in podcast_show_translations; translation($locale) falls back to the default locale. */
class PodcastShow extends Model
{
    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(PodcastShowTranslation::class);
    }

    public function translation(?string $locale = null): ?PodcastShowTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class);
    }
}
