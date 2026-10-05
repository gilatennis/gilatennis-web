<?php

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;

/*
|--------------------------------------------------------------------------
| Group standings
|--------------------------------------------------------------------------
|
| A standings table is just its entrants sorted by the ranking comparator, and
| that comparator *is* the tie-break chain:
|
|     wins → set difference → game difference → head-to-head → name
|
| So this file pins each link of that chain rather than treating "standings" and
| "tie-breaks" as separate features: wins ordering, the set/game counters the
| tie-breaks consume, set-difference, game-difference, and head-to-head
| resolution, and the undecided-tie gate. The full lifecycle e2e
| (tests/Feature/EndToEnd/GroupStageLifecycleTest.php) asserts only the wins
| cohort and delegates ordering to this file.
|
*/

function standingsDivision(int $drawSize = 8): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'draw_size' => $drawSize,
    ]);
}

/**
 * @return array<int, TournamentRegistration>
 */
function standingsEntrants(Division $division, int $count): array
{
    return collect(range(1, $count))
        ->map(fn ($i) => TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]))
        ->all();
}

/**
 * Decide a tie outright and record a single completed match with the given
 * set scores.
 *
 * @param  array<int, array{0: int, 1: int}>  $sets
 */
function decideTie(BracketSlot $slot, int $winnerId, array $sets): void
{
    $match = TennisMatch::factory()->create([
        'division_id' => $slot->bracket->division_id,
        'bracket_slot_id' => $slot->id,
        'entrant_a_id' => $slot->entrant_a_id,
        'entrant_b_id' => $slot->entrant_b_id,
        'side_a_name' => 'A',
        'side_b_name' => 'B',
        'status' => MatchStatus::Completed,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
    ]);

    $aSets = collect($sets)->filter(fn ($set) => $set[0] > $set[1])->count();
    $bSets = collect($sets)->filter(fn ($set) => $set[1] > $set[0])->count();

    $gamesA = collect($sets)->sum(fn ($set) => $set[0]);
    $gamesB = collect($sets)->sum(fn ($set) => $set[1]);

    $match->state()->update([
        'sets' => collect($sets)->map(fn ($set) => ['a' => $set[0], 'b' => $set[1]])->all(),
        'set_a' => $aSets,
        'set_b' => $bSets,
        'game_a' => $gamesA,
        'game_b' => $gamesB,
        'win' => $winnerId === $slot->entrant_a_id ? 1 : 2,
    ]);

    app(BracketService::class)->setWinner($slot, TournamentRegistration::find($winnerId));
}

function groupStageWithFour(Division $division): BracketGroup
{
    $bracket = Bracket::factory()->forDivision($division)->create([
        'type' => Bracket::TYPE_GROUP_STAGE,
    ]);

    return BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();
}

describe('Group standings', function () {
    it('ranks entrants by wins', function () {
        $division = standingsDivision();
        [$a, $b, $c, $d] = standingsEntrants($division, 4);
        $group = groupStageWithFour($division);

        [$ab, $cd, $ac, $bd] = [
            BracketSlot::factory()->inGroup($group)->round(1, 0)->create(['entrant_a_id' => $a->id, 'entrant_b_id' => $b->id]),
            BracketSlot::factory()->inGroup($group)->round(1, 1)->create(['entrant_a_id' => $c->id, 'entrant_b_id' => $d->id]),
            BracketSlot::factory()->inGroup($group)->round(2, 0)->create(['entrant_a_id' => $a->id, 'entrant_b_id' => $c->id]),
            BracketSlot::factory()->inGroup($group)->round(2, 1)->create(['entrant_a_id' => $b->id, 'entrant_b_id' => $d->id]),
        ];

        decideTie($ab, $a->id, [[6, 3], [6, 4]]);
        decideTie($cd, $c->id, [[6, 2], [6, 1]]);
        decideTie($ac, $a->id, [[6, 4], [6, 3]]);
        decideTie($bd, $b->id, [[6, 1], [6, 2]]);

        $rows = app(BracketService::class)->standings($group);

        expect($rows->pluck('name')->all())->toBe(['Entrant 1', 'Entrant 2', 'Entrant 3', 'Entrant 4'])
            ->and($rows->firstWhere('entrant_id', $a->id)['wins'])->toBe(2)
            ->and($rows->firstWhere('entrant_id', $b->id)['wins'])->toBe(1)
            ->and($rows->firstWhere('entrant_id', $c->id)['wins'])->toBe(1)
            ->and($rows->firstWhere('entrant_id', $d->id)['wins'])->toBe(0);
    });

    it('counts played ties and sets and games for and against', function () {
        $division = standingsDivision();
        [$a, $b] = standingsEntrants($division, 2);
        $group = groupStageWithFour($division);

        $slot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create([
            'entrant_a_id' => $a->id,
            'entrant_b_id' => $b->id,
        ]);

        decideTie($slot, $a->id, [[6, 4], [3, 6], [6, 2]]);

        $rows = app(BracketService::class)->standings($group);
        $winner = $rows->firstWhere('entrant_id', $a->id);
        $loser = $rows->firstWhere('entrant_id', $b->id);

        expect($winner['played'])->toBe(1)
            ->and($winner['sets_for'])->toBe(2)
            ->and($winner['sets_against'])->toBe(1)
            ->and($winner['games_for'])->toBe(15)
            ->and($winner['games_against'])->toBe(12)
            ->and($winner['set_diff'])->toBe(1)
            ->and($loser['played'])->toBe(1)
            ->and($loser['wins'])->toBe(0)
            ->and($loser['rank'])->toBe(2);
    });

    it('breaks a win tie on set difference', function () {
        $division = standingsDivision();
        [$a, $b, $c, $d] = standingsEntrants($division, 4);
        $group = groupStageWithFour($division);

        $ab = BracketSlot::factory()->inGroup($group)->round(1, 0)->create(['entrant_a_id' => $a->id, 'entrant_b_id' => $b->id]);
        $cd = BracketSlot::factory()->inGroup($group)->round(1, 1)->create(['entrant_a_id' => $c->id, 'entrant_b_id' => $d->id]);

        decideTie($ab, $a->id, [[6, 0], [6, 0]]);
        decideTie($cd, $c->id, [[6, 4], [4, 6], [6, 3]]);

        $rows = app(BracketService::class)->standings($group);

        expect($rows[0]['entrant_id'])->toBe($a->id)
            ->and($rows[1]['entrant_id'])->toBe($c->id);
    });

    it('breaks a win tie on game difference', function () {
        $division = standingsDivision();
        [$a, $b, $c, $d] = standingsEntrants($division, 4);
        $group = groupStageWithFour($division);

        $ab = BracketSlot::factory()->inGroup($group)->round(1, 0)->create(['entrant_a_id' => $a->id, 'entrant_b_id' => $b->id]);
        $cd = BracketSlot::factory()->inGroup($group)->round(1, 1)->create(['entrant_a_id' => $c->id, 'entrant_b_id' => $d->id]);

        // Both winners take two sets to none, so the set difference is level;
        // only the game margin separates them.
        decideTie($ab, $a->id, [[6, 4], [6, 4]]);
        decideTie($cd, $c->id, [[6, 0], [6, 0]]);

        $rows = app(BracketService::class)->standings($group);

        expect($rows[0]['entrant_id'])->toBe($c->id)
            ->and($rows[1]['entrant_id'])->toBe($a->id)
            ->and($rows->firstWhere('entrant_id', $a->id)['set_diff'])->toBe(2)
            ->and($rows->firstWhere('entrant_id', $c->id)['set_diff'])->toBe(2)
            ->and($rows->firstWhere('entrant_id', $a->id)['game_diff'])->toBe(4)
            ->and($rows->firstWhere('entrant_id', $c->id)['game_diff'])->toBe(12);
    });

    it('breaks a full tie on head-to-head', function () {
        $division = standingsDivision();
        [$a, $b] = standingsEntrants($division, 2);
        $group = groupStageWithFour($division);

        $slot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create([
            'entrant_a_id' => $a->id,
            'entrant_b_id' => $b->id,
        ]);

        // Even sets and games, so only the head-to-head winner separates them.
        decideTie($slot, $b->id, [[6, 4], [4, 6]]);

        $rows = app(BracketService::class)->standings($group);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['entrant_id'])->toBe($b->id);
    });

    it('leaves undecided ties out of the win column', function () {
        $division = standingsDivision();
        [$a, $b] = standingsEntrants($division, 2);
        $group = groupStageWithFour($division);

        BracketSlot::factory()->inGroup($group)->round(1, 0)->create([
            'entrant_a_id' => $a->id,
            'entrant_b_id' => $b->id,
        ]);

        $rows = app(BracketService::class)->standings($group);

        expect($rows)->toHaveCount(2)
            ->and($rows->sum('wins'))->toBe(0)
            ->and($rows->sum('played'))->toBe(0)
            ->and($rows->pluck('rank')->all())->toBe([1, 2]);
    });
});
