<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter campaigns sent by the scheduler in batches (cron `schedule:run` every minute: `newsletter:send`).
 * One delivery row per subscriber, so an interrupted run never sends twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('subject', 200);
            $t->longText('body');                                  // Quill HTML, sanitised
            $t->string('locale', 5)->default('all');               // fr | en | all
            $t->json('topics')->nullable();                        // null = every subscriber
            $t->string('status', 16)->default('draft')->index();   // draft | scheduled | sending | sent | cancelled
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('newsletter_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $t->string('status', 10);                              // sent | failed
            $t->string('error', 255)->nullable();
            $t->timestamp('sent_at');
            $t->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'nl_delivery_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
        Schema::dropIfExists('newsletter_campaigns');
    }
};
