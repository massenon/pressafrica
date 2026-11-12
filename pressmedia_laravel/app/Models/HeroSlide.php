<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Home carousel slide (spec EX-40 to EX-42). Empty texts of an article slide fall back to the article. */
class HeroSlide extends Model
{
    /** Prototype rule (admin-featured.js): at most 6 slides on air at once. */
    public const MAX_LIVE = 6;

    /** Image of the default introduction slide, imported once from the prototype (build_site.py hero_block()). */
    public const INTRO_IMAGE = 'media/import/malabo-860.webp';

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function translations(): HasMany
    {
        return $this->hasMany(HeroSlideTranslation::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Published, within its schedule (Benin time), with a published article when it follows one. */
    public function scopeLive($q)
    {
        return $q->where('placement', 'home')->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('post_id')->orWhereHas('post', fn ($p) => $p->published()))
            ->orderBy('position')->orderBy('id');
    }

    /** What the carousel prints for $lang: own texts first, then the article's, then FR (INV-10). */
    public function view(string $lang): array
    {
        // Field by field: this language, else French, else the article (an empty English row must not hide French).
        $own = fn (string $f) => $this->translations->firstWhere('locale', $lang)?->$f ?: $this->translations->firstWhere('locale', 'fr')?->$f;
        $pt = $this->post?->translation($lang);
        $image = $this->media ?? $this->post?->cover;

        return [
            'title' => $own('title') ?: $pt?->title,
            'text' => $own('text') ?: $pt?->excerpt,
            'cta' => $own('cta') ?: __('proto.read_more', [], $lang),
            'url' => $own('url') ? url($own('url')) : $this->post?->url($lang),
            'alt' => $own('alt') ?: ($pt ? __('site.illustration', ['title' => $pt->title], $lang) : (string) $own('title')),
            'image' => $image,
            'by' => $this->post?->author?->name,
            'article' => (bool) $this->post_id,
        ];
    }

    /**
     * Rebuilds the default carousel: the prototype's 4 slides (lead article, introduction, two articles), from the
     * imported home picks. Used by the import and by the admin's "restore default" button.
     */
    public static function restoreDefaults(): Collection
    {
        return DB::transaction(function () {
            static::where('placement', 'home')->get()->each->delete();
            $hero = HomeSlot::where('slot', 'hero')->orderBy('position')->pluck('post_id')->values();
            $order = [$hero[1] ?? null, 'intro', $hero[0] ?? null, $hero[2] ?? null];
            $intro = Media::where('path', self::INTRO_IMAGE)->first() ?? static::importIntroImage();
            $slides = collect();
            foreach (array_values(array_filter($order)) as $i => $what) {
                $slide = static::create(['placement' => 'home', 'position' => $i + 1, 'status' => 'published',
                    'post_id' => $what === 'intro' ? null : $what, 'media_id' => $what === 'intro' ? $intro?->id : null]);
                if ($what === 'intro') {
                    foreach (['fr', 'en'] as $l) {
                        $slide->translations()->create([
                            'locale' => $l, 'title' => __('proto.hero_intro_title', [], $l), 'text' => __('proto.hero_intro_text', [], $l),
                            'cta' => __('proto.hero_intro_cta', [], $l), 'url' => "/$l/".($l === 'fr' ? 'presse-en-ligne' : 'online-press'),
                            'alt' => $l === 'fr' ? "Panorama urbain d'une capitale africaine" : 'Urban skyline of an African capital',
                        ]);
                    }
                }
                $slides->push($slide);
            }

            return $slides;
        });
    }

    /** Copies the introduction picture from the prototype assets into the media library (once). */
    private static function importIntroImage(): ?Media
    {
        $src = base_path('../prototype_html/assets/img/posts');
        if (! is_file("$src/malabo-860.webp")) {
            return null;
        }
        foreach (['420', '860'] as $w) {
            Storage::disk('public')->put("media/import/malabo-$w.webp", file_get_contents("$src/malabo-$w.webp"));
        }

        return Media::create(['path' => self::INTRO_IMAGE, 'mime' => 'image/webp', 'size' => filesize("$src/malabo-860.webp"),
            'width' => 600, 'height' => 361, 'alt_fr' => "Panorama urbain d'une capitale africaine", 'alt_en' => 'Urban skyline of an African capital']);
    }
}
