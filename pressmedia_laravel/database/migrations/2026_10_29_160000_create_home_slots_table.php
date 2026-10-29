<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-picked posts of the home page (hero carousel, latest block, economy pages): the prototype's editorial
 * selection, imported, later edited from Admin > Featured. Slots: hero, latest, econ_big, econ_small.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_slots', function (Blueprint $t) {
            $t->id();
            $t->string('slot', 20);
            $t->unsignedTinyInteger('position');
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->unique(['slot', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_slots');
    }
};
