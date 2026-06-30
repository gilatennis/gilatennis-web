<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tennis_matches', function (Blueprint $table) {
            $table->id();
            $table->string('side_a_name', 60);
            $table->string('side_b_name', 60);
            $table->unsignedTinyInteger('sets_to_win');
            $table->unsignedTinyInteger('games_to_win')->default(6);
            $table->boolean('extend_at_tie')->default(false);
            $table->unsignedTinyInteger('tiebreak_to');
            $table->enum('status', ['pending', 'running', 'completed', 'canceled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tennis_matches');
    }
};
