<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('participation_category', ['singles', 'doubles', 'team']);
            $table->unsignedInteger('draw_size');
            $table->unsignedInteger('spots_left');
            $table->decimal('fee', 10, 2);
            $table->enum('gender', ['mens', 'womens', 'mixed', 'coed'])->nullable();
            $table->decimal('utr_min', 4, 1)->nullable();
            $table->decimal('utr_max', 4, 1)->nullable();
            $table->unsignedInteger('min_age')->nullable();
            $table->unsignedInteger('max_age')->nullable();
            $table->unsignedInteger('min_team_size')->nullable();
            $table->unsignedInteger('max_team_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
