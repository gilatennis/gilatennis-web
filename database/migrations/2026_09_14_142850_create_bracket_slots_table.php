<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->unsignedInteger('position');
            $table->foreignId('entrant_a_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('entrant_b_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('winner_entrant_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('prev_slot_a_id')->nullable()->constrained('bracket_slots')->nullOnDelete();
            $table->foreignId('prev_slot_b_id')->nullable()->constrained('bracket_slots')->nullOnDelete();
            $table->unsignedInteger('seed')->nullable();
            $table->boolean('bye')->default(false);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['bracket_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_slots');
    }
};
