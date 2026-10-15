<?php

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;

/**
 * @return array{0: User, 1: Tournament, 2: Division}
 */
function groupStageSetup(int $players = 8, int $drawSize = 8): array
{
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'singles',
        'draw_size' => $drawSize,
    ]);

    foreach (range(1, $players) as $i) {
        TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => "Player {$i}",
        ]);
    }

    return [$admin, $tournament, $division];
}

function openGroupStage(User $admin, Tournament $tournament, Division $division)
{
    return visit('/admin/login')
        ->fill('email', $admin->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->wait(1)
        ->assertPathIs('/admin/dashboard')
        ->navigate(route('admin.tournaments.divisions.show', [
            'tournament' => $tournament->id,
            'division' => $division->id,
            'tab' => 'brackets',
        ]))
        ->wait(2);
}

it('generates groups from the UI and renders the standings', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    $page = openGroupStage($admin, $tournament, $division);

    $page->assertSee('Round-Robin Groups')
        ->click('Generate Groups')
        ->wait(1)
        ->click('@generate-groups')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    expect($bracket->groups)->toHaveCount(2);

    $page->assertSee('Group A')
        ->assertSee('Group B');

    foreach ($bracket->groups[0]->entrantIds() as $entrantId) {
        $page->assertPresent('@group-entrant-'.$entrantId);
    }
});

it('promotes the picked group winners into the knockout bracket', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    $service = app(BracketService::class);
    $groupStage = $service->generateGroups($division, 2, [1, 1]);

    $order = [];

    foreach ($groupStage->groups as $group) {
        $picks = $group->entrantIds()->take(1)->all();
        $service->setGroupWinners($group, $picks);
        $order = array_merge($order, $picks);
    }

    $page = openGroupStage($admin, $tournament, $division);

    $page->click('@open-promote')
        ->wait(1)
        ->assertSee('Promote Winners')
        ->click('@promote-winners')
        ->wait(3);

    expect(Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->exists())->toBeTrue();
});

it('locks entrant assignment once a group has started', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
    $group = $bracket->groups->firstWhere('position', 0);
    $slot = $group->slots->first();
    $slot->update(['winner_entrant_id' => $slot->entrant_a_id]);

    $page = openGroupStage($admin, $tournament, $division);

    $page->assertDontSeeIn('@group-card-'.$group->id, 'Add entrant');
});

it('sets winners on successive group ties and undoes one', function () {
    [$admin, $tournament, $division] = groupStageSetup(players: 4, drawSize: 4);

    $bracket = app(BracketService::class)->generateGroups($division, 2, [1, 1]);
    $slotA = $bracket->groups[0]->slots->first();
    $slotB = $bracket->groups[1]->slots->first();

    $page = openGroupStage($admin, $tournament, $division);

    // Record the first tie's winner.
    $page->click('@advance-slot-'.$slotA->id.'-side-1')
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(2);

    expect($slotA->fresh()->winner_entrant_id)->toBe($slotA->entrant_a_id);

    // Regression: the confirm modal must reopen for the next tie without a
    // page reload, and its Undo button must be usable.
    $page->click('@advance-slot-'.$slotB->id.'-side-1')
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(2);

    expect($slotB->fresh()->winner_entrant_id)->toBe($slotB->entrant_a_id);

    $page->assertButtonEnabled('@undo-slot-'.$slotA->id)
        ->click('@undo-slot-'.$slotA->id)
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(2);

    expect($slotA->fresh()->winner_entrant_id)->toBeNull();
});

it('carries a group stage through to a promoted knockout from the UI', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    $page = openGroupStage($admin, $tournament, $division);

    // Generate two groups through the dialog.
    $page->click('Generate Groups')
        ->wait(1)
        ->click('@generate-groups')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    // No knockout affordance while the group stage is un-promoted.
    $page->assertMissing('@generate-bracket')->assertMissing('@promote-hint');

    // Pick the top entrant from each group's standings table.
    $leaders = [];

    foreach ($bracket->groups as $group) {
        $leader = $group->fresh()->entrantIds()->first();
        $leaders[] = $leader;
        $page->click('@pick-winner-'.$leader)->wait(2);
    }

    // Once every group has its winner the promote hint appears above the groups.
    $page->assertPresent('@promote-hint')
        ->assertPresent('@group-stage-groups')
        ->assertPresent('@toggle-group-stage');

    // Promote — the button enables once every group has its winner.
    $page->click('@open-promote')
        ->wait(1)
        ->assertSee('Promote Winners')
        ->click('@promote-winners')
        ->wait(3);

    $knockout = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    expect($knockout->size)->toBe(2);

    $final = $knockout->finalSlot();

    // Promoting collapses the groups so the bracket takes focus. The knockout
    // is still a draft, so the group stage stays open: it reads as in progress
    // and its controls remain available so the draw can still be corrected.
    $page->assertMissing('@group-stage-groups')
        ->assertSeeIn('@group-stage-status', 'In Progress')
        ->assertPresent('@open-promote')
        ->assertPresent('@regenerate-groups')
        ->assertPresent('@delete-groups')
        ->assertSee('Knockout Stage')
        ->assertSee('Final')
        ->assertPresent('@advance-slot-'.$final->id.'-side-1')
        ->assertPresent('@advance-slot-'.$final->id.'-side-2')
        ->click('@toggle-group-stage')
        ->wait(1)
        ->assertPresent('@group-stage-groups');

    // Once the knockout begins play the group stage reads as completed and its
    // controls are gone.
    $knockout->update(['status' => BracketStatus::Active]);

    $page->navigate(route('admin.tournaments.divisions.show', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'tab' => 'brackets',
    ]))->wait(2);

    $page->assertSeeIn('@group-stage-status', 'Completed')
        ->assertMissing('@open-promote')
        ->assertMissing('@regenerate-groups')
        ->assertMissing('@delete-groups');

    // Expanding the completed groups reveals a read-only standings table: the
    // per-group edit controls are gone too.
    $page->click('@toggle-group-stage')
        ->wait(1)
        ->assertPresent('@group-stage-groups');

    foreach ($leaders as $leader) {
        $page->assertMissing('@pick-winner-'.$leader);
    }
});

it('promotes a non-power-of-two field as a bye through the UI', function () {
    [$admin, $tournament, $division] = groupStageSetup(players: 6, drawSize: 6);

    $page = openGroupStage($admin, $tournament, $division);

    // Three groups of two, one winner advancing from each.
    $page->click('Generate Groups')
        ->wait(1)
        ->click('@groups-inc')
        ->wait(1)
        ->fill('@advance-count-0', '1')
        ->fill('@advance-count-1', '1')
        ->fill('@advance-count-2', '1')
        ->click('@generate-groups')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    expect($bracket->groups)->toHaveCount(3);

    foreach ($bracket->groups as $group) {
        $leader = $group->fresh()->entrantIds()->first();
        $page->click('@pick-winner-'.$leader)->wait(2);
    }

    // The promote dialog flags the single bye before confirming.
    $page->click('@open-promote')
        ->wait(1)
        ->assertSee('Promote Winners')
        ->assertPresent('@bye-warning')
        ->click('@promote-winners')
        ->wait(3);

    $knockout = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $firstRound = $knockout->slots()->where('round', 1)->orderBy('position')->get();
    $byeSlot = $firstRound->firstWhere('bye', true);
    $liveSlot = $firstRound->firstWhere('bye', false);

    expect($knockout->size)->toBe(4)
        ->and($firstRound->where('bye', true))->toHaveCount(1)
        ->and($byeSlot->winner_entrant_id)->not->toBeNull();

    // A "Semifinal" round renders; the live tie offers its advance controls
    // while the resolved bye tie does not.
    $page->assertSee('Semifinal')
        ->assertPresent('@advance-slot-'.$liveSlot->id.'-side-1');

    $page->assertMissing('@advance-slot-'.$byeSlot->id.'-side-1');
});

it('generates a standalone knockout from the UI when there is no group stage', function () {
    [$admin, $tournament, $division] = groupStageSetup(players: 4, drawSize: 4);

    $page = openGroupStage($admin, $tournament, $division);

    // No group stage and no knockout: the standalone draw is offered and the
    // group-stage entry point is still available.
    $page->assertPresent('@generate-bracket')
        ->assertMissing('@promote-hint')
        ->assertMissing('@standalone-hint');

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $semi = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

    // The knockout is now the primary flow, so the group-stage entry is hidden.
    $page->assertPresent('@advance-slot-'.$semi->id.'-side-1')
        ->assertMissing('@generate-bracket')
        ->assertPresent('@standalone-hint');
});

it('unpicks a group winner through the standings check button', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    app(BracketService::class)->generateGroups($division, 2, [1, 1]);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    $leader = $bracket->groups->firstWhere('position', 0)->entrantIds()->first();

    $page = openGroupStage($admin, $tournament, $division);

    // Pick the leader, then click the check again to undo the pick.
    $page->click('@pick-winner-'.$leader)->wait(2);
    expect($bracket->groups->firstWhere('position', 0)->fresh()->winnerIds())->toContain($leader);

    $page->click('@pick-winner-'.$leader)->wait(2);
    expect($bracket->groups->firstWhere('position', 0)->fresh()->winnerIds())->not->toContain($leader);
});

it('removes an entrant to the unassigned rail and adds it back to a group', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    app(BracketService::class)->generateGroups($division, 2, [1, 1]);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    $groupA = $bracket->groups->firstWhere('position', 0);
    $groupB = $bracket->groups->firstWhere('position', 1);
    $mover = $groupA->entrantIds()->first();

    $page = openGroupStage($admin, $tournament, $division);

    // Remove from its group → it appears in the unassigned rail.
    $page->click('@remove-entrant-'.$mover)->wait(2);
    $page->assertPresent('@unassigned-entrant-'.$mover);

    expect($groupA->fresh()->entrantIds())->not->toContain($mover);

    // Add it back into the other group from the rail dropdown.
    $page->select('@assign-unassigned-'.$mover, (string) $groupB->id)->wait(2);

    expect($groupB->fresh()->entrantIds())->toContain($mover);
    $page->assertMissing('@unassigned-entrant-'.$mover);
});

it('stacks the standings above the entrants when the card is narrow', function () {
    [$admin, $tournament, $division] = groupStageSetup();

    app(BracketService::class)->generateGroups($division, 2, [2, 2]);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();

    $groupId = $bracket->groups->first()->id;

    $page = openGroupStage($admin, $tournament, $division);

    $assertStacked = <<<JS
        function() {
            const standings = document.querySelector('[data-testid="group-standings-{$groupId}"]').getBoundingClientRect();
            const entrants = document.querySelector('[data-testid="group-entrants-{$groupId}"]').getBoundingClientRect();

            return standings.bottom <= entrants.top + 1;
        }
        JS;

    $assertSideBySide = <<<JS
        function() {
            const standings = document.querySelector('[data-testid="group-standings-{$groupId}"]').getBoundingClientRect();
            const entrants = document.querySelector('[data-testid="group-entrants-{$groupId}"]').getBoundingClientRect();

            return standings.top < entrants.bottom && standings.bottom > entrants.top;
        }
        JS;

    $page->resize(900, 900)->assertScript($assertStacked);
    $page->resize(1920, 900)->assertScript($assertSideBySide);
    $page->assertNoJavaScriptErrors();
});
