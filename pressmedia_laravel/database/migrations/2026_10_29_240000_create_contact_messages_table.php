<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages of the contact page; forwarded to the publisher support inbox (SUPPORT_MAIL) and answered from the admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 5);
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('organisation', 120)->nullable();
            $t->string('email', 150);
            $t->string('phone', 40)->nullable();
            $t->string('country', 80)->nullable();
            $t->string('city', 80)->nullable();
            $t->text('message');
            $t->string('status', 16)->default('new')->index();   // new | read | replied | spam
            $t->timestamp('consent_at');
            $t->timestamp('replied_at')->nullable();
            $t->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
