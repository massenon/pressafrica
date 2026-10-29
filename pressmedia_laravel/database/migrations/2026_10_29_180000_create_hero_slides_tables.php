<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home carousel slides managed from Admin > Featured (spec EX-40). A slide either follows an article (image, title,
 * summary and link taken from it unless overridden) or is free (own image and texts). `placement` leaves room for
 * per-section carousels later (EX-52).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $t) {
            $t->id();
            $t->string('placement', 20)->default('home');
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('status', 16)->default('draft');      // draft | published
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->timestamps();
            $t->index(['placement', 'status', 'position']);
        });
        Schema::create('hero_slide_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hero_slide_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title')->nullable();
            $t->text('text')->nullable();
            $t->string('cta')->nullable();
            $t->string('url')->nullable();
            $t->string('alt')->nullable();
            $t->unique(['hero_slide_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slide_translations');
        Schema::dropIfExists('hero_slides');
    }
};
