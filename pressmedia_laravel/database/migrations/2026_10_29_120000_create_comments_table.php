<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reader comments (EX-26). Only what the form asks for, plus the consent timestamp (GDPR proof);
 * no IP address is kept (data minimisation), abuse is limited by rate limiting and the honeypot instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('author', 80);
            $t->string('email', 100);
            $t->text('body');
            $t->string('status', 16)->default('pending')->index();   // pending | approved | spam
            $t->timestamp('consent_at');
            $t->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
