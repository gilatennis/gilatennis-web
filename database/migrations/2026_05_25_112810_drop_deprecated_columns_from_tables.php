<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['utr_min', 'utr_max']);
        });

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'rating_type',
                'rating_value',
                'event_type',
                'find_partner',
                'partner_name',
                'partner_email',
            ]);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['utr_min', 'utr_max', 'event_types']);
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->decimal('utr_min', 4, 1)->nullable();
            $table->decimal('utr_max', 4, 1)->nullable();
        });

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->string('rating_type', 20)->nullable();
            $table->decimal('rating_value', 4, 1)->nullable();
            $table->enum('event_type', ['singles', 'doubles', 'both']);
            $table->boolean('find_partner')->default(false);
            $table->string('partner_name')->nullable();
            $table->string('partner_email')->nullable();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->decimal('utr_min', 4, 1)->nullable();
            $table->decimal('utr_max', 4, 1)->nullable();
            $table->json('event_types')->nullable();
        });
    }
};
