<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

/** Public author pages (/{locale}/authors/{slug}): the slug is the name's, no extra column; only bylined staff have one. */
class Authors
{
    public static function slug(User $user): string
    {
        return Str::slug((string) $user->name);
    }

    public static function url(User $user, string $locale): ?string
    {
        return ($slug = self::slug($user)) !== '' ? url("$locale/authors/$slug") : null;
    }

    /** The staff member with a published article behind this slug, or null. */
    public static function find(string $slug): ?User
    {
        return User::whereIn('id', Post::published()->whereNotNull('author_id')->select('author_id'))
            ->get()->first(fn (User $u) => self::slug($u) === $slug);
    }
}
