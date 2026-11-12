<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Imported posts keep the prototype's reading time (counted on the WordPress text); new posts compute it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', fn (Blueprint $t) => $t->unsignedSmallInteger('reading_minutes')->nullable()->after('body_locale'));
    }

    public function down(): void
    {
        Schema::table('post_translations', fn (Blueprint $t) => $t->dropColumn('reading_minutes'));
    }
};
