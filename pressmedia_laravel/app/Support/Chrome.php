<?php

namespace App\Support;

/**
 * Prints the prototype's page chrome (resources/chrome/*.html, exported by tools/export_chrome.py).
 * The fragments are trusted, generated markup; only the markers are replaced, with escaped values.
 */
class Chrome
{
    public static function render(string $part, string $lang, array $alternates = []): string
    {
        $file = resource_path("chrome/$part".($part === 'sprite' ? '' : "-$lang").'.html');
        $date = now()->locale($lang)->isoFormat($lang === 'fr' ? 'dddd D MMMM YYYY' : 'dddd, MMMM D, YYYY');

        return strtr(file_get_contents($file), [
            '%%ALT_FR%%' => e($alternates['fr'] ?? url('fr/index.html')),
            '%%ALT_EN%%' => e($alternates['en'] ?? url('en/index.html')),
            '%%DATE%%' => e($date),
        ]);
    }
}
