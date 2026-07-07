<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->string('level', 32)->nullable()->after('rating_value');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->json('level_requirements')->nullable()->after('min_male_players');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registration_players', function (Blueprint $table) {
            $table->dropColumn('level');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn('level_requirements');
        });
    }
};
