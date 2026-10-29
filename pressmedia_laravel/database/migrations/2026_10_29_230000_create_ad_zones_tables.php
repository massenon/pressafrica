<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad zones (Admin > Ad zones): one configuration for desktop and one for mobile (banner, AdSense or sponsor HTML),
 * and daily impression/click counters per zone and device (EX-20: clicks and CTR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_zones', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('name', 80);
            $t->string('placement', 120)->nullable();
            $t->boolean('active')->default(false);
            $t->json('desktop')->nullable();   // {enabled, type: banner|adsense|html, size, media_id, url, client, slot, html}
            $t->json('mobile')->nullable();
            $t->timestamps();
        });
        Schema::create('ad_stats', function (Blueprint $t) {
            $t->foreignId('ad_zone_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->string('device', 8);
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->primary(['ad_zone_id', 'day', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats');
        Schema::dropIfExists('ad_zones');
    }
};
