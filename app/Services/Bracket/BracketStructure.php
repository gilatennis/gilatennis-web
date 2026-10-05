<?php

namespace App\Services\Bracket;

use App\Models\Bracket;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;

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
}
