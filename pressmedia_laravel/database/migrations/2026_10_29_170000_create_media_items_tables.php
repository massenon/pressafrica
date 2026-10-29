<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One store for the media sections (EX-01 to EX-06): podcast shows and episodes, videos, magazine issues,
 * radio stations, TV channels. Type-specific fields live in `data` (see App\Models\MediaItem::TYPES).
 * Replaces the unused podcast_* tables of the first schema so there is a single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['podcast_episode_translations', 'podcast_episodes', 'podcast_show_translations', 'podcast_shows'] as $old) {
            Schema::dropIfExists($old);
        }
        Schema::create('media_items', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20)->index();            // podcast_show, podcast, video, magazine, radio, tv
            $t->string('key', 60)->unique();             // stable id from the prototype (ep-001, vid-001, mag-006...)
            $t->string('status', 16)->default('draft')->index();   // draft | published
            $t->date('published_on')->nullable()->index();
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();   // linked article / cover story
            $t->json('data')->nullable();
            $t->timestamps();
        });
        Schema::create('media_item_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('file')->nullable();              // per-language file (podcast audio)
            $t->unique(['media_item_id', 'locale']);
        });
        Schema::create('media_item_post', function (Blueprint $t) {   // magazine contents, in order
            $t->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->primary(['media_item_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_item_post');
        Schema::dropIfExists('media_item_translations');
        Schema::dropIfExists('media_items');
    }
};
