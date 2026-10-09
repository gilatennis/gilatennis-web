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
function knockoutAdvanceSetup(int $players = 4): array
{
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'singles',
        'draw_size' => $players,
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

function openKnockout(User $admin, Tournament $tournament, Division $division)
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

it('advances a first-round winner and undoes it', function () {
    [$admin, $tournament, $division] = knockoutAdvanceSetup();

    $page = openKnockout($admin, $tournament, $division);

    $page->click('@generate-bracket')
        ->wait(1)
        ->click('@generate-bracket-confirm')
        ->wait(3);

    $bracket = Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();

    $slot = $bracket->slots()->where('round', 1)->orderBy('position')->first();

    $page->click('@advance-slot-'.$slot->id.'-side-1')
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(3);

    expect($slot->fresh()->winner_entrant_id)->toBe($slot->entrant_a_id);

    // The Undo button must be usable for a normal completed tie.
    $page->assertButtonEnabled('@undo-slot-'.$slot->id)
        ->click('@undo-slot-'.$slot->id)
        ->wait(1)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')
        ->wait(3);

    expect($slot->fresh()->winner_entrant_id)->toBeNull();
});
