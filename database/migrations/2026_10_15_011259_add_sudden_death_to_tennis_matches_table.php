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
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->boolean('sudden_death')->default(true)->after('extend_tiebreak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->dropColumn('sudden_death');
        });
    }
};
