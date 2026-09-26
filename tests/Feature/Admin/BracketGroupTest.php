<?php

use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

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

function groupTestDivision(int $drawSize = 8, string $category = 'singles'): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => $category,
        'draw_size' => $drawSize,
    ]);
}

function groupTestEntrants(Division $division, int $count): array
{
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

describe('Group stage generation', function () {
    it('rejects fewer than two groups', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 4);

        expect(fn () => app(BracketService::class)->generateGroups($division, 1, [1]))
            ->toThrow(InvalidArgumentException::class, 'at least two groups');
    });

    it('rejects a group count that cannot seat two entrants each', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 5);

        expect(fn () => app(BracketService::class)->generateGroups($division, 3, [1, 1, 1]))
            ->toThrow(InvalidArgumentException::class, 'at least two entrants');
    });

    it('rejects an advance count for every group', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        expect(fn () => app(BracketService::class)->generateGroups($division, 2, [1]))
            ->toThrow(InvalidArgumentException::class, 'advancing-winner count');
    });

    it('creates the requested groups with round-robin fixtures', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 2], false, BracketService::SEEDING_AUTO);

        expect($bracket->type)->toBe(Bracket::TYPE_GROUP_STAGE)
            ->and($bracket->isGroupStage())->toBeTrue()
            ->and($bracket->groups)->toHaveCount(2)
            ->and($bracket->groups->pluck('name')->all())->toBe(['Group A', 'Group B'])
            ->and($bracket->groups->pluck('advance_count')->all())->toBe([1, 2]);

        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);

        expect($groupA->slots)->toHaveCount(6)
            ->and($groupB->slots)->toHaveCount(6)
            ->and($groupA->entrantIds())->toHaveCount(4)
            ->and($groupB->entrantIds())->toHaveCount(4);
    });

    it('snake-distributes the seeds so the top seeds are split apart', function () {
        $division = groupTestDivision(8, 'singles');
        $entrants = groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1], false, BracketService::SEEDING_AUTO);

        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);

        expect($groupA->fresh()->load('slots')->entrantIds()->all())
            ->toContain($entrants[0]->id, $entrants[3]->id)
            ->not->toContain($entrants[1]->id);
    });

    it('leaves the groups empty in manual mode', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1], false, BracketService::SEEDING_MANUAL);

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->slots()->count())->toBe(0);
    });

    it('builds a double round robin with return legs when requested', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1], true, BracketService::SEEDING_AUTO);

        $groupA = $bracket->groups->firstWhere('position', 0);

        expect($groupA->double_round_robin)->toBeTrue()
            ->and($groupA->slots)->toHaveCount(12)
            ->and($groupA->slots->where('leg', 2))->toHaveCount(6);
    });

    it('generates a group stage through the admin endpoint', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.groups.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), [
                'group_count' => 2,
                'advance_counts' => [1, 1],
                'double_round_robin' => false,
                'seeding_mode' => 'auto',
            ])
            ->assertRedirect();

        $bracket = Bracket::where('division_id', $division->id)
            ->where('type', Bracket::TYPE_GROUP_STAGE)
            ->firstOrFail();

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->slots()->count())->toBe(12);
    });

    it('keeps the knockout bracket when a group stage is added', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        app(BracketService::class)->generate($division);
        app(BracketService::class)->generateGroups($division, 2, [1, 1]);

        expect(Bracket::where('division_id', $division->id)->count())->toBe(2)
            ->and(Bracket::where('division_id', $division->id)->where('type', Bracket::TYPE_SINGLE_ELIMINATION)->exists())->toBeTrue()
            ->and(Bracket::where('division_id', $division->id)->where('type', Bracket::TYPE_GROUP_STAGE)->exists())->toBeTrue();
    });

    it('regenerates a group stage without duplicating groups', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->slots()->count())->toBe(12);
    });
});
