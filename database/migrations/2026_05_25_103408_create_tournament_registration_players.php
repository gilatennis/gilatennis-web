<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_registration_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_registration_id');
            $table->unsignedBigInteger('tournament_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50);
            $table->date('date_of_birth');
            $table->string('rating_type', 20)->nullable();
            $table->decimal('rating_value', 4, 1)->nullable();
            $table->enum('tshirt_size', ['XS', 'S', 'M', 'L', 'XL', 'XXL'])->default('M');
            $table->text('dietary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tournament_registration_id', 'trp_reg_fk')
                ->references('id')
                ->on('tournament_registrations')
                ->cascadeOnDelete();

            $table->foreign('tournament_id', 'trp_tourn_fk')
                ->references('id')
                ->on('tournaments')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'trp_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_registration_players');
    }
};
