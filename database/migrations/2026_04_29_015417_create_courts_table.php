<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('city');
            $table->enum('surface', ['hard', 'clay', 'grass', 'indoor_hard']);
            $table->decimal('rating', 2, 1);
            $table->unsignedInteger('review_count')->default(0);
            $table->enum('content_type', ['video', 'article']);
            $table->string('duration');
            $table->string('image_alt')->nullable();
            $table->text('excerpt');
            $table->boolean('is_featured')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
