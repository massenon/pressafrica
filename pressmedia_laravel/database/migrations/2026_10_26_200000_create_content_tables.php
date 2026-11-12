<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial core: categories, posts (+ FR/EN translations), media, podcasts.
 * Mirrors the HTML prototype data model (prototype_html/data/podcasts.json, generated articles).
 * Text lives in *_translations tables so a new language is a new row, not a new column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();            // stable key used in routes and permissions
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::create('category_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('name');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->string('seo_title')->nullable();
            $t->unique(['locale', 'slug']);
            $t->unique(['category_id', 'locale']);
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('path');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('alt_fr')->nullable();
            $t->string('alt_en')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $t->string('status', 16)->default('draft')->index();   // draft | review | published | scheduled
            $t->timestamp('published_at')->nullable()->index();
            $t->boolean('featured')->default(false);
            $t->unsignedInteger('views')->default(0);
            $t->unsignedBigInteger('legacy_id')->nullable()->unique(); // WordPress post id (import)
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('post_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title');
            $t->string('slug');
            $t->text('excerpt')->nullable();
            $t->longText('body');                                  // sanitised with mews/purifier before save
            $t->string('seo_title')->nullable();
            $t->string('seo_description', 320)->nullable();
            $t->unique(['locale', 'slug']);
            $t->unique(['post_id', 'locale']);
        });
        Schema::create('category_post', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'post_id']);
        });

        Schema::create('podcast_shows', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->timestamps();
        });
        Schema::create('podcast_show_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('podcast_show_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title');
            $t->text('description')->nullable();
            $t->unique(['podcast_show_id', 'locale']);
        });
        Schema::create('podcast_episodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('podcast_show_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();   // linked article
            $t->string('status', 16)->default('draft')->index();
            $t->date('published_on')->index();
            $t->unsignedInteger('duration')->default(0);                            // seconds
            $t->timestamps();
        });
        Schema::create('podcast_episode_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('podcast_episode_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('audio_path');                                               // one audio file per language
            $t->unique(['podcast_episode_id', 'locale']);
        });
    }

    public function down(): void
    {
        foreach (['podcast_episode_translations', 'podcast_episodes', 'podcast_show_translations', 'podcast_shows', 'category_post',
            'post_translations', 'posts', 'media', 'category_translations', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
