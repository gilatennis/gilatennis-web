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
        // The bracket_id foreign key leans on the (bracket_id, round, position)
        // index, so it must come down before the index can be replaced.
        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropForeign(['bracket_id']);
        });

        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropUnique(['bracket_id', 'round', 'position']);

            $table->foreignId('bracket_group_id')
                ->nullable()
                ->after('bracket_id')
                ->constrained()
                ->nullOnDelete();

            // Round-robin fixtures restart at round 1 inside every group, so the
            // position must be scoped per group. Tree slots share scope 0; group
            // fixtures carry their group id. This keeps MySQL able to enforce
            // uniqueness for both bracket shapes at once.
            $table->unsignedBigInteger('bracket_scope')->default(0)->after('bracket_group_id');
        });

        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->unique(['bracket_id', 'bracket_scope', 'round', 'position']);
            $table->foreign('bracket_id')->references('id')->on('brackets')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropForeign(['bracket_id']);
        });

        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->dropUnique(['bracket_id', 'bracket_scope', 'round', 'position']);
            $table->dropColumn('bracket_scope');
            $table->dropConstrainedForeignId('bracket_group_id');
        });

        Schema::table('bracket_slots', function (Blueprint $table) {
            $table->unique(['bracket_id', 'round', 'position']);
            $table->foreign('bracket_id')->references('id')->on('brackets')->cascadeOnDelete();
        });
    }
};
