<?php

use App\Enums\BracketStatus;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

function manualTestDivision(int $drawSize = 8): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'draw_size' => $drawSize,
    ]);
}

function manualTestEntrants(Division $division, int $count): array
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
 * Build a promoted knockout from two groups of four, advancing two each, and
 * return [knockout, qualifiedIds].
 *
 * @return array{0: Bracket, 1: array<int, int>}
 */
function manualPromotedKnockout(Division $division, BracketService $service): array
{
    $groupStage = $service->generateGroups($division, 2, [2, 2]);

    $order = [];
    $qualified = [];

    foreach ($groupStage->groups as $group) {
        $picks = $group->entrantIds()->take(2)->all();
        $service->setGroupWinners($group, $picks);
        $order = array_merge($order, $picks);
        $qualified = array_merge($qualified, $picks);
    }

    return [$service->promoteToKnockout($division, $order, true), $qualified];
}

describe('Manual knockout assignment', function () {
    it('persists the setting and exposes it with eligible entrants', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto', 'manual_assignment' => true])->assertRedirect();

        expect(Bracket::where('division_id', $division->id)->firstOrFail()->manual_assignment)->toBeTrue();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'tab' => 'brackets',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bracket.manual_assignment', true)
                ->has('bracket.eligible_entrants', 6));
    });

    it('toggles the setting through the toolbar endpoint while drafting', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $bracket = app(BracketService::class)->generate($division, 'auto', false);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.settings.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['manual_assignment' => true])
            ->assertRedirect();

        expect($bracket->fresh()->manual_assignment)->toBeTrue();
    });

    it('moves an entrant between first-round slots without duplicating', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $from = $round1[0];
        $to = $round1[1];
        $entrant = TournamentRegistration::findOrFail($from->entrant_a_id);

        $service->assignSlotEntrant($to, 'a', $entrant);

        expect($to->fresh()->entrant_a_id)->toBe($entrant->id)
            ->and($from->fresh()->entrant_a_id)->toBeNull()
            ->and($bracket->slots()->where('round', 1)->where('entrant_b_id', $entrant->id)->exists())->toBeFalse();
    });

    it('clears a first-round side when passed null', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

        $service->assignSlotEntrant($slot, 'a', null);

        expect($slot->fresh()->entrant_a_id)->toBeNull();
    });

    it('assigns an entrant through the endpoint', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $entrant = TournamentRegistration::findOrFail($round1[0]->entrant_a_id);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.entrant.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $round1[1]->id,
            ]), ['side' => 'a', 'entrant_id' => $entrant->id])
            ->assertRedirect();

        expect($round1[1]->fresh()->entrant_a_id)->toBe($entrant->id);
    });

    it('rejects assignment when manual assignment is disabled', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', false);
        $slot = $bracket->slots()->where('round', 1)->whereNotNull('entrant_a_id')->first();
        $entrant = TournamentRegistration::findOrFail($slot->entrant_a_id);

        expect(fn () => $service->assignSlotEntrant($slot, 'a', $entrant))
            ->toThrow(InvalidArgumentException::class, 'manual assignment');
    });

    it('rejects assignment once the draw is no longer a draft', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)
            ->whereNotNull('entrant_a_id')
            ->whereNotNull('entrant_b_id')
            ->first();
        $entrant = TournamentRegistration::findOrFail($slot->entrant_a_id);

        $service->completeDraw($bracket);
        $service->startSlot($slot);

        expect($bracket->fresh()->status)->toBe(BracketStatus::Active);

        expect(fn () => $service->assignSlotEntrant($slot, 'a', $entrant))
            ->toThrow(InvalidArgumentException::class, 'draw is final');
    });

    it('rejects assignment into a later round', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $final = $bracket->slots()->where('round', 2)->firstOrFail();

        expect(fn () => $service->assignSlotEntrant($final, 'a', null))
            ->toThrow(InvalidArgumentException::class, 'first-round');
    });

    it('rejects a non-boolean manual assignment flag', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto', 'manual_assignment' => 'not-a-boolean'])
            ->assertSessionHasErrors('manual_assignment');

        expect(Bracket::where('division_id', $division->id)->exists())->toBeFalse();
    });

    it('lists eligible entrants without a query per entrant', function () {
        $queryCount = function (int $entrantCount): int {
            $division = manualTestDivision(16);
            manualTestEntrants($division, $entrantCount);
            $bracket = app(BracketService::class)->generate($division, 'auto', true);

            DB::enableQueryLog();
            DB::flushQueryLog();

            app(BracketService::class)->eligibleEntrants($bracket);

            $queries = count(DB::getQueryLog());

            DB::disableQueryLog();

            return $queries;
        };

        expect($queryCount(12))->toBe($queryCount(4));
    });
});

function roundFilled(Bracket $bracket, int $round): int
{
    return $bracket->slots()->where('round', $round)->get()
        ->sum(fn ($slot) => ($slot->entrant_a_id !== null ? 1 : 0) + ($slot->entrant_b_id !== null ? 1 : 0));
}

describe('Completing a manual draw', function () {
    it('leaves byes unresolved while a seeded manual draw is being edited', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $bracket = app(BracketService::class)->generate($division, 'auto', true);

        expect($bracket->fresh()->draw_locked)->toBeFalse()
            ->and(roundFilled($bracket, 2))->toBe(0)
            ->and($bracket->slots()->where('round', 1)->where('bye', true)->count())->toBe(0);
    });

    it('does not advance a lone partner when an entrant is moved', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $entrant = TournamentRegistration::findOrFail($round1[0]->entrant_a_id);

        $service->assignSlotEntrant($round1[1], 'a', $entrant);

        $origin = $round1[0]->fresh();

        expect($origin->entrant_a_id)->toBeNull()
            ->and($origin->entrant_b_id)->not->toBeNull()
            ->and($origin->winner_entrant_id)->toBeNull()
            ->and($origin->bye)->toBeFalse()
            ->and(roundFilled($bracket, 2))->toBe(0);
    });

    it('resolves byes and locks the draw on completion', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        $service->completeDraw($bracket);

        $fresh = $bracket->fresh();

        expect($fresh->draw_locked)->toBeTrue()
            ->and($fresh->slots()->where('round', 1)->where('bye', true)->count())->toBe(2)
            ->and(roundFilled($bracket, 2))->toBe(2);
    });

    it('refuses to complete while entrants are still unplaced', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

        $service->assignSlotEntrant($slot, 'a', null);

        expect(fn () => $service->completeDraw($bracket))
            ->toThrow(InvalidArgumentException::class, 'Place all 8 entrants');
    });

    it('flashes an error toast and keeps the draw open when completing an unplaced field', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();
        $slot->update(['entrant_a_id' => null]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.complete', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('bracket')
            ->assertSessionHas('inertia.flash_data', fn ($flash) => $flash['toast']['type'] === 'error');

        expect($bracket->fresh()->draw_locked)->toBeFalse();
    });

    it('refuses to complete with an empty first-round match', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

        $service->assignSlotEntrant($slot, 'a', null);
        $service->assignSlotEntrant($slot, 'b', null);

        expect(fn () => $service->completeDraw($bracket))
            ->toThrow(InvalidArgumentException::class, 'at least one entrant');
    });

    it('rejects assignment once the draw is locked', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();
        $entrant = TournamentRegistration::findOrFail($slot->entrant_a_id);

        $service->completeDraw($bracket);

        expect(fn () => $service->assignSlotEntrant($slot, 'a', $entrant))
            ->toThrow(InvalidArgumentException::class, 'draw is complete');
    });

    it('reopens a completed draw and retracts the byes', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        $service->completeDraw($bracket);
        $service->reopenDraw($bracket);

        expect($bracket->fresh()->draw_locked)->toBeFalse()
            ->and(roundFilled($bracket, 2))->toBe(0)
            ->and($bracket->slots()->where('round', 1)->where('bye', true)->count())->toBe(0);
    });

    it('refuses to start a match while editing', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

        expect(fn () => $service->startSlot($slot))
            ->toThrow(InvalidArgumentException::class, 'Complete the draw before starting play');
    });

    it('refuses to record a result while editing', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();
        $entrant = TournamentRegistration::findOrFail($slot->entrant_a_id);

        expect(fn () => $service->setWinner($slot, $entrant))
            ->toThrow(InvalidArgumentException::class, 'Complete the draw before recording results');
    });

    it('maps a slot update while editing to a form error instead of a 500', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $bracket = app(BracketService::class)->generate($division, 'auto', true);
        $slot = $bracket->slots()->where('round', 1)->whereNotNull('entrant_a_id')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $slot->id,
            ]), ['winner_entrant_id' => $slot->entrant_a_id])
            ->assertRedirect()
            ->assertSessionHasErrors('slot');

        expect($slot->fresh()->winner_entrant_id)->toBeNull();
    });

    it('refuses to turn off manual assignment while the draw is open', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);

        expect(fn () => $service->setManualAssignment($bracket, false))
            ->toThrow(InvalidArgumentException::class, 'Complete the draw before turning off');
    });

    it('retracts advanced byes when manual assignment is switched on', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', false);

        expect(roundFilled($bracket, 2))->toBe(2);

        $service->setManualAssignment($bracket, true);

        expect(roundFilled($bracket, 2))->toBe(0)
            ->and($bracket->slots()->where('round', 1)->where('bye', true)->count())->toBe(0);
    });
});

describe('Manual assignment on a group-derived knockout', function () {
    it('rejects assignment until the setting is enabled, then allows it', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        [$knockout] = manualPromotedKnockout($division, $service);

        $round1 = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        expect(fn () => $service->assignSlotEntrant($round1[0], 'a', null))
            ->toThrow(InvalidArgumentException::class, 'manual assignment');

        $service->setManualAssignment($knockout, true);

        $entrant = TournamentRegistration::findOrFail($round1[0]->entrant_a_id);
        $service->assignSlotEntrant($round1[1], 'a', $entrant);

        expect($round1[1]->fresh()->entrant_a_id)->toBe($entrant->id);
    });

    it('rejects a non-qualifier for a group-derived bracket', function () {
        $division = manualTestDivision(8);
        $entrants = manualTestEntrants($division, 8);

        $service = app(BracketService::class);
        [$knockout, $qualified] = manualPromotedKnockout($division, $service);
        $service->setManualAssignment($knockout, true);

        $nonQualifier = collect($entrants)->first(fn ($e) => ! in_array($e->id, $qualified, true));
        $slot = $knockout->slots()->where('round', 1)->firstOrFail();

        expect(fn () => $service->assignSlotEntrant($slot, 'a', $nonQualifier))
            ->toThrow(InvalidArgumentException::class, 'not part of this bracket');
    });
});

describe('Undoing an advancement while editing', function () {
    /**
     * Simulate a bracket that predates draw_locked: byes were resolved while the
     * manual draw was still open, leaving an uneditable bye slot behind.
     */
    function staleEditingBye(BracketService $service, Division $division): array
    {
        $bracket = $service->generate($division, 'auto', true);
        $service->completeDraw($bracket);

        $byeSlot = $bracket->slots()->where('round', 1)->where('bye', true)->firstOrFail();

        $bracket->update(['draw_locked' => false]);

        return [$bracket, $byeSlot];
    }

    it('retracts a resolved bye and its later-round entrant', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        [$bracket, $byeSlot] = staleEditingBye($service, $division);

        expect($byeSlot->winner_entrant_id)->not->toBeNull()
            ->and(roundFilled($bracket, 2))->toBe(2);

        $service->undoWhileEditing($byeSlot->fresh());

        expect($byeSlot->fresh()->winner_entrant_id)->toBeNull()
            ->and($byeSlot->fresh()->bye)->toBeFalse()
            ->and(roundFilled($bracket, 2))->toBe(1);
    });

    it('fills the empty side of a stale bye slot', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        [$bracket, $byeSlot] = staleEditingBye($service, $division);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $donor = $round1->first(fn ($slot) => ! $slot->bye && $slot->entrant_b_id !== null);
        $entrant = TournamentRegistration::findOrFail($donor->entrant_b_id);

        $side = $byeSlot->entrant_a_id !== null ? 'b' : 'a';
        $service->assignSlotEntrant($byeSlot->fresh(), $side, $entrant);

        $fresh = $byeSlot->fresh();

        expect(($side === 'a' ? $fresh->entrant_a_id : $fresh->entrant_b_id))->toBe($entrant->id)
            ->and($fresh->winner_entrant_id)->toBeNull()
            ->and($fresh->bye)->toBeFalse();
    });

    it('undoes a bye through the endpoint while the draw is editable', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        [$bracket, $byeSlot] = staleEditingBye($service, $division);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $byeSlot->id,
            ]))
            ->assertRedirect();

        expect($byeSlot->fresh()->winner_entrant_id)->toBeNull()
            ->and($byeSlot->fresh()->bye)->toBeFalse();
    });

    it('still refuses to undo a bye once the draw is locked', function () {
        $division = manualTestDivision(8);
        manualTestEntrants($division, 6);

        $service = app(BracketService::class);
        $bracket = $service->generate($division, 'auto', true);
        $service->completeDraw($bracket);

        $byeSlot = $bracket->slots()->where('round', 1)->where('bye', true)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $byeSlot->id,
            ]))
            ->assertSessionHasErrors('slot');

        expect($byeSlot->fresh()->winner_entrant_id)->not->toBeNull();
    });
});
