<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('tagline')->nullable();
            $table->string('dates');
            $table->string('starts_label');
            $table->string('city');
            $table->string('venue')->nullable();
            $table->enum('surface', ['hard', 'clay', 'grass', 'indoor_hard']);
            $table->unsignedInteger('draw_size');
            $table->unsignedInteger('spots_left');
            $table->unsignedInteger('fee_cents');
            $table->decimal('utr_min', 4, 1)->nullable();
            $table->decimal('utr_max', 4, 1)->nullable();
            $table->string('format');
            $table->string('ball_type')->nullable();
            $table->datetime('registration_closes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'open', 'closed', 'completed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
