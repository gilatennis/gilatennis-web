<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('bracket_slot_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
            $table->foreignId('entrant_a_id')->nullable()->after('side_b_name')->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('entrant_b_id')->nullable()->after('entrant_a_id')->constrained('tournament_registrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('bracket_slot_id');
            $table->dropConstrainedForeignId('entrant_a_id');
            $table->dropConstrainedForeignId('entrant_b_id');
        });
    }
};
