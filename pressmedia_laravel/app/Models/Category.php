<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Translatable model: text columns live in category_translations; translation($locale) falls back to the default locale. */
class Category extends Model
{
    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(?string $locale = null): ?CategoryTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', config('app.content_locale'));
    }

    /** Section page URL as in the prototype: /{locale}/{slug} (clean URL; the prototype path ended in .html). */
    public function url(string $locale): string
    {
        return url("$locale/".$this->translation($locale)?->slug);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
