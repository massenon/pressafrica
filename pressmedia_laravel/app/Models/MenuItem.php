<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MenuItem extends Model
{
    public const PANELS = ['none', 'articles', 'podcasts', 'videos', 'press'];

    protected $guarded = [];

    protected $casts = ['data' => 'array', 'active' => 'boolean'];

    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function label(string $lang): string
    {
        return (string) ($this->translations->firstWhere('locale', $lang)?->label ?: $this->translations->firstWhere('locale', 'fr')?->label);
    }

    public function href(string $lang): string
    {
        $own = $this->translations->firstWhere('locale', $lang)?->url;

        return $own ? url($own) : ($this->category?->url($lang) ?? url($lang));
    }

    /** The prototype's main menu (build_site.py mainnav()); used by the first import and Admin > Menus "restore". */
    public static function restoreDefaults(): void
    {
        DB::transaction(function () {
            static::where('location', 'main')->get()->each->delete();
            $cat = fn ($k) => Category::where('key', $k)->value('id');
            $picks = Post::whereIn('legacy_id', [4555, 4556, 4557])->orderByRaw('CASE legacy_id WHEN 4555 THEN 1 WHEN 4556 THEN 2 ELSE 3 END')->pluck('id')->all();
            foreach ([['top', 'articles', 'online-press', 'nav_top', []], ['pod', 'podcasts', 'podcasts', 'nav_podcasts', []],
                ['vid', 'videos', 'videos', 'nav_videos', []], ['press', 'press', 'online-press', 'nav_press', ['picks' => $picks]]] as $i => [$key, $panel, $c, $label, $data]) {
                $item = static::create(['location' => 'main', 'key' => $key, 'position' => $i + 1, 'panel' => $panel, 'category_id' => $cat($c), 'data' => $data]);
                foreach (['fr', 'en'] as $l) {
                    $item->translations()->create(['locale' => $l, 'label' => __("proto.$label", [], $l)]);
                }
            }
        });
    }
}
