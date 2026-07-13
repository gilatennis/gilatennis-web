<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn(['tshirt_size', 'dietary']);
        });

        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->dropColumn(['tshirt_size', 'dietary']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->enum('tshirt_size', ['XS', 'S', 'M', 'L', 'XL', 'XXL'])->default('M');
            $table->text('dietary')->nullable();
        });

        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->enum('tshirt_size', ['XS', 'S', 'M', 'L', 'XL', 'XXL'])->default('M');
            $table->text('dietary')->nullable();
        });
    }
};
