<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BracketService
{
    public const SEEDING_AUTO = 'auto';

    public const SEEDING_MANUAL = 'manual';

    /**
     * Generate a fresh group-stage bracket for a division, split into the
     * requested number of round-robin groups.
     *
     * In auto mode paid entrants are snake-distributed across the groups; in
     * manual mode the groups start empty for the admin to fill. The group stage
     * lives alongside (never replaces) a knockout bracket in the same division.
     *
     * @param  array<int, int>  $advanceCounts  Winners advancing per group, by position.
     */
    public function generateGroups(
        Division $division,
        int $groupCount,
        array $advanceCounts,
        bool $doubleRoundRobin = false,
        string $seedingMode = self::SEEDING_AUTO,
    ): Bracket {
        $entrants = $this->paidEntrants($division);
        $count = $entrants->count();

        if ($groupCount < 2) {
            throw new InvalidArgumentException('A group stage needs at least two groups.');
        }

        if ($count < $groupCount * 2) {
            throw new InvalidArgumentException('Each group needs at least two entrants.');
        }

        if ($count > $division->draw_size) {
            throw new InvalidArgumentException('Paid entrants exceed the configured draw size.');
        }

        if (count($advanceCounts) !== $groupCount) {
            throw new InvalidArgumentException('An advancing-winner count is required for every group.');
        }

        foreach ($advanceCounts as $advanceCount) {
            if ($advanceCount < 1) {
                throw new InvalidArgumentException('Each group must advance at least one entrant.');
            }
        }

        return DB::transaction(function () use ($division, $groupCount, $advanceCounts, $doubleRoundRobin, $seedingMode, $entrants) {
            $bracket = Bracket::updateOrCreate(
                [
                    'division_id' => $division->id,
                    'type' => Bracket::TYPE_GROUP_STAGE,
                ],
                [
                    'status' => Bracket::STATUS_DRAFT,
                    'size' => $entrants->count(),
                ],
            );

            $this->purgeGroupStructure($bracket);

            $groups = collect(range(0, $groupCount - 1))
                ->map(fn (int $position) => BracketGroup::create([
                    'bracket_id' => $bracket->id,
                    'name' => 'Group '.chr(65 + $position),
                    'position' => $position,
                    'advance_count' => $advanceCounts[$position],
                    'double_round_robin' => $doubleRoundRobin,
                ]));

            if ($seedingMode === self::SEEDING_AUTO) {
                $this->distributeEntrants($groups, $entrants->values()->all());
            }

            return $bracket->fresh(['groups.slots']);
        });
    }

    /**
     * Whether any tie in the group has been started or decided. Once a group is
     * live its membership and format are frozen so recorded results stay valid.
     */
    public function groupHasStarted(BracketGroup $group): bool
    {
        $slotIds = $group->slots()->pluck('id');

        if ($slotIds->isEmpty()) {
            return false;
        }

        if ($group->slots()->whereNotNull('winner_entrant_id')->exists()) {
            return true;
        }

        return TennisMatch::whereIn('bracket_slot_id', $slotIds)->exists();
    }

    /**
     * Add a paid entrant to a group, rebuilding its fixtures. Blocked once the
     * group has started.
     */
    public function addEntrant(BracketGroup $group, TournamentRegistration $entrant): void
    {
        $group->load('bracket');

        $this->assertGroupEditable($group);

        if ($entrant->division_id !== $group->bracket->division_id) {
            throw new InvalidArgumentException('That entrant belongs to a different division.');
        }

        $placed = BracketGroupEntrant::whereIn(
            'bracket_group_id',
            $group->bracket->groups()->pluck('id'),
        )->where('tournament_registration_id', $entrant->id)->exists();

        if ($placed) {
            throw new InvalidArgumentException('That entrant is already placed in this group stage.');
        }

        DB::transaction(function () use ($group, $entrant) {
            $this->placeEntrants($group, [$entrant], append: true);
        });
    }

    /**
     * Move an entrant from one group to another, rebuilding both fixtures.
     * Blocked when either group has started.
     */
    public function moveEntrant(BracketGroup $from, BracketGroup $to, TournamentRegistration $entrant): void
    {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('The entrant is already in that group.');
        }

        if ($from->bracket_id !== $to->bracket_id) {
            throw new InvalidArgumentException('Groups must belong to the same group stage.');
        }

        $this->assertGroupEditable($from);
        $this->assertGroupEditable($to);

        DB::transaction(function () use ($from, $to, $entrant) {
            $membership = BracketGroupEntrant::where('bracket_group_id', $from->id)
                ->where('tournament_registration_id', $entrant->id)
                ->first();

            if ($membership === null) {
                throw new InvalidArgumentException('That entrant is not in the source group.');
            }

            $membership->delete();

            $this->placeEntrants($to, [$entrant], append: true);
            $this->buildGroupFixtures($from);
        });
    }

    /**
     * Rebuild the group's fixtures under a different round-robin length.
     * Blocked once the group has started.
     */
    public function setGroupDoubleRoundRobin(BracketGroup $group, bool $doubleRoundRobin): void
    {
        $this->assertGroupEditable($group);

        DB::transaction(function () use ($group, $doubleRoundRobin) {
            $group->update(['double_round_robin' => $doubleRoundRobin]);

            $this->buildGroupFixtures($group);
        });
    }

    private function assertGroupEditable(BracketGroup $group): void
    {
        if ($this->groupHasStarted($group)) {
            throw new InvalidArgumentException('This group has already started; its fixtures are locked.');
        }
    }

    /**
     * Write membership rows for a group, then (re)build its fixtures. Membership
     * is the source of truth, so this works before any fixture can exist.
     *
     * @param  array<int, TournamentRegistration>  $entrants
     */
    private function placeEntrants(BracketGroup $group, array $entrants, bool $append = false): void
    {
        $offset = $append ? (int) $group->entrants()->max('position') + 1 : 0;

        foreach (array_values($entrants) as $index => $entrant) {
            BracketGroupEntrant::create([
                'bracket_group_id' => $group->id,
                'tournament_registration_id' => $entrant->id,
                'position' => $offset + $index,
            ]);
        }

        $this->buildGroupFixtures($group);
    }

    /**
     * Generate a fresh single-elimination bracket for a division.
     *
     * Any previously generated structure (and its matches) is discarded.
     * In auto mode, paid entrants are seeded immediately; in manual mode the
     * first round is left empty for the admin to seed later.
     */
    public function generate(Division $division, string $seedingMode = self::SEEDING_AUTO): Bracket
    {
        $entrants = $this->paidEntrants($division);
        $count = $entrants->count();

        if ($count < 2) {
            throw new InvalidArgumentException('At least two paid entrants are required to generate a bracket.');
        }

        if ($count > $division->draw_size) {
            throw new InvalidArgumentException('Paid entrants exceed the configured draw size.');
        }

        $size = $this->capacityFor($count);

        return DB::transaction(function () use ($division, $size, $entrants, $seedingMode) {
            $bracket = Bracket::updateOrCreate(
                ['division_id' => $division->id],
                [
                    'type' => Bracket::TYPE_SINGLE_ELIMINATION,
                    'status' => Bracket::STATUS_DRAFT,
                    'size' => $size,
                ],
            );

            $this->purgeStructure($bracket);
            $this->createSlots($bracket, $size);

            if ($seedingMode === self::SEEDING_AUTO) {
                $this->assignEntrants($bracket, $entrants->values()->all());
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
        $entrants = $this->paidEntrants($division)->keyBy('id');

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
                'side_a_name' => $slot->entrantA?->name ?? 'Side A',
                'side_b_name' => $slot->entrantB?->name ?? 'Side B',
                'status' => MatchStatus::Pending,
            ],
            $overrides,
        ));

        if ($slot->bracket->status === Bracket::STATUS_DRAFT) {
            $slot->bracket->update(['status' => Bracket::STATUS_ACTIVE]);
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
            $this->purgeStructure($bracket);
            $bracket->delete();
        });
    }

    /**
     * Compute the live standings table for a group, ordered best-first.
     *
     * A tie counts as a win for the group once its slot carries a winner; the
     * sets and games for/against come from every completed rubber attached to
     * the slot. Ties are broken by wins, then set difference, then game
     * difference, then head-to-head, then entrant name for stability.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(BracketGroup $group): Collection
    {
        $group->loadMissing([
            'entrants.registration.team',
            'slots.entrantA.team',
            'slots.entrantB.team',
            'slots.matches.state',
        ]);

        $entrants = [];

        foreach ($group->entrants as $membership) {
            $registration = $membership->registration;

            if ($registration !== null && ! isset($entrants[$registration->id])) {
                $entrants[$registration->id] = $this->standingRow($registration);
            }
        }

        foreach ($group->slots as $slot) {
            foreach ([$slot->entrantA, $slot->entrantB] as $entrant) {
                if ($entrant !== null && ! isset($entrants[$entrant->id])) {
                    $entrants[$entrant->id] = $this->standingRow($entrant);
                }
            }
        }

        $headToHead = [];

        foreach ($group->slots as $slot) {
            $this->applySlotToStandings($slot, $entrants, $headToHead);
        }

        $rows = array_values($entrants);

        usort($rows, function (array $a, array $b) use ($headToHead): int {
            $byWins = $b['wins'] <=> $a['wins'];

            if ($byWins !== 0) {
                return $byWins;
            }

            $setDiff = ($b['sets_for'] - $b['sets_against']) <=> ($a['sets_for'] - $a['sets_against']);

            if ($setDiff !== 0) {
                return $setDiff;
            }

            $gameDiff = ($b['games_for'] - $b['games_against']) <=> ($a['games_for'] - $a['games_against']);

            if ($gameDiff !== 0) {
                return $gameDiff;
            }

            $h2h = $headToHead[$b['entrant_id']][$a['entrant_id']] ?? 0;

            if ($h2h !== 0) {
                return $h2h;
            }

            return strcmp($a['name'], $b['name']);
        });

        $rank = 1;

        return collect($rows)->map(function (array $row) use (&$rank) {
            $row['set_diff'] = $row['sets_for'] - $row['sets_against'];
            $row['game_diff'] = $row['games_for'] - $row['games_against'];
            $row['rank'] = $rank++;

            return $row;
        });
    }

    /**
     * A zeroed standings row for an entrant.
     *
     * @return array<string, mixed>
     */
    private function standingRow(TournamentRegistration $entrant): array
    {
        return [
            'entrant_id' => $entrant->id,
            'name' => $entrant->team?->name ?? $entrant->name,
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'sets_for' => 0,
            'sets_against' => 0,
            'games_for' => 0,
            'games_against' => 0,
        ];
    }

    /**
     * Fold one tie into the running standings. Only decided ties move a win,
     * but any completed rubber contributes its sets and games.
     *
     * @param  array<int, array<string, mixed>>  $entrants
     * @param  array<int, array<int, int>>  $headToHead
     */
    private function applySlotToStandings(BracketSlot $slot, array &$entrants, array &$headToHead): void
    {
        $aId = $slot->entrant_a_id;
        $bId = $slot->entrant_b_id;

        if ($aId === null || $bId === null) {
            return;
        }

        if ($slot->winner_entrant_id !== null && isset($entrants[$aId], $entrants[$bId])) {
            $entrants[$aId]['played']++;
            $entrants[$bId]['played']++;

            if ($slot->winner_entrant_id === $aId) {
                $entrants[$aId]['wins']++;
                $entrants[$bId]['losses']++;
                $headToHead[$aId][$bId] = ($headToHead[$aId][$bId] ?? 0) + 1;
            } elseif ($slot->winner_entrant_id === $bId) {
                $entrants[$bId]['wins']++;
                $entrants[$aId]['losses']++;
                $headToHead[$bId][$aId] = ($headToHead[$bId][$aId] ?? 0) + 1;
            }
        }

        foreach ($slot->matches as $match) {
            $state = $match->state;

            if ($state === null || $state->win === null) {
                continue;
            }

            foreach ($state->sets ?? [] as $set) {
                $aGames = (int) ($set['a'] ?? 0);
                $bGames = (int) ($set['b'] ?? 0);

                if (isset($entrants[$aId])) {
                    $entrants[$aId]['games_for'] += $aGames;
                    $entrants[$aId]['games_against'] += $bGames;
                    $entrants[$aId]['sets_for'] += $aGames > $bGames ? 1 : 0;
                    $entrants[$aId]['sets_against'] += $bGames > $aGames ? 1 : 0;
                }

                if (isset($entrants[$bId])) {
                    $entrants[$bId]['games_for'] += $bGames;
                    $entrants[$bId]['games_against'] += $aGames;
                    $entrants[$bId]['sets_for'] += $bGames > $aGames ? 1 : 0;
                    $entrants[$bId]['sets_against'] += $aGames > $bGames ? 1 : 0;
                }
            }
        }
    }

    /**
     * @return Collection<int, TournamentRegistration>
     */
    private function paidEntrants(Division $division): Collection
    {
        return $division->orderedPaidRegistrations()->get();
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
     * Snake-distribute the seed-ordered entrants across the groups so the
     * strongest seeds are spread apart (group A, B, C, then C, B, A, ...).
     *
     * @param  Collection<int, BracketGroup>  $groups
     * @param  array<int, TournamentRegistration>  $entrants
     */
    private function distributeEntrants(Collection $groups, array $entrants): void
    {
        $groupCount = $groups->count();
        $buckets = array_fill(0, $groupCount, []);

        foreach ($entrants as $index => $entrant) {
            $row = intdiv($index, $groupCount);
            $column = $index % $groupCount;
            $target = $row % 2 === 0 ? $column : $groupCount - 1 - $column;

            $buckets[$target][] = $entrant;
        }

        foreach ($groups as $group) {
            $this->placeEntrants($group, $buckets[$group->position]);
        }
    }

    /**
     * Build (or rebuild) a group's round-robin fixtures from its membership.
     * Fixtures are laid out matchday-first: `round` is the matchday and
     * `position` is the fixture index within it. A group with fewer than two
     * entrants keeps its members but has no fixtures.
     */
    private function buildGroupFixtures(BracketGroup $group): void
    {
        $group->slots()->delete();

        $entrants = $group->entrants()
            ->with('registration')
            ->get()
            ->map(fn (BracketGroupEntrant $membership) => $membership->registration)
            ->filter()
            ->values()
            ->all();

        $count = count($entrants);

        if ($count < 2) {
            return;
        }

        $schedule = (new RoundRobinScheduler)->schedule($count, $group->double_round_robin);

        foreach ($schedule as $matchdayIndex => $fixtures) {
            foreach ($fixtures as $fixtureIndex => [$sideA, $sideB, $leg]) {
                BracketSlot::create([
                    'bracket_id' => $group->bracket_id,
                    'bracket_group_id' => $group->id,
                    'bracket_scope' => $group->id,
                    'round' => $matchdayIndex + 1,
                    'position' => $fixtureIndex,
                    'leg' => $leg,
                    'entrant_a_id' => $entrants[$sideA]->id,
                    'entrant_b_id' => $entrants[$sideB]->id,
                ]);
            }
        }
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

        if ($final !== null && $final->winner_entrant_id !== null) {
            $bracket->update(['status' => Bracket::STATUS_COMPLETED]);

            return;
        }

        if ($bracket->status === Bracket::STATUS_COMPLETED) {
            $bracket->update(['status' => Bracket::STATUS_ACTIVE]);

            return;
        }

        if ($bracket->status === Bracket::STATUS_DRAFT && $graph['slots']->contains(
            fn (BracketSlot $slot) => $slot->winner_entrant_id !== null,
        )) {
            $bracket->update(['status' => Bracket::STATUS_ACTIVE]);
        }
    }

    private function purgeStructure(Bracket $bracket): void
    {
        $slotIds = $bracket->slots()->pluck('id');

        if ($slotIds->isNotEmpty()) {
            TennisMatch::whereIn('bracket_slot_id', $slotIds)->delete();
        }

        $bracket->slots()->delete();
    }

    /**
     * Discard a group stage's ties, matches, and group rows so it can be
     * regenerated cleanly.
     */
    private function purgeGroupStructure(Bracket $bracket): void
    {
        $this->purgeStructure($bracket);
        $bracket->groups()->delete();
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
