<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bracket_group_entrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['bracket_group_id', 'tournament_registration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bracket_group_entrants');
    }
};
