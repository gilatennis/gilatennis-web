<?php

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

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

function startTeamRubber(
    Division $division,
    User $admin,
    string $sideA = 'Ana / Bea',
    string $sideB = 'Cara / Dana',
): TennisMatch {
    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.slots.rubbers.store', [
        'tournament' => $division->tournament_id,
        'division' => $division->id,
        'slot' => rubberBracketSlot($division),
    ]), [
        'side_a_name' => $sideA,
        'side_b_name' => $sideB,
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

    $page->assertScript(<<<'JS'
        function() {
            const list = document.querySelector('[data-testid="rubber-list"]');
            const footer = document.querySelector('[data-testid="rubber-add"]').parentElement;

            return footer.getBoundingClientRect().top - list.getBoundingClientRect().bottom >= 24;
        }
        JS);
});

it('lists every rubber with edit and score buttons and edits from a row', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    startTeamRubber($division, $admin);
    startTeamRubber($division, $admin, 'Eve / Fay', 'Gia / Hana');

    $matchIds = TennisMatch::orderBy('id')->pluck('id')->all();

    $page = openRubberBracket($admin, $tournament, $division);

    $page->assertSeeIn('@rubber-summary', '2 rubbers')
        ->click('@rubbers')
        ->wait(1)
        ->assertSeeIn('@rubber-list', 'Ana / Bea')
        ->assertSeeIn('@rubber-list', 'Cara / Dana')
        ->assertSeeIn('@rubber-list', 'Eve / Fay')
        ->assertSeeIn('@rubber-list', 'Gia / Hana');

    $page->assertScript(sprintf(
        <<<'JS'
        function() {
            const rows = document.querySelectorAll('[data-testid="rubber-row"]');
            const edits = document.querySelectorAll('[data-testid="rubber-edit"]');
            const scores = [...document.querySelectorAll('[data-testid="rubber-score"]')];

            if (rows.length !== 2 || edits.length !== 2 || scores.length !== 2) {
                return false;
            }

            const expected = [%s].map((id) => `/admin/tennis-matches/${id}`).sort();
            const actual = scores.map((link) => new URL(link.href).pathname).sort();

            return JSON.stringify(actual) === JSON.stringify(expected);
        }
        JS,
        implode(', ', $matchIds),
    ));

    $page->click('@rubber-edit')
        ->wait(1)
        ->assertSee('Edit Rubber')
        ->click('@rubber-type-singles')
        ->click('@rubber-submit')
        ->wait(3);

    $page->assertScript(<<<'JS'
        document.querySelectorAll('[data-testid="rubber-row"]').length === 2
        JS);

    $singles = TennisMatch::orderBy('id')
        ->pluck('side_a_name')
        ->filter(fn (string $name) => ! str_contains($name, ' / '));

    expect($singles)->toHaveCount(1);
});

it('stacks the status above the row actions on mobile only', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    startTeamRubber($division, $admin);

    $page = openRubberBracket($admin, $tournament, $division);

    $page->click('@rubbers')->wait(1);

    $page->resize(390, 844)->assertScript(<<<'JS'
        function() {
            const row = document.querySelector('[data-testid="rubber-row"]');
            const status = row.querySelector('[data-testid="rubber-status"]').getBoundingClientRect();
            const edit = row.querySelector('[data-testid="rubber-edit"]').getBoundingClientRect();

            return status.bottom <= edit.top;
        }
        JS);

    $page->resize(1280, 900)->assertScript(<<<'JS'
        function() {
            const row = document.querySelector('[data-testid="rubber-row"]');
            const status = row.querySelector('[data-testid="rubber-status"]').getBoundingClientRect();
            const edit = row.querySelector('[data-testid="rubber-edit"]').getBoundingClientRect();

            return status.top < edit.bottom && status.bottom > edit.top;
        }
        JS);
});

it('counts completed rubbers in the card summary', function () {
    [$admin, $tournament, $division] = rubberBracketSetup();

    $match = startTeamRubber($division, $admin);
    $match->update(['status' => MatchStatus::Completed]);

    $page = openRubberBracket($admin, $tournament, $division);

    $page->assertSeeIn('@rubber-summary', '1 rubber')
        ->assertSeeIn('@rubber-summary', '1 completed');
});
