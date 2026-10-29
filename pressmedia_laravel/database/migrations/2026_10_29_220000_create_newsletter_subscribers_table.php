<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter subscribers with double opt-in: pending until the link in the confirmation e-mail is followed,
 * consent timestamped, one-click unsubscribe with the same secret token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email', 150)->unique();
            $t->string('locale', 5)->default('fr');
            $t->json('topics')->nullable();
            $t->string('status', 16)->default('pending')->index();   // pending | confirmed | unsubscribed
            $t->string('token', 64)->unique();
            $t->string('source', 20)->default('site');                 // site | article | import | admin
            $t->timestamp('consent_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
