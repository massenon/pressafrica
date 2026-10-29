<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 56 imported posts exist only in French: their English row has a translated title and excerpt but keeps the
 * French body, shown under a notice like the prototype does. body_locale records that, so the page can set lang="fr".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $t) {
            $t->string('body_locale', 5)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $t) {
            $t->dropColumn('body_locale');
        });
    }
};
