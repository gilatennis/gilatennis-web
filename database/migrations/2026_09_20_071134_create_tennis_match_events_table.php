<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tennis_match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tennis_match_id')->constrained('tennis_matches')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('type', 20);
            $table->unsignedTinyInteger('side')->nullable();
            $table->string('shot', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tennis_match_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tennis_match_events');
    }
};
