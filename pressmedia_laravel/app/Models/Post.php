<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Translatable model: text columns live in post_translations; translation($locale) falls back to the default locale. */
class Post extends Model
{
    use SoftDeletes;

    /** Same workflow as the prototype editor; published and scheduled need posts.publish. */
    public const STATUSES = ['draft', 'pending', 'published', 'scheduled'];

    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime', 'featured' => 'boolean'];

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function translation(?string $locale = null): ?PostTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', config('app.content_locale'));
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Public URL in $locale (/{locale}/articles/{slug}; the prototype path ended in .html), or null without a translation. */
    public function url(string $locale): ?string
    {
        $t = $this->translations->firstWhere('locale', $locale);

        return $t ? url("$locale/articles/{$t->slug}") : null;
    }

    /** Categories in label order for $locale, accents ignored (build_site.py mk(): the prototype's chip order). */
    public function sortedCategories(?string $locale = null)
    {
        $locale ??= app()->getLocale();

        return $this->categories->sortBy(fn ($c) => Str::lower(Str::ascii($c->translation($locale)?->name ?? '')))->values();
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Reading time as the prototype computes it (build_site.py: one minute per 950 characters, minimum 3). */
    public static function minutes(string $html): int
    {
        return max(3, (int) round(mb_strlen(trim(strip_tags($html))) / 950));
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }
}
