<?php

use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
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

        BracketGroupEntrant::factory()->inGroup($group)->forEntrant($a, 0)->create();
        BracketGroupEntrant::factory()->inGroup($group)->forEntrant($b, 1)->create();
        BracketGroupEntrant::factory()->inGroup($group)->forEntrant($c, 2)->create();

        expect($group->fresh()->entrantIds()->all())
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

describe('Group entrant management', function () {
    it('adds entrants to an empty group and rebuilds its fixtures', function () {
        $division = groupTestDivision(8, 'singles');
        $entrants = groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups(
            $division, 2, [1, 1], false, BracketService::SEEDING_MANUAL,
        );
        $group = $bracket->groups->firstWhere('position', 0);

        expect($group->slots)->toHaveCount(0);

        $service = app(BracketService::class);
        $service->addEntrant($group, $entrants[0]);
        $service->addEntrant($group, $entrants[1]);
        $service->addEntrant($group, $entrants[2]);

        expect($group->fresh()->entrantIds()->all())
            ->toBe([$entrants[0]->id, $entrants[1]->id, $entrants[2]->id])
            ->and($group->slots()->count())->toBe(3);
    });

    it('rejects adding an entrant already placed in the group stage', function () {
        $division = groupTestDivision(8, 'singles');
        $entrants = groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $group = $bracket->groups->firstWhere('position', 0);

        expect(fn () => app(BracketService::class)->addEntrant($group, $entrants[0]))
            ->toThrow(InvalidArgumentException::class, 'already placed');
    });

    it('moves an entrant between groups and rebuilds both', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);

        $mover = TournamentRegistration::find($groupA->entrantIds()->first());

        app(BracketService::class)->moveEntrant($groupA, $groupB, $mover);

        expect($groupA->fresh()->entrantIds())->toHaveCount(3)
            ->and($groupA->slots()->count())->toBe(3)
            ->and($groupB->fresh()->entrantIds())->toHaveCount(5)
            ->and($groupB->slots()->count())->toBe(10);
    });

    it('blocks moves once a group has started', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);

        $slot = $groupA->slots->first();
        $slot->update(['winner_entrant_id' => $slot->entrant_a_id]);

        $mover = TournamentRegistration::find($groupA->entrantIds()->first());

        expect(fn () => app(BracketService::class)->moveEntrant($groupA, $groupB, $mover))
            ->toThrow(InvalidArgumentException::class, 'already started');
    });

    it('toggles a single group to a double round robin and rebuilds it', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);

        expect($groupA->slots)->toHaveCount(6);

        app(BracketService::class)->setGroupDoubleRoundRobin($groupA, true);

        expect($groupA->fresh()->double_round_robin)->toBeTrue()
            ->and($groupA->slots()->count())->toBe(12)
            ->and($groupA->slots()->where('leg', 2)->count())->toBe(6)
            ->and($groupB->slots()->count())->toBe(6);
    });

    it('blocks the round-robin toggle once a group has started', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);

        $slot = $groupA->slots->first();
        $slot->update(['winner_entrant_id' => $slot->entrant_a_id]);

        expect(fn () => app(BracketService::class)->setGroupDoubleRoundRobin($groupA, true))
            ->toThrow(InvalidArgumentException::class, 'already started');
    });

    it('adds an entrant through the admin endpoint', function () {
        $division = groupTestDivision(8, 'singles');
        $entrants = groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups(
            $division, 2, [1, 1], false, BracketService::SEEDING_MANUAL,
        );
        $group = $bracket->groups->firstWhere('position', 0);

        $route = route('admin.tournaments.divisions.bracket.groups.entrants.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'group' => $group->id,
        ]);

        $this->actingAs($this->admin)->post($route, ['entrant_id' => $entrants[0]->id])->assertRedirect();
        $this->actingAs($this->admin)->post($route, ['entrant_id' => $entrants[1]->id])->assertRedirect();

        expect($group->fresh()->entrantIds())->toHaveCount(2)
            ->and($group->slots()->count())->toBe(1);
    });

    it('validates the entrant id when adding', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups(
            $division, 2, [1, 1], false, BracketService::SEEDING_MANUAL,
        );
        $group = $bracket->groups->firstWhere('position', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.groups.entrants.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'group' => $group->id,
            ]), [])
            ->assertSessionHasErrors('entrant_id');
    });

    it('moves an entrant through the admin endpoint and requires a target group', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);
        $groupB = $bracket->groups->firstWhere('position', 1);
        $entrant = TournamentRegistration::find($groupA->entrantIds()->first());

        $route = route('admin.tournaments.divisions.bracket.groups.entrants.move', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'group' => $groupA->id,
        ]);

        $this->actingAs($this->admin)
            ->post($route, ['entrant_id' => $entrant->id])
            ->assertSessionHasErrors('to_group_id');

        $this->actingAs($this->admin)
            ->post($route, ['entrant_id' => $entrant->id, 'to_group_id' => $groupB->id])
            ->assertRedirect();

        expect($groupA->fresh()->entrantIds())->not->toContain($entrant->id)
            ->and($groupB->fresh()->entrantIds())->toContain($entrant->id);
    });

    it('updates a group setting through the admin endpoint', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $groupA = $bracket->groups->firstWhere('position', 0);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.groups.settings.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'group' => $groupA->id,
            ]), ['double_round_robin' => true])
            ->assertRedirect();

        expect($groupA->fresh()->double_round_robin)->toBeTrue()
            ->and($groupA->slots()->count())->toBe(12);
    });

    it('returns 404 for a group outside the division', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);
        app(BracketService::class)->generateGroups($division, 2, [1, 1]);

        $other = groupTestDivision(8, 'singles');
        groupTestEntrants($other, 8);
        $foreignGroup = app(BracketService::class)->generateGroups($other, 2, [1, 1])
            ->groups->firstWhere('position', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.groups.entrants.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'group' => $foreignGroup->id,
            ]), ['entrant_id' => $division->registrations()->value('id')])
            ->assertNotFound();
    });

    it('decides a group tie through the shared slot endpoint', function () {
        $division = groupTestDivision(8, 'singles');
        groupTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
        $slot = $bracket->groups->firstWhere('position', 0)->slots->first();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $slot->id,
            ]), ['winner_entrant_id' => $slot->entrant_a_id])
            ->assertRedirect();

        expect($slot->fresh()->winner_entrant_id)->toBe($slot->entrant_a_id);
    });
});
