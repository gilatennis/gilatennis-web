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
        Schema::table('bracket_groups', function (Blueprint $table) {
            $table->json('winner_entrant_ids')->nullable()->after('double_round_robin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_groups', function (Blueprint $table) {
            $table->dropColumn('winner_entrant_ids');
        });
    }
};
