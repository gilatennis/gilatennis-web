<?php

use App\Enums\BracketType;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

/**
 * @return array{0: User, 1: Tournament, 2: Division}
 */
function manualAssignmentSetup(int $players = 4, ?int $drawSize = null): array
{
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'singles',
        'draw_size' => $drawSize ?? $players,
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

function openManualBracket(User $admin, Tournament $tournament, Division $division)
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

it('assigns a knockout entrant to a slot manually', function () {
    [$admin, $tournament, $division] = manualAssignmentSetup();

    $page = openManualBracket($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@manual-assignment-checkbox')
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    expect($bracket->manual_assignment)->toBeTrue();

    $slots = $bracket->slots()->where('round', 1)->orderBy('position')->get();
    $target = $slots[0];
    $other = $slots[1];

    // The manual-assignment toggle and per-slot selects are exposed while drafting.
    $page->assertPresent('@manual-assignment-toggle')
        ->assertPresent('@assign-slot-'.$target->id.'-side-1');

    $moved = $other->entrant_a_id;

    $page->select('@assign-slot-'.$target->id.'-side-1', (string) $moved)
        ->wait(2);

    expect($target->fresh()->entrant_a_id)->toBe($moved)
        ->and($other->fresh()->entrant_a_id)->not->toBe($moved);
});

it('keeps the bracket read-only when manual assignment is off', function () {
    [$admin, $tournament, $division] = manualAssignmentSetup();

    $page = openManualBracket($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

    $page->assertPresent('@manual-assignment-toggle')
        ->assertMissing('@assign-slot-'.$slot->id.'-side-1');

    // Flipping the toolbar switch enables the per-slot selects.
    $page->click('@manual-assignment-toggle')
        ->wait(2)
        ->assertPresent('@assign-slot-'.$slot->id.'-side-1');

    expect($bracket->fresh()->manual_assignment)->toBeTrue();
});

it('completes the draw, resolving byes, then reopens it', function () {
    [$admin, $tournament, $division] = manualAssignmentSetup(6, 8);

    $page = openManualBracket($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@manual-assignment-checkbox')
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

    $page->assertPresent('@assign-slot-'.$slot->id.'-side-1')
        ->assertPresent('@complete-draw')
        ->click('@complete-draw')
        ->wait(3);

    expect($bracket->fresh()->draw_locked)->toBeTrue();

    // Once locked, the selects give way to the reopen affordance.
    $page->assertPresent('@reopen-draw')
        ->assertMissing('@assign-slot-'.$slot->id.'-side-1');

    $page->click('@reopen-draw')
        ->wait(3);

    expect($bracket->fresh()->draw_locked)->toBeFalse();

    $page->assertPresent('@assign-slot-'.$slot->id.'-side-1')
        ->assertPresent('@complete-draw');
});

it('undoes a bye advancement while the draw is editable', function () {
    [$admin, $tournament, $division] = manualAssignmentSetup(6, 8);

    $page = openManualBracket($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@manual-assignment-checkbox')
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $page->click('@complete-draw')
        ->wait(1)
        ->assertPresent('@reopen-draw')
        ->wait(1);

    $bracket->refresh();

    $byeSlot = $bracket->slots()->where('round', 1)->where('bye', true)->firstOrFail();

    expect($byeSlot->winner_entrant_id)->not->toBeNull();

    // Simulate a bracket that predates draw_locked: resolved byes while open.
    $bracket->update(['draw_locked' => false]);

    $page->navigate(route('admin.tournaments.divisions.show', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'tab' => 'brackets',
    ]))->wait(2);

    $page->assertButtonEnabled('@undo-slot-'.$byeSlot->id)
        ->click('@undo-slot-'.$byeSlot->id)
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(3);

    expect($byeSlot->fresh()->winner_entrant_id)->toBeNull()
        ->and($byeSlot->fresh()->bye)->toBeFalse();
});

it('shows an error toast when completing an unplaced draw', function () {
    [$admin, $tournament, $division] = manualAssignmentSetup(4);

    $page = openManualBracket($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@manual-assignment-checkbox')
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();
    $slot->update(['entrant_a_id' => null]);

    $page->navigate(route('admin.tournaments.divisions.show', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'tab' => 'brackets',
    ]))->wait(2);

    $page->assertPresent('@complete-draw')
        ->click('@complete-draw')
        ->wait(1)
        ->assertSee('before completing the draw');

    expect($bracket->fresh()->draw_locked)->toBeFalse();
});
