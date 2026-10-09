<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A manual draw that is still being edited (draft, not locked) must not
     * carry any derived data: byes stay unresolved and the later rounds are
     * empty. Brackets created before draw_locked existed defaulted to unlocked
     * while still holding resolved byes, which left their bye slots uneditable.
     * Normalise them back to a clean, editable first round.
     */
    public function up(): void
    {
        $editingBracketIds = DB::table('brackets')
            ->where('manual_assignment', true)
            ->where('status', 'draft')
            ->where('draw_locked', false)
            ->pluck('id');

        if ($editingBracketIds->isEmpty()) {
            return;
        }

        DB::table('bracket_slots')
            ->whereIn('bracket_id', $editingBracketIds)
            ->where('round', 1)
            ->update([
                'bye' => false,
                'winner_entrant_id' => null,
                'advance_reason' => null,
            ]);

        DB::table('bracket_slots')
            ->whereIn('bracket_id', $editingBracketIds)
            ->where('round', '>', 1)
            ->update([
                'entrant_a_id' => null,
                'entrant_b_id' => null,
                'winner_entrant_id' => null,
                'advance_reason' => null,
            ]);
    }

    /**
     * The derived data cannot be reconstructed, so this migration is a one-way
     * normalisation.
     */
    public function down(): void
    {
        //
    }
};
