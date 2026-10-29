<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdZone extends Model
{
    public const TYPES = ['banner', 'adsense', 'html'];

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'desktop' => 'array', 'mobile' => 'array'];

    /** Zones of the prototype (assets/js/admin.js), all inactive: their AdSense codes are placeholders. */
    public static function ensureDefaults(): void
    {
        foreach ([['sidebar_article', 'Article sidebar', 'Article page, sidebar (300x250)', '300x250', '300x250'],
            ['header_top', 'Header top', 'Below the logo band (728x90 / 320x100)', '728x90', '320x100'],
            ['in_article', 'In article', 'After the 3rd paragraph (640x200)', '640x200', '320x100']] as [$key, $name, $placement, $d, $m]) {
            static::firstOrCreate(['key' => $key], ['name' => $name, 'placement' => $placement, 'active' => false,
                'desktop' => ['enabled' => true, 'type' => 'banner', 'size' => $d], 'mobile' => ['enabled' => true, 'type' => 'banner', 'size' => $m]]);
        }
    }

    /** The configuration to show for a device, or null when nothing should be served there. */
    public function config(string $device): ?array
    {
        $c = $this->$device ?? [];
        if (! $this->active || empty($c['enabled'])) {
            return null;
        }
        $ready = match ($c['type'] ?? '') {
            'banner' => ! empty($c['media_id']) && ! empty($c['url']),
            'adsense' => ! empty($c['client']) && ! empty($c['slot']),
            'html' => ! empty($c['html']),
            default => false,
        };

        return $ready ? $c : null;
    }

    public function track(string $device, string $what): void
    {
        DB::table('ad_stats')->insertOrIgnore(['ad_zone_id' => $this->id, 'day' => now()->toDateString(), 'device' => $device]);
        DB::table('ad_stats')->where(['ad_zone_id' => $this->id, 'day' => now()->toDateString(), 'device' => $device])->increment($what);
    }
}
