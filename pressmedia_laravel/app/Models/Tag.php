<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /** Finds or creates tags from free-text names, as typed in the post editor. */
    public static function idsFromNames(array $names): array
    {
        return collect($names)->map(fn ($n) => trim($n))->filter()->unique(fn ($n) => Str::slug($n))
            ->map(fn ($n) => static::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n])->id)
            ->values()->all();
    }
}
