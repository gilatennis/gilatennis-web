<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
        });

        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable(false)->change();
        });

        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('phone', 50)->nullable(false)->change();
        });
    }
};
