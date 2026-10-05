<?php

namespace App\Services\Bracket;

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns single-elimination brackets: slot trees, seeding, byes, and the
 * propagation of winners through the slot graph. Slot operations are shared
 * with the group stage, whose ties reuse the same slot model.
 */
class KnockoutBracketService
{
    public function __construct(private BracketStructure $structure) {}

    /**
     * Generate a fresh single-elimination bracket for a division.
     *
     * Any previously generated structure (and its matches) is discarded.
     * In auto mode, paid entrants are seeded immediately; in manual mode the
     * first round is left empty for the admin to seed later.
     */
    public function generate(Division $division, string $seedingMode = BracketStructure::SEEDING_AUTO): Bracket
    {
        $entrants = $this->structure->paidEntrants($division);
        $count = $entrants->count();

        if ($count < 2) {
            throw new InvalidArgumentException('At least two paid entrants are required to generate a bracket.');
        }

        if ($count > $division->draw_size) {
            throw new InvalidArgumentException('Paid entrants exceed the configured draw size.');
        }

        return $this->writeSingleElimination(
            $division,
            $entrants->values()->all(),
            $seedingMode === BracketStructure::SEEDING_AUTO,
        );
    }

    /**
     * Create (or regenerate) the division's single-elimination bracket with a
     * fresh slot tree, seeding the given entrants in order when asked.
     *
     * @param  array<int, TournamentRegistration>  $entrants
     */
    public function writeSingleElimination(Division $division, array $entrants, bool $assign): Bracket
    {
        $size = $this->capacityFor(count($entrants));

        return DB::transaction(function () use ($division, $entrants, $size, $assign) {
            $bracket = Bracket::updateOrCreate(
                [
                    'division_id' => $division->id,
                    'type' => BracketType::SingleElimination,
                ],
                [
                    'status' => BracketStatus::Draft,
                    'size' => $size,
                ],
            );

            $this->structure->purgeStructure($bracket);
            $this->createSlots($bracket, $size);

            if ($assign) {
                $this->assignEntrants($bracket, $entrants);
            }

            return $bracket->fresh(['slots']);
        });
    }

    /**
     * Seed (or re-seed) the first round from an ordered list of entrant ids.
     * The first id is seed 1, the second is seed 2, and so on.
     *
     * @param  array<int, int>  $entrantIds
     */
    public function seed(Bracket $bracket, array $entrantIds): void
    {
        $division = $bracket->division;
        $entrants = $this->structure->paidEntrants($division)->keyBy('id');

        $ordered = collect($entrantIds)
            ->map(fn ($id) => $entrants->get((int) $id))
            ->filter()
            ->unique('id')
            ->values();

        if ($ordered->count() !== $entrants->count()) {
            throw new InvalidArgumentException('Every paid entrant must be seeded exactly once.');
        }

        DB::transaction(function () use ($bracket, $ordered) {
            $this->assignEntrants($bracket, $ordered->all());
        });
    }

    /**
     * Create a match for a ready bracket slot. Team divisions may create
     * multiple matches per slot (one per individual rubber).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function startSlot(BracketSlot $slot, array $overrides = []): TennisMatch
    {
        if ($slot->winner_entrant_id !== null) {
            throw new InvalidArgumentException('This slot has already been decided.');
        }

        if ($slot->entrant_a_id === null || $slot->entrant_b_id === null) {
            throw new InvalidArgumentException('Both entrants must be known before starting this slot.');
        }

        $slot->loadMissing(['bracket.division', 'entrantA', 'entrantB']);
        $division = $slot->bracket->division;

        $match = TennisMatch::create(array_merge(
            $this->defaultFormat(),
            [
                'division_id' => $division->id,
                'bracket_slot_id' => $slot->id,
                'entrant_a_id' => $slot->entrant_a_id,
                'entrant_b_id' => $slot->entrant_b_id,
                'side_a_name' => $slot->entrantA->name ?? 'Side A',
                'side_b_name' => $slot->entrantB->name ?? 'Side B',
                'status' => MatchStatus::Pending,
            ],
            $overrides,
        ));

        if ($slot->bracket->status === BracketStatus::Draft) {
            $slot->bracket->update(['status' => BracketStatus::Active]);
        }

        return $match;
    }

    /**
     * Explicitly award a slot to an entrant. This is the only path that
     * advances a slot; match results never propagate automatically. Passing
     * null clears the result and every downstream entrant/winner.
     *
     * The optional reason records why a slot was decided without a completed
     * result (walkover, retirement, disqualification) for reporting.
     */
    public function setWinner(BracketSlot $slot, ?TournamentRegistration $winner, ?string $reason = null): void
    {
        $slot->loadMissing('bracket');
        $bracket = $slot->bracket;

        DB::transaction(function () use ($slot, $bracket, $winner, $reason) {
            $graph = $this->loadSlotGraph($bracket);
            $slot = $graph['slots'][$slot->id];

            $slot->winner_entrant_id = $winner?->id;
            $slot->advance_reason = $winner === null ? null : $reason;
            $slot->save();

            if ($winner !== null) {
                $this->propagate($slot, $graph);
            } else {
                $this->clearDescendants($slot, $graph);
                $this->resolveByes($bracket, $graph);
            }

            $this->syncStatus($bracket, $graph);
        });
    }

    /**
     * A slot may only be undone from the bottom up: every slot that consumes
     * its winner (and their successors) must be untouched. Clearing it while a
     * later round carries a winner or a started match would silently orphan
     * that progress.
     */
    public function laterRoundsHaveProgress(BracketSlot $slot): bool
    {
        $slot->loadMissing('bracket');

        $graph = $this->loadSlotGraph($slot->bracket);
        $ancestorIds = [];
        $currentId = $slot->id;

        while (($parent = $graph['parents'][$currentId] ?? null) !== null) {
            $ancestorIds[] = $parent['id'];
            $currentId = $parent['id'];
        }

        if ($ancestorIds === []) {
            return false;
        }

        $decided = $graph['slots']
            ->only($ancestorIds)
            ->contains(fn (BracketSlot $ancestor) => $ancestor->winner_entrant_id !== null);

        if ($decided) {
            return true;
        }

        return TennisMatch::whereIn('bracket_slot_id', $ancestorIds)->exists();
    }

    public function delete(Bracket $bracket): void
    {
        DB::transaction(function () use ($bracket) {
            $this->structure->purgeStructure($bracket);
            $bracket->delete();
        });
    }

    private function capacityFor(int $count): int
    {
        $size = 2;

        while ($size < $count) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * Standard bracket seed order: index = position, value = seed number.
     *
     * @return array<int, int>
     */
    private function seedOrder(int $size): array
    {
        $order = [1];

        for ($n = 2; $n <= $size; $n *= 2) {
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $n + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }

    private function createSlots(Bracket $bracket, int $size): void
    {
        $totalRounds = (int) log($size, 2);
        $byRound = [];

        for ($round = 1; $round <= $totalRounds; $round++) {
            $slotsInRound = intdiv($size, 2 ** $round);

            for ($position = 0; $position < $slotsInRound; $position++) {
                $byRound[$round][$position] = BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round' => $round,
                    'position' => $position,
                    'label' => $this->roundLabel($round, $totalRounds),
                ]);
            }
        }

        for ($round = 2; $round <= $totalRounds; $round++) {
            foreach ($byRound[$round] as $position => $slot) {
                $slot->prev_slot_a_id = $byRound[$round - 1][2 * $position]->id;
                $slot->prev_slot_b_id = $byRound[$round - 1][2 * $position + 1]->id;
                $slot->save();
            }
        }
    }

    /**
     * @param  array<int, TournamentRegistration>  $entrants
     */
    private function assignEntrants(Bracket $bracket, array $entrants): void
    {
        $size = $bracket->size;
        $order = $this->seedOrder($size);
        $count = count($entrants);
        $firstRound = $bracket->slots()->where('round', 1)->get()->keyBy('position');

        for ($position = 0; $position < intdiv($size, 2); $position++) {
            $slot = $firstRound[$position];
            $seedA = $order[2 * $position];
            $seedB = $order[2 * $position + 1];

            $entrantA = $seedA <= $count ? $entrants[$seedA - 1] : null;
            $entrantB = $seedB <= $count ? $entrants[$seedB - 1] : null;

            $slot->entrant_a_id = $entrantA?->id;
            $slot->entrant_b_id = $entrantB?->id;
            $slot->seed = min($seedA, $seedB);
            $slot->bye = $entrantA === null || $entrantB === null;
            $slot->winner_entrant_id = null;
            $slot->advance_reason = null;
            $slot->save();
        }

        $bracket->slots()->where('round', '>', 1)->update([
            'entrant_a_id' => null,
            'entrant_b_id' => null,
            'winner_entrant_id' => null,
            'advance_reason' => null,
        ]);

        $this->resolveByes($bracket, $this->loadSlotGraph($bracket));
    }

    private function resolveByes(Bracket $bracket, array $graph): void
    {
        foreach ($graph['slots'] as $slot) {
            if ($slot->round !== 1 || ! $slot->bye || $slot->winner_entrant_id !== null) {
                continue;
            }

            $winnerId = $slot->entrant_a_id ?? $slot->entrant_b_id;

            if ($winnerId === null) {
                continue;
            }

            $slot->winner_entrant_id = $winnerId;
            $slot->advance_reason = null;
            $slot->save();

            $this->propagate($slot, $graph);
        }
    }

    /**
     * Load the bracket structure once and index the parent/child links by
     * slot id, avoiding per-step queries while advancing winners.
     *
     * @return array{
     *     slots: Collection<int, BracketSlot>,
     *     parents: array<int, array{id: int, side: string}>,
     *     children: array<int, array<int, array{id: int, side: string}>>,
     *     final: ?BracketSlot
     * }
     */
    private function loadSlotGraph(Bracket $bracket): array
    {
        $slots = $bracket->slots()->get()->keyBy('id');
        $parents = [];
        $children = [];
        $final = null;

        foreach ($slots as $slot) {
            if ($slot->prev_slot_a_id !== null) {
                $parents[$slot->prev_slot_a_id] = ['id' => $slot->id, 'side' => 'a'];
                $children[$slot->prev_slot_a_id][] = ['id' => $slot->id, 'side' => 'a'];
            }

            if ($slot->prev_slot_b_id !== null) {
                $parents[$slot->prev_slot_b_id] = ['id' => $slot->id, 'side' => 'b'];
                $children[$slot->prev_slot_b_id][] = ['id' => $slot->id, 'side' => 'b'];
            }

            if ($final === null
                || $slot->round > $final->round
                || ($slot->round === $final->round && $slot->position < $final->position)) {
                $final = $slot;
            }
        }

        return [
            'slots' => $slots,
            'parents' => $parents,
            'children' => $children,
            'final' => $final,
        ];
    }

    private function propagate(BracketSlot $slot, array $graph): void
    {
        $parent = $graph['parents'][$slot->id] ?? null;

        if ($parent === null) {
            return;
        }

        $parentSlot = $graph['slots'][$parent['id']];

        if ($parent['side'] === 'a') {
            $parentSlot->entrant_a_id = $slot->winner_entrant_id;
        } else {
            $parentSlot->entrant_b_id = $slot->winner_entrant_id;
        }

        $parentSlot->save();
    }

    private function clearDescendants(BracketSlot $slot, array $graph): void
    {
        foreach ($graph['children'][$slot->id] ?? [] as $child) {
            $childSlot = $graph['slots'][$child['id']];

            if ($child['side'] === 'a') {
                $childSlot->entrant_a_id = null;
            } else {
                $childSlot->entrant_b_id = null;
            }

            $childSlot->winner_entrant_id = null;
            $childSlot->advance_reason = null;
            $childSlot->save();

            $this->clearDescendants($childSlot, $graph);
        }
    }

    private function syncStatus(Bracket $bracket, array $graph): void
    {
        $final = $graph['final'];

        // A group stage is never won by a single tie — it is only completed by
        // promotion into the knockout — so a decided matchday must not mark the
        // group bracket complete.
        if (! $bracket->isGroupStage() && $final !== null && $final->winner_entrant_id !== null) {
            $bracket->update(['status' => BracketStatus::Completed]);

            return;
        }

        if ($bracket->status === BracketStatus::Completed) {
            $bracket->update(['status' => BracketStatus::Active]);

            return;
        }

        if ($bracket->status === BracketStatus::Draft && $graph['slots']->contains(
            fn (BracketSlot $slot) => $slot->winner_entrant_id !== null,
        )) {
            $bracket->update(['status' => BracketStatus::Active]);
        }
    }

    /**
     * @return array<string, int|bool>
     */
    private function defaultFormat(): array
    {
        return [
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ];
    }

    private function roundLabel(int $round, int $totalRounds): ?string
    {
        return match ($totalRounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Quarterfinal',
            3 => 'Round of 16',
            4 => 'Round of 32',
            default => null,
        };
    }
}
