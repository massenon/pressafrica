<?php

namespace App\Support;

/**
 * Prints the prototype's page chrome (resources/chrome/*.html, exported by tools/export_chrome.py).
 * The fragments are trusted, generated markup; only the markers are replaced, with escaped values.
 */
class Chrome
{
    /** Base URL the prototype generator wrote into absolute links (JSON-LD of fixed pages). */
    private const PROTOTYPE_SITE = 'https://www.africaglobalpress.example';

    public static function render(string $part, string $lang, array $alternates = [], array $extra = []): string
    {
        $file = resource_path("chrome/$part".($part === 'sprite' ? '' : "-$lang").'.html');
        $date = now()->locale($lang)->isoFormat($lang === 'fr' ? 'dddd D MMMM YYYY' : 'dddd, MMMM D, YYYY');

        return strtr(file_get_contents($file), [
            '%%ALT_FR%%' => e($alternates['fr'] ?? url('fr/index.html')),
            '%%ALT_EN%%' => e($alternates['en'] ?? url('en/index.html')),
            '%%DATE%%' => e($date),
            self::PROTOTYPE_SITE => rtrim(url('/'), '/'),
        ] + $extra);
    }

    /** Fixed page keys by language and slug (resources/chrome/pages.json, from tools/export_chrome.py). */
    public static function pages(): array
    {
        static $pages;

        return $pages ??= json_decode(file_get_contents(resource_path('chrome/pages.json')), true);
    }
}
