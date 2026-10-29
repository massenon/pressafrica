<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section pages use one of the prototype's three layouts (archive, thematic, landing); thematic pages show a
 * heading that differs from the menu name ("Actualités Politiques" for "Politique").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->string('layout', 16)->default('archive')->after('key');
        });
        Schema::table('category_translations', function (Blueprint $t) {
            $t->string('heading')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('layout'));
        Schema::table('category_translations', fn (Blueprint $t) => $t->dropColumn('heading'));
    }
};
