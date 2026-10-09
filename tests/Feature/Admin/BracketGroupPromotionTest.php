<?php

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Models\Bracket;
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

function promotionDivision(int $drawSize = 8): Division
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
function promotionEntrants(Division $division, int $count): array
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
 * Generate a group stage, pick every winner, and promote, leaving the knockout
 * in its initial draft state.
 *
 * @return array{0: BracketService, 1: Bracket, 2: Bracket, 3: array<int, int>}
 */
function promoteDivision(Division $division): array
{
    $service = app(BracketService::class);
    $groupStage = $service->generateGroups($division, 2, [2, 2]);

    $order = [];

    foreach ($groupStage->groups as $group) {
        $picks = $group->entrantIds()->take(2)->all();
        $service->setGroupWinners($group, $picks);
        $order = array_merge($order, $picks);
    }

    $knockout = $service->promoteToKnockout($division, $order, true);

    return [$service, $groupStage, $knockout, $order];
}

describe('Group winners', function () {
    it('records the picked winners on the group', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);
        $group = $bracket->groups->firstWhere('position', 0);
        $picks = $group->entrantIds()->take(2)->all();

        app(BracketService::class)->setGroupWinners($group, $picks);

        expect($group->fresh()->winnerIds())->toBe($picks);
    });

    it('rejects a pick count that does not match the advance count', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);
        $group = $bracket->groups->firstWhere('position', 0);

        expect(fn () => app(BracketService::class)->setGroupWinners($group, $group->entrantIds()->take(1)->all()))
            ->toThrow(InvalidArgumentException::class, 'exactly 2');
    });

    it('rejects a winner who is not a group member', function () {
        $division = promotionDivision();
        $entrants = promotionEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);
        $group = $bracket->groups->firstWhere('position', 0);

        $outsider = collect($entrants)
            ->first(fn (TournamentRegistration $e) => ! $group->entrantIds()->contains($e->id));

        expect(fn () => app(BracketService::class)->setGroupWinners($group, [$outsider->id, $group->entrantIds()->first()]))
            ->toThrow(InvalidArgumentException::class, 'member of the group');
    });

    it('saves winners through the admin endpoint', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);
        $group = $bracket->groups->firstWhere('position', 0);
        $picks = $group->entrantIds()->take(2)->all();

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.groups.winners.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'group' => $group->id,
            ]), ['winner_ids' => $picks])
            ->assertRedirect();

        expect($group->fresh()->winnerIds())->toBe($picks);
    });

    it('validates the winner ids when saving', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);
        $group = $bracket->groups->firstWhere('position', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.groups.winners.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'group' => $group->id,
            ]), [])
            ->assertSessionHasErrors('winner_ids');
    });
});

describe('Group promotion', function () {
    it('seeds a knockout bracket from the picked winners', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);

        expect($knockout->type)->toBe(BracketType::SingleElimination)
            ->and($knockout->size)->toBe(4)
            ->and($knockout->slots()->count())->toBe(3)
            ->and(Bracket::where('division_id', $division->id)->count())->toBe(2);

        $round1 = $knockout->slots()->where('round', 1)->orderBy('position')->get();
        $entrantsIn = $round1->flatMap(fn ($slot) => [$slot->entrant_a_id, $slot->entrant_b_id])->filter();

        expect($entrantsIn->sort()->values()->all())->toBe(collect($order)->sort()->values()->all());
    });

    it('marks the group stage completed on promotion and reopens it when the knockout is deleted', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);

        expect($groupStage->fresh()->status)->toBe(BracketStatus::Completed);

        $service->delete($knockout);

        expect($groupStage->fresh()->status)->toBe(BracketStatus::Active);
    });

    it('refuses to re-seed a knockout promoted from the group stage', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);

        expect(fn () => $service->seed($knockout, array_reverse($order)))
            ->toThrow(InvalidArgumentException::class, 'group stage');
    });

    it('splits group-mates into opposite halves when seeding', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);
        $groupA = $groupStage->groups->firstWhere('position', 0);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);
        $aPicks = $groupA->fresh()->winnerIds();

        $round1 = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        $halves = $round1->map(fn ($slot) => in_array($slot->entrant_a_id, $aPicks, true) || in_array($slot->entrant_b_id, $aPicks, true));

        expect($halves->filter()->count())->toBe(2);
    });

    it('honours the provided seed order when group-mates are not kept apart', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, false);

        $round1 = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        // Seeds 1 and 2 follow the caller's order exactly.
        expect($round1->firstWhere('position', 0)->entrant_a_id)->toBe($order[0])
            ->and($round1->firstWhere('position', 1)->entrant_a_id)->toBe($order[1]);
    });

    it('reports a clash when the draw cannot keep every group-mate apart', function () {
        $division = promotionDivision();
        promotionEntrants($division, 6);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 3, [2, 2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);

        expect($service->firstRoundGroupMateClashes($knockout, $division))->toBeGreaterThan(0);
    });

    it('allows a non-power-of-two field and reports byes', function () {
        $division = promotionDivision();
        promotionEntrants($division, 6);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 3, [1, 1, 1]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(1)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $knockout = $service->promoteToKnockout($division, $order, true);

        expect($knockout->size)->toBe(4)
            ->and($knockout->slots()->where('round', 1)->where('bye', true)->count())->toBe(1);
    });

    it('rejects promotion until every group has picked its winners', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        app(BracketService::class)->generateGroups($division, 2, [2, 2]);

        expect(fn () => app(BracketService::class)->promoteToKnockout($division, [], true))
            ->toThrow(InvalidArgumentException::class, 'Pick the advancing winners');
    });

    it('rejects winners that do not match the group picks', function () {
        $division = promotionDivision();
        $entrants = promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $order[0] = collect($entrants)
            ->first(fn (TournamentRegistration $e) => ! in_array($e->id, $order, true))
            ->id;

        expect(fn () => $service->promoteToKnockout($division, $order, true))
            ->toThrow(InvalidArgumentException::class, 'must match the winners picked');
    });

    it('promotes winners through the admin endpoint', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.promote', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['winner_ids' => $order, 'seed_pairs_apart' => true])
            ->assertRedirect();

        expect(Bracket::where('division_id', $division->id)
            ->where('type', BracketType::SingleElimination)
            ->exists())->toBeTrue();
    });
});

describe('Stage lock', function () {
    it('stays editable while the promoted knockout is still a draft', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        [$service, $groupStage, $knockout] = promoteDivision($division);

        expect($knockout->status)->toBe(BracketStatus::Draft);

        $group = $groupStage->groups->firstWhere('position', 0);
        $picks = $group->entrantIds()->take(2)->all();

        $service->setGroupWinners($group, $picks);

        expect($group->fresh()->winnerIds())->toBe($picks);

        $service->generateGroups($division, 2, [2, 2]);

        expect($division->groupStage()->first()->groups()->count())->toBe(2);
    });

    it('freezes group winner picks and regeneration once the knockout starts', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        [$service, $groupStage, $knockout] = promoteDivision($division);

        $knockout->update(['status' => BracketStatus::Active]);

        $group = $groupStage->groups()->firstWhere('position', 0);
        $picks = $group->entrantIds()->take(2)->all();

        expect(fn () => $service->setGroupWinners($group, $picks))
            ->toThrow(InvalidArgumentException::class, 'knockout has already started');

        expect(fn () => $service->generateGroups($division, 2, [2, 2]))
            ->toThrow(InvalidArgumentException::class, 'knockout has already started');
    });

    it('refuses to re-promote once the knockout starts', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        [$service, , $knockout, $order] = promoteDivision($division);

        $knockout->update(['status' => BracketStatus::Active]);

        expect(fn () => $service->promoteToKnockout($division, $order, true))
            ->toThrow(InvalidArgumentException::class, 'knockout has already started');
    });

    it('rejects deleting the group stage through the endpoint once the knockout starts', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        [, , $knockout] = promoteDivision($division);

        $knockout->update(['status' => BracketStatus::Active]);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.destroy', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'type' => 'group_stage',
            ]))
            ->assertSessionHasErrors('bracket');

        expect($division->groupStage()->exists())->toBeTrue();
    });

    it('rejects promotion when a picked winner is no longer a paid entrant', function () {
        $division = promotionDivision();
        promotionEntrants($division, 8);

        $service = app(BracketService::class);
        $groupStage = $service->generateGroups($division, 2, [2, 2]);

        $order = [];

        foreach ($groupStage->groups as $group) {
            $picks = $group->entrantIds()->take(2)->all();
            $service->setGroupWinners($group, $picks);
            $order = array_merge($order, $picks);
        }

        TournamentRegistration::findOrFail($order[0])->update(['payment_status' => 'refunded']);

        expect(fn () => $service->promoteToKnockout($division, $order, true))
            ->toThrow(InvalidArgumentException::class, 'still be a paid entrant');
    });
});
