<?php

namespace App\Services\Bracket;

use App\Enums\BracketStatus;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Collection;

/**
 * Small shared operations used by both the group-stage and knockout services.
 */
class BracketStructure
{
    public const SEEDING_AUTO = 'auto';

    public const SEEDING_MANUAL = 'manual';

    /**
     * The paid entrants for a division, in registration order.
     *
     * @return Collection<int, TournamentRegistration>
     */
    public function paidEntrants(Division $division): Collection
    {
        return $division->orderedPaidRegistrations()->get();
    }

    /**
     * Discard a bracket's slots and their matches.
     */
    public function purgeStructure(Bracket $bracket): void
    {
        $slotIds = $bracket->slots()->pluck('id');

        if ($slotIds->isNotEmpty()) {
            TennisMatch::whereIn('bracket_slot_id', $slotIds)->delete();
        }

        $bracket->slots()->delete();
    }

    /**
     * Whether play has begun anywhere in the division's draw. A bracket only
     * becomes non-draft (or gains a started match / decided slot) once a tie is
     * under way, so any of these means the draw is final and must not be
     * reshaped by withdrawing an entrant.
     */
    public function stageStarted(Division $division): bool
    {
        $bracketIds = Bracket::where('division_id', $division->id)->pluck('id');

        if ($bracketIds->isEmpty()) {
            return false;
        }

        if (Bracket::whereIn('id', $bracketIds)->where('status', '!=', BracketStatus::Draft)->exists()) {
            return true;
        }

        $slotIds = BracketSlot::whereIn('bracket_id', $bracketIds)->pluck('id');

        if ($slotIds->isEmpty()) {
            return false;
        }

        if (BracketSlot::whereIn('id', $slotIds)->whereNotNull('winner_entrant_id')->exists()) {
            return true;
        }

        return TennisMatch::whereIn('bracket_slot_id', $slotIds)->exists();
    }
}
