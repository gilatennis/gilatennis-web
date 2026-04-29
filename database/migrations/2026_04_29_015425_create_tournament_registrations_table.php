<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Confirmation code
            $table->string('confirmation_code', 20)->unique();

            // Player info
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50);
            $table->date('date_of_birth');
            $table->enum('rating_type', ['UTR', 'ITN', 'USTA']);
            $table->decimal('rating_value', 4, 1);

            // Event selection
            $table->enum('event_type', ['singles', 'doubles', 'both']);
            $table->boolean('find_partner')->default(false);
            $table->string('partner_name')->nullable();
            $table->string('partner_email')->nullable();
            $table->enum('tshirt_size', ['XS', 'S', 'M', 'L', 'XL', 'XXL'])->default('M');
            $table->text('dietary')->nullable();

            // Payment
            $table->enum('payment_status', ['pending', 'processing', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('payment_url', 500)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('IDR');

            // Timestamps
            $table->datetime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_registrations');
    }
};
