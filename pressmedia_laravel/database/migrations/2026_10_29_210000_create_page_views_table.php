<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party visit log (owner decision Q7: option b). No cookie and no IP address: `visitor` is a hash of
 * IP + browser + day + app key, so a visitor is counted once a day and cannot be followed from one day to the next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $t) {
            $t->id();
            $t->timestamp('created_at')->index();
            $t->string('path', 255);
            $t->string('locale', 5);
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $t->string('section', 40)->nullable();
            $t->string('referrer_host', 120)->nullable();
            $t->string('country', 2)->nullable();
            $t->string('device', 10);
            $t->string('search', 100)->nullable();
            $t->char('visitor', 16);
            $t->index(['created_at', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
