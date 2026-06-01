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
        Schema::table('divisions', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_female_players')->nullable()->after('max_team_size');
            $table->unsignedTinyInteger('min_male_players')->nullable()->after('min_female_players');
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['min_female_players', 'min_male_players']);
        });
    }
};
