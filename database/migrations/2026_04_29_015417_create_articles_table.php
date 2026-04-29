<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('type', ['review', 'tip', 'strategy', 'fitness']);
            $table->string('kicker');
            $table->string('title');
            $table->text('excerpt');
            $table->longText('body')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('author');
            $table->unsignedInteger('reading_minutes');
            $table->boolean('is_published')->default(true);
            $table->datetime('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
