<?php

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Vite;

beforeEach(fn () => Vite::useHotFile(sys_get_temp_dir().'/vite-hot-file-disabled'));

function rubberBracketSetup(): array
{
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'team',
        'draw_size' => 2,
    ]);

    foreach (['Alpha', 'Bravo'] as $name) {
        TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => $name,
        ]);
    }

    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.store', [
        'tournament' => $tournament->id,
        'division' => $division->id,
    ]), ['seeding_mode' => 'auto']);

    return [$admin, $tournament, $division];
}

function rubberBracketSlot(Division $division): int
{
    return Bracket::where('division_id', $division->id)
        ->firstOrFail()
        ->slots()
        ->where('round', 1)
        ->firstOrFail()
        ->id;
}

function startTeamRubber(Division $division, User $admin): TennisMatch
{
    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.slots.start', [
        'tournament' => $division->tournament_id,
        'division' => $division->id,
        'slot' => rubberBracketSlot($division),
    ]), [
        'side_a_name' => 'Ana / Bea',
        'side_b_name' => 'Cara / Dana',
        'sets_to_win' => 1,
        'games_to_win' => 6,
        'extend_at_tie' => true,
        'tiebreak_to' => 7,
    ]);

    return TennisMatch::firstOrFail();
}

function openRubberBracket(User $admin, Tournament $tournament, Division $division)
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
            'tab' => 'matches',
        ]))
        ->wait(2);
}

it('summarises an empty tie and adds the first rubber from the dialog', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    $page = openRubberBracket($admin, $tournament, $division);

    $page->assertSeeIn('@rubber-summary', 'No rubbers set up')
        ->click('@rubbers')
        ->wait(1)
        ->assertSee('Add Rubber');

    $page->click('@rubber-type-singles')
        ->click('@rubber-submit')
        ->wait(3);

    $match = TennisMatch::firstOrFail();

    $page->assertSeeIn('@rubber-list', $match->side_a_name)
        ->assertSeeIn('@rubber-list', $match->side_b_name)
        ->assertSeeIn('@rubber-summary', '1 rubber')
        ->assertSeeIn('@rubber-summary', '0 completed');

    expect($match->bracket_slot_id)->not->toBeNull();
});

it('opens an existing tie on the rubber list and edits from a row', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    startTeamRubber($division, $admin);

    $page = openRubberBracket($admin, $tournament, $division);

    $page->assertSeeIn('@rubber-summary', '1 rubber')
        ->click('@rubbers')
        ->wait(1)
        ->assertSeeIn('@rubber-list', 'Ana / Bea')
        ->assertSeeIn('@rubber-list', 'Cara / Dana')
        ->assertSeeIn('@rubber-list', 'Pending');

    $page->click('@rubber-row')
        ->wait(1)
        ->assertSee('Edit Rubber')
        ->click('@rubber-type-singles')
        ->click('@rubber-submit')
        ->wait(3);

    $page->assertSeeIn('@rubber-list', 'Ana');

    expect(TennisMatch::firstOrFail()->side_a_name)->toBe('Ana');
});

it('counts completed rubbers in the card summary', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    $match = startTeamRubber($division, $admin);
    $match->update(['status' => MatchStatus::Completed]);

    $page = openRubberBracket($admin, $tournament, $division);

    $page->assertSeeIn('@rubber-summary', '1 rubber')
        ->assertSeeIn('@rubber-summary', '1 completed');
});
