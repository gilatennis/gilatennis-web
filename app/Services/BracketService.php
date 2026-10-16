<?php

namespace App\Services;

use App\Enums\BracketType;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use App\Services\Bracket\BracketStructure;
use App\Services\Bracket\GroupStageService;
use App\Services\Bracket\GroupStandingsCalculator;
use App\Services\Bracket\KnockoutBracketService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Facade over the bracket feature. Group stages, the knockout bracket, and
 * standings live in dedicated services; this class keeps a single entry point
 * for controllers and other callers.
 */
class BracketService
{
    public const SEEDING_AUTO = BracketStructure::SEEDING_AUTO;

    public const SEEDING_MANUAL = BracketStructure::SEEDING_MANUAL;

    public function __construct(
        private GroupStageService $groups,
        private KnockoutBracketService $knockout,
        private GroupStandingsCalculator $standings,
    ) {}

    // --- Group stage ------------------------------------------------------

    /**
     * @param  array<int, int>  $advanceCounts
     */
    public function generateGroups(
        Division $division,
        int $groupCount,
        array $advanceCounts,
        bool $doubleRoundRobin = false,
        string $seedingMode = self::SEEDING_AUTO,
    ): Bracket {
        return $this->groups->generateGroups($division, $groupCount, $advanceCounts, $doubleRoundRobin, $seedingMode);
    }

    public function groupHasStarted(BracketGroup $group): bool
    {
        return $this->groups->groupHasStarted($group);
    }

    public function addEntrant(BracketGroup $group, TournamentRegistration $entrant): void
    {
        $this->groups->addEntrant($group, $entrant);
    }

    public function moveEntrant(BracketGroup $from, BracketGroup $to, TournamentRegistration $entrant): void
    {
        $this->groups->moveEntrant($from, $to, $entrant);
    }

    public function removeEntrant(Division $division, TournamentRegistration $entrant): void
    {
        $this->groups->removeEntrant($division, $entrant);
    }

    public function setGroupDoubleRoundRobin(BracketGroup $group, bool $doubleRoundRobin): void
    {
        $this->groups->setGroupDoubleRoundRobin($group, $doubleRoundRobin);
    }

    /**
     * @param  array<int, int>  $winnerIds
     */
    public function setGroupWinners(BracketGroup $group, array $winnerIds): void
    {
        $this->groups->setGroupWinners($group, $winnerIds);
    }

    /**
     * @param  array<int, int>  $winnerIdsInSeedOrder
     */
    public function promoteToKnockout(
        Division $division,
        array $winnerIdsInSeedOrder,
        bool $seedPairsApart = true,
    ): Bracket {
        return $this->groups->promoteToKnockout($division, $winnerIdsInSeedOrder, $seedPairsApart);
    }

    public function firstRoundGroupMateClashes(Bracket $knockout, Division $division): int
    {
        return $this->groups->firstRoundGroupMateClashes($knockout, $division);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(BracketGroup $group): Collection
    {
        return $this->standings->standings($group);
    }

    // --- Knockout ---------------------------------------------------------

    public function generate(Division $division, string $seedingMode = self::SEEDING_AUTO, bool $manualAssignment = false): Bracket
    {
        return $this->knockout->generate($division, $seedingMode, $manualAssignment);
    }

    /**
     * @param  array<int, int>  $entrantIds
     */
    public function seed(Bracket $bracket, array $entrantIds): void
    {
        $this->knockout->seed($bracket, $entrantIds);
    }

    public function setManualAssignment(Bracket $bracket, bool $manualAssignment): void
    {
        $this->knockout->setManualAssignment($bracket, $manualAssignment);
    }

    /**
     * @return Collection<int, TournamentRegistration>
     */
    public function eligibleEntrants(Bracket $bracket): Collection
    {
        return $this->knockout->eligibleEntrants($bracket);
    }

    public function assignSlotEntrant(BracketSlot $slot, string $side, ?TournamentRegistration $entrant): void
    {
        $this->knockout->assignSlotEntrant($slot, $side, $entrant);
    }

    public function completeDraw(Bracket $bracket): void
    {
        $this->knockout->completeDraw($bracket);
    }

    public function reopenDraw(Bracket $bracket): void
    {
        $this->knockout->reopenDraw($bracket);
    }

    public function undoWhileEditing(BracketSlot $slot): void
    {
        $this->knockout->undoWhileEditing($slot);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function startSlot(BracketSlot $slot, array $overrides = []): TennisMatch
    {
        return $this->knockout->startSlot($slot, $overrides);
    }

    public function removeRubber(TennisMatch $match): void
    {
        $this->knockout->removeRubber($match);
    }

    public function setWinner(BracketSlot $slot, ?TournamentRegistration $winner, ?string $reason = null): void
    {
        $this->knockout->setWinner($slot, $winner, $reason);
    }

    public function laterRoundsHaveProgress(BracketSlot $slot): bool
    {
        return $this->knockout->laterRoundsHaveProgress($slot);
    }

    public function delete(Bracket $bracket): void
    {
        $division = $bracket->division;
        $wasKnockout = $bracket->type === BracketType::SingleElimination;

        DB::transaction(function () use ($bracket, $division, $wasKnockout) {
            if (! $wasKnockout && $division !== null) {
                $this->groups->assertStageOpen($division);
            }

            $this->knockout->delete($bracket);

            if ($wasKnockout && $division !== null) {
                $this->groups->reopenAfterKnockoutDelete($division);
            }
        });
    }
}
