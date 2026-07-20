<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::statement("
            INSERT INTO tennis_match_states
                (tennis_match_id, server, first_server, set_a, set_b, game_a, game_b,
                 tiebreak, tb_a, tb_b, tb_first, sets, win, created_at, updated_at)
            SELECT
                id, 1, NULL, 0, 0, 0, 0, 0, 0, 0, NULL, '[]', NULL, ?, ?
            FROM tennis_matches
            WHERE id NOT IN (SELECT tennis_match_id FROM tennis_match_states)
        ", [$now, $now]);
    }

    public function down(): void
    {
        // Forward-fix only. Backfilled rows are indistinguishable from
        // organically-created ones, so rolling back would require a marker column.
    }
};
