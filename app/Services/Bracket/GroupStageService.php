<?php

namespace App\Services\Bracket;

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use App\Services\RoundRobinScheduler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns round-robin group stages: membership, fixtures, advancing-winner picks,
 * and promotion of the qualifiers into the division's knockout bracket.
 */
class GroupStageService
{
    public function __construct(
        private BracketStructure $structure,
        private KnockoutBracketService $knockout,
    ) {}

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
        string $seedingMode = BracketStructure::SEEDING_AUTO,
    ): Bracket {
        $entrants = $this->structure->paidEntrants($division);
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
                    'type' => BracketType::GroupStage,
                ],
                [
                    'status' => BracketStatus::Draft,
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

            if ($seedingMode === BracketStructure::SEEDING_AUTO) {
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

    /**
     * Record the entrants picked to advance from a group, in seed order. Picks
     * must be group members and must match the group's advance count.
     *
     * @param  array<int, int>  $winnerIds
     */
    public function setGroupWinners(BracketGroup $group, array $winnerIds): void
    {
        $winnerIds = array_values(array_map('intval', $winnerIds));

        if (count(array_unique($winnerIds)) !== count($winnerIds)) {
            throw new InvalidArgumentException('Each advancing winner may only be picked once.');
        }

        if (count($winnerIds) !== $group->advance_count) {
            throw new InvalidArgumentException("Pick exactly {$group->advance_count} advancing winner(s) for {$group->name}.");
        }

        $members = $group->entrantIds()->all();

        foreach ($winnerIds as $winnerId) {
            if (! in_array($winnerId, $members, true)) {
                throw new InvalidArgumentException('Every picked winner must be a member of the group.');
            }
        }

        $group->update(['winner_entrant_ids' => $winnerIds]);
    }

    /**
     * Promote the picked group winners into the division's single-elimination
     * bracket, seeded in the given order. Requires every group to have recorded
     * its advancing winners. A non-power-of-two qualifier count is allowed; the
     * resulting byes are reported by the caller as a warning.
     *
     * @param  array<int, int>  $winnerIdsInSeedOrder
     */
    public function promoteToKnockout(
        Division $division,
        array $winnerIdsInSeedOrder,
        bool $seedPairsApart = true,
    ): Bracket {
        $groupBracket = Bracket::where('division_id', $division->id)
            ->where('type', BracketType::GroupStage)
            ->first();

        if ($groupBracket === null) {
            throw new InvalidArgumentException('Generate a group stage before promoting winners.');
        }

        $groups = $groupBracket->groups()->get();
        $qualified = [];
        $groupIdByEntrant = [];

        foreach ($groups as $group) {
            $winners = $group->winnerIds();

            if (count($winners) !== $group->advance_count) {
                throw new InvalidArgumentException("Pick the advancing winners for {$group->name} first.");
            }

            foreach ($winners as $winnerId) {
                $qualified[] = $winnerId;
                $groupIdByEntrant[$winnerId] = $group->id;
            }
        }

        $provided = array_values(array_map('intval', $winnerIdsInSeedOrder));
        $sortedQualified = $qualified;
        $sortedProvided = $provided;

        sort($sortedQualified);
        sort($sortedProvided);

        if ($sortedQualified !== $sortedProvided) {
            throw new InvalidArgumentException('The promoted winners must match the winners picked from every group.');
        }

        // The caller's order is authoritative: keeping group-mates apart only
        // reorders the provided entrants, spreading each group's winners across
        // the bracket instead of replacing the order wholesale.
        $seedOrder = $seedPairsApart
            ? $this->interleaveByGroup($provided, $groupIdByEntrant)
            : $provided;

        $entrants = $this->structure->paidEntrants($division)->keyBy('id');

        $ordered = collect($seedOrder)
            ->map(fn (int $id) => $entrants->get($id))
            ->filter()
            ->unique('id')
            ->values();

        if ($ordered->count() < 2) {
            throw new InvalidArgumentException('At least two advancing winners are required to build the knockout bracket.');
        }

        return $this->knockout->writeSingleElimination($division, $ordered->all(), true);
    }

    /**
     * Reorder a provided seed list so entrants from the same group are spread
     * across the bracket instead of clustered. Groups are interleaved round-robin,
     * preserving each group's internal order.
     *
     * This is best-effort: for field shapes where a perfect split is impossible
     * some group-mates may still share a first-round tie (see
     * {@see self::firstRoundGroupMateClashes()}).
     *
     * @param  array<int, int>  $provided
     * @param  array<int, int>  $groupIdByEntrant
     * @return array<int, int>
     */
    private function interleaveByGroup(array $provided, array $groupIdByEntrant): array
    {
        $buckets = [];

        foreach ($provided as $id) {
            $buckets[$groupIdByEntrant[$id] ?? 0][] = $id;
        }

        if (count($buckets) < 2) {
            return $provided;
        }

        $max = max(array_map('count', $buckets));
        $order = [];

        for ($round = 0; $round < $max; $round++) {
            foreach ($buckets as $members) {
                if (isset($members[$round])) {
                    $order[] = $members[$round];
                }
            }
        }

        return $order;
    }

    /**
     * The number of first-round knockout ties pitting two entrants from the same
     * group against each other — the residue of a best-effort group-mate split.
     */
    public function firstRoundGroupMateClashes(Bracket $knockout, Division $division): int
    {
        $groupBracket = Bracket::where('division_id', $division->id)
            ->where('type', BracketType::GroupStage)
            ->first();

        if ($groupBracket === null) {
            return 0;
        }

        $groupIdByEntrant = [];

        foreach ($groupBracket->groups()->get() as $group) {
            foreach ($group->winnerIds() as $id) {
                $groupIdByEntrant[$id] = $group->id;
            }
        }

        return $knockout->slots()
            ->where('round', 1)
            ->get()
            ->filter(function (BracketSlot $slot) use ($groupIdByEntrant): bool {
                $a = $slot->entrant_a_id;
                $b = $slot->entrant_b_id;

                return $a !== null && $b !== null
                    && isset($groupIdByEntrant[$a], $groupIdByEntrant[$b])
                    && $groupIdByEntrant[$a] === $groupIdByEntrant[$b];
            })
            ->count();
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
     * Discard a group stage's ties, matches, and group rows so it can be
     * regenerated cleanly.
     */
    private function purgeGroupStructure(Bracket $bracket): void
    {
        $this->structure->purgeStructure($bracket);
        $bracket->groups()->delete();
    }
}
