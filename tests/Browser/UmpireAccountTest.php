<?php

use App\Enums\MatchStatus;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;

function openUmpireConsole(User $umpire)
{
    return visit('/umpire/login')
        ->fill('email', $umpire->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->wait(1)
        ->assertPathIs('/umpire');
}

function umpireConsoleDivision(): array
{
    $tournament = Tournament::factory()->create([
        'status' => 'open',
        'name' => 'Umpire Open Meet',
    ]);

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Mens Singles',
    ]);

    return [$tournament, $division];
}

it('lets an umpire log in, pick a tournament and score a match', function () {
    $umpire = User::factory()->withRole(User::ROLE_UMPIRE, 'Umpire')->create();

    [$tournament, $division] = umpireConsoleDivision();

    $match = TennisMatch::factory()->fast4()->running()->create([
        'division_id' => $division->id,
        'side_a_name' => 'Console A',
        'side_b_name' => 'Console B',
    ]);

    $page = openUmpireConsole($umpire);

    $page->assertSee('Umpire Open Meet')
        ->click("@umpire-tournament-{$tournament->id}")
        ->wait(2)
        ->assertSeeIn('@on-court', 'Console A')
        ->click("@umpire-oncourt-{$match->id}")
        ->wait(2);

    $page->click('@server-pick-a')
        ->click('@start-match')
        ->wait(2)
        ->click('@point-a')
        ->wait(2);

    $fresh = $match->fresh();

    expect($fresh->status)->toBe(MatchStatus::Running)
        ->and($fresh->events()->count())->toBe(2);

    $page->assertNoJavaScriptErrors();
});

it('shows a match on the umpire screen once it is sent to court', function () {
    $umpire = User::factory()->withRole(User::ROLE_UMPIRE, 'Umpire')->create();

    [$tournament, $division] = umpireConsoleDivision();

    $match = TennisMatch::factory()->create([
        'division_id' => $division->id,
        'side_a_name' => 'Called A',
        'side_b_name' => 'Called B',
    ]);

    $page = openUmpireConsole($umpire)
        ->navigate(route('umpire.tournaments.show', $tournament))
        ->wait(2)
        ->assertSeeIn('@on-court', 'No matches on court right now.');

    // The referee sends the match to court from the admin side.
    $match->update(['status' => MatchStatus::Running, 'started_at' => now()]);

    $page->wait(5)->assertSeeIn('@on-court', 'Called A');

    $page->assertNoJavaScriptErrors();
});

it('does not let the umpire open a match that is not on court yet', function () {
    $umpire = User::factory()->withRole(User::ROLE_UMPIRE, 'Umpire')->create();

    [$tournament, $division] = umpireConsoleDivision();

    $match = TennisMatch::factory()->create([
        'division_id' => $division->id,
        'side_a_name' => 'Soon A',
        'side_b_name' => 'Soon B',
    ]);

    $page = openUmpireConsole($umpire)
        ->navigate(route('umpire.tournaments.show', $tournament))
        ->wait(2);

    $page->assertSeeIn("@umpire-division-{$division->id}", 'Upcoming');

    $page->assertScript(<<<JS
        function() {
            const row = document.querySelector('[data-testid="umpire-match-{$match->id}"]');

            return row !== null && row.closest('a') === null;
        }
        JS);

    $page->click("@umpire-match-{$match->id}")->wait(1);

    $page->assertMissing('@match-heading');
    $page->assertPresent('@on-court');

    $page->assertNoJavaScriptErrors();
});
