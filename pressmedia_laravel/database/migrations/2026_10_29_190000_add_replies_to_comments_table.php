<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions and answers under articles (spec EX-43 to EX-46): one level of replies, newsroom replies signed by a
 * staff account, and the reader's opt-in to be e-mailed when the newsroom answers (owner decision S4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('post_id')->constrained('comments')->cascadeOnDelete();
            $t->foreignId('staff_user_id')->nullable()->after('moderated_by')->constrained('users')->nullOnDelete();
            $t->timestamp('notify_consent_at')->nullable()->after('consent_at');
            $t->timestamp('notified_at')->nullable()->after('notify_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
            $t->dropConstrainedForeignId('staff_user_id');
            $t->dropColumn(['notify_consent_at', 'notified_at']);
        });
    }
};
