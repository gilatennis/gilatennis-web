<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tennis_match_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tennis_match_id')->unique()->constrained('tennis_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('server')->nullable();
            $table->unsignedTinyInteger('first_server')->nullable();
            $table->unsignedSmallInteger('set_a')->default(0);
            $table->unsignedSmallInteger('set_b')->default(0);
            $table->unsignedTinyInteger('game_a')->default(0);
            $table->unsignedTinyInteger('game_b')->default(0);
            $table->boolean('tiebreak')->default(false);
            $table->unsignedTinyInteger('tb_a')->default(0);
            $table->unsignedTinyInteger('tb_b')->default(0);
            $table->unsignedTinyInteger('tb_first')->nullable();
            $table->json('sets')->nullable();
            $table->unsignedTinyInteger('win')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tennis_match_states');
    }
};
