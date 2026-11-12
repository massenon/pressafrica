<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail (PRD EX-28, INV-02). Rows are inserted, never updated or deleted:
 * the model refuses both and no route exposes them. Same fields as the prototype (assets/js/admin.js, AGP.audit).
 * The actor's name and role are copied so the entry still reads correctly after the user is renamed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_name')->nullable();
            $t->string('role')->nullable();
            $t->string('action', 40);           // login, login_failed, lockout, logout, token_create, token_revoke, create, update, delete...
            $t->string('module', 40)->index();  // auth or a permission module (posts, users, settings...)
            $t->string('target')->nullable();
            $t->text('detail')->nullable();
            $t->string('ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
