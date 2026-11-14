<?php

namespace App\Support;

/** Fingerprinted URLs for the compiled public assets, so a long browser cache never serves a stale file after a deploy. */
class Assets
{
    /** "/assets/css/app.min.css" becomes "/assets/css/app.min.css?v=<mtime>"; unchanged when the file is missing. */
    public static function v(string $path): string
    {
        static $memo = [];
        $memo[$path] ??= is_file($file = public_path(ltrim($path, '/'))) ? $path.'?v='.filemtime($file) : $path;

        return $memo[$path];
    }

    /** Default social card (1200x630) for pages without an image of their own. */
    public static function ogDefault(): array
    {
        return ['image' => url('img/og-default.png'), 'width' => 1200, 'height' => 630, 'alt' => 'Africa Global Press'];
    }
}
