<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Main navigation of the public site (Admin > Menus). Each entry links to a section and may open a mega panel:
 * latest articles, podcasts, videos, or the "press" panel (intro + picks + latest). Defaults = the prototype menu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('location', 20)->default('main');
            $t->string('key', 30)->unique();               // used for the panel id (mega-<key>)
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('panel', 16)->default('none');      // none | articles | podcasts | videos | press
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->json('data')->nullable();                  // articles: {"from_category":bool}; press: {"picks":[post ids]}
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('menu_item_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('label', 80);
            $t->string('url')->nullable();                 // overrides the section link (external or other page)
            $t->unique(['menu_item_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_items');
    }
};
