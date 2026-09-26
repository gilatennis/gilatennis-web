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
            $table->foreignId('bracket_group_id')
                ->nullable()
                ->after('bracket_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bracket_group_id');
        });
    }
};
