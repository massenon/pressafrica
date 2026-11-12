<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keyword tags (EX-23). Language-neutral like the prototype: one name used on both FR and EN pages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 100)->unique();
            $t->timestamps();
        });
        Schema::create('post_tag', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
    }
};
