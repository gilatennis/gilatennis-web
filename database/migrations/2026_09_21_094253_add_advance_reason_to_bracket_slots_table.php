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
        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->string('advance_reason', 32)->nullable()->after('winner_entrant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropColumn('advance_reason');
        });
    }
};
