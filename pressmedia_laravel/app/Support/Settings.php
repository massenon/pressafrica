<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Site settings. Keys and defaults mirror the prototype (prototype_html/assets/js/admin.js, seeded "settings").
 * Analytics settings (EX-12) are a phase 6 module and are not defined here yet.
 * Values are read once per request from the file cache, which is cleared on every save.
 */
class Settings
{
    private const CACHE_KEY = 'site-settings';

    /** key => [section, type, default, extra validation rules]. Types: string, text, bool, int, color, enum:<a,b>, secret. */
    public const DEFINITIONS = [
        'siteTitle' => ['general', 'string', 'Africa Global Press', ['required', 'max:120']],
        'tagline' => ['general', 'string', 'L’information africaine, sans frontières', ['max:200']],
        'logoText' => ['general', 'string', 'AGP', ['max:12']],
        'colorPrimary' => ['branding', 'color', '#013079', []],
        'colorSecondary' => ['branding', 'color', '#ffec00', []],
        'fontHeading' => ['branding', 'string', 'Alegreya', ['max:60']],
        'fontBody' => ['branding', 'string', 'Encode Sans', ['max:60']],
        'baseSize' => ['branding', 'int', 20, ['min:12', 'max:28']],
        'defaultLang' => ['language', 'enum:fr,en', 'fr', []],
        'cookiesOn' => ['privacy', 'bool', true, []],
        'cookieText' => ['privacy', 'text', 'We only use essential cookies until you accept third-party services.', ['max:500']],
        'blockThirdParty' => ['privacy', 'bool', true, []],
        'maintenance' => ['maintenance', 'bool', false, []],
        'maintenanceMsg' => ['maintenance', 'text', 'Back soon. The newsroom is upgrading.', ['max:500']],
        'maintenanceIps' => ['maintenance', 'text', '', ['max:1000']],
        'pwa' => ['pwa', 'bool', true, []],
        'pwaName' => ['pwa', 'string', 'Africa Global Press', ['max:60']],
        'pwaShort' => ['pwa', 'string', 'AGP', ['max:12']],
        'pwaTheme' => ['pwa', 'color', '#013079', []],
        // Audience (Q7): first-party log by default; GA4 implemented but off until the owner enables it.
        'analyticsSource' => ['analytics', 'enum:internal,ga4', 'internal', []],
        'ga4Enabled' => ['analytics', 'bool', false, []],
        'ga4MeasurementId' => ['analytics', 'string', '', ['max:30', 'regex:/^(G-[A-Z0-9]{4,20})?$/']],
        'ga4PropertyId' => ['analytics', 'string', '', ['max:20', 'regex:/^[0-9]*$/']],
        'ga4Credentials' => ['analytics', 'secret', '', ['max:10000']],
        'aiProvider' => ['ai', 'enum:openai,gemini', 'openai', []],
        'aiOpenaiKey' => ['ai', 'secret', '', ['max:255']],
        'aiGeminiKey' => ['ai', 'secret', '', ['max:255']],
        'aiModel' => ['ai', 'string', 'gpt-4o-mini', ['max:60']],
        'commentsApproval' => ['security', 'bool', true, []],
        'minPassword' => ['security', 'int', 12, ['min:8', 'max:64']],
        'passComplexity' => ['security', 'bool', true, []],
        'maxAttempts' => ['security', 'int', 5, ['min:3', 'max:20']],
        'lockMinutes' => ['security', 'int', 15, ['min:1', 'max:1440']],
        'nofollow' => ['security', 'bool', true, []],
        // Comments marked as spam when the e-mail, its domain or a word of the text is listed here (one per line).
        'commentBlacklist' => ['security', 'text', '', ['max:5000']],
        // Outbound "official site" links in the radio/TV directories (PRD EX-04). Shown as on the prototype until the owner answers Q5.
        'directoryLinks' => ['seo', 'bool', true, []],
        // Newsletter e-mails sent per scheduler run (every minute): keep under the host's hourly mail limit / 60.
        'newsletterBatch' => ['seo', 'int', 50, ['min:1', 'max:1000']],
        'newsSitemap' => ['seo', 'bool', true, []],
        'rssFeed' => ['seo', 'bool', true, []],
        'popup' => ['seo', 'bool', true, []],
    ];

    public static function get(string $key): mixed
    {
        return static::all()[$key] ?? null;
    }

    /** Defaults overlaid with stored values; falls back to defaults when the table is not migrated yet (install, CLI). */
    public static function all(): array
    {
        $defaults = array_map(fn ($d) => $d[2], self::DEFINITIONS);
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (Throwable) {
            return $defaults;
        }
        foreach ($stored as $key => $json) {
            if (isset(self::DEFINITIONS[$key])) {
                $value = json_decode($json, true);
                $defaults[$key] = self::DEFINITIONS[$key][1] === 'secret' && $value !== '' ? Crypt::decryptString($value) : $value;
            }
        }

        return $defaults;
    }

    /** Saves the given values and returns the keys that actually changed (for the audit log). */
    public static function save(array $values): array
    {
        $current = static::all();
        $changed = [];
        foreach ($values as $key => $value) {
            if (! isset(self::DEFINITIONS[$key]) || $current[$key] === $value) {
                continue;
            }
            $stored = self::DEFINITIONS[$key][1] === 'secret' && $value !== '' ? Crypt::encryptString($value) : $value;
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($stored)]);
            $changed[] = $key;
        }
        Cache::forget(self::CACHE_KEY);

        return $changed;
    }

    /** Validation rules derived from the definitions. */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::DEFINITIONS as $key => [, $type, , $extra]) {
            $base = match (true) {
                $type === 'bool' => ['boolean'],
                $type === 'int' => ['required', 'integer'],
                $type === 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                str_starts_with($type, 'enum:') => ['required', 'in:'.substr($type, 5)],
                default => ['nullable', 'string'],
            };
            $rules[$key] = array_merge($base, $extra);
        }

        return $rules;
    }
}
