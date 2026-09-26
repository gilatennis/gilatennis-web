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
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_registration_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->json('raw_payload')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('result_code', 20)->nullable();
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['tournament_registration_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
