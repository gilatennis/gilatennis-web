<?php

use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;

function groupStageBracket(): Bracket
{
    $tournament = Tournament::factory()->create();
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'draw_size' => 8,
    ]);

    return Bracket::factory()->forDivision($division)->create([
        'type' => Bracket::TYPE_GROUP_STAGE,
    ]);
}

function groupStageEntrants(Bracket $bracket, int $count): array
{
    $division = $bracket->division;

    return collect(range(1, $count))
        ->map(fn ($i) => TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]))
        ->all();
}

describe('Bracket group model', function () {
    it('defaults a slot to leg one outside of any group', function () {
        $slot = BracketSlot::factory()->create();

        expect($slot->leg)->toBe(1)
            ->and($slot->bracket_group_id)->toBeNull()
            ->and($slot->load('group')->group)->toBeNull();
    });

    it('links a group to its bracket and slots', function () {
        $bracket = groupStageBracket();

        $group = BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create([
            'advance_count' => 2,
            'double_round_robin' => true,
        ]);

        BracketSlot::factory()->inGroup($group)->round(1, 0)->create();
        BracketSlot::factory()->inGroup($group)->round(1, 1)->create();

        $loaded = BracketGroup::with('slots.group', 'slots.bracket')->findOrFail($group->id);

        expect($bracket->groups)->toHaveCount(1)
            ->and($bracket->isGroupStage())->toBeTrue()
            ->and($group->position)->toBe(0)
            ->and($group->advance_count)->toBe(2)
            ->and($group->double_round_robin)->toBeTrue()
            ->and($loaded->slots)->toHaveCount(2)
            ->and($loaded->slots->first()->group->id)->toBe($group->id)
            ->and($loaded->slots->first()->bracket->id)->toBe($bracket->id);
    });

    it('orders groups by position regardless of insertion order', function () {
        $bracket = groupStageBracket();

        BracketGroup::factory()->forBracket($bracket)->named('Group C', 2)->create();
        BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();
        BracketGroup::factory()->forBracket($bracket)->named('Group B', 1)->create();

        expect($bracket->groups->pluck('name')->all())->toBe(['Group A', 'Group B', 'Group C']);
    });

    it('allows a division to hold both a group stage and a knockout bracket', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'draw_size' => 8,
        ]);

        Bracket::factory()->forDivision($division)->create([
            'type' => Bracket::TYPE_GROUP_STAGE,
        ]);
        Bracket::factory()->forDivision($division)->create([
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
        ]);

        expect($division->fresh()->bracket)->not->toBeNull()
            ->and(Bracket::where('division_id', $division->id)->count())->toBe(2);
    });

    it('returns the distinct entrants placed in a group', function () {
        $bracket = groupStageBracket();
        $group = BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();
        [$a, $b, $c] = groupStageEntrants($bracket, 3);

        BracketSlot::factory()->inGroup($group)->round(1, 0)->create([
            'entrant_a_id' => $a->id,
            'entrant_b_id' => $b->id,
        ]);
        BracketSlot::factory()->inGroup($group)->round(1, 1)->create([
            'entrant_a_id' => $b->id,
            'entrant_b_id' => $c->id,
        ]);

        expect($group->fresh()->load('slots')->entrantIds()->all())
            ->toBe([$a->id, $b->id, $c->id]);
    });
});
