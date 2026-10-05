<?php

use App\Enums\BracketStatus;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

/**
 * Drives the whole tournament lifecycle through the admin UI: generate the
 * bracket, start each tie, record its result, complete the match, advance the
 * tie by hand, and read the champion off the finished bracket.
 *
 * Match results are seeded through the scoring endpoints (as MatchScoringTest
 * does) so this file focuses on the bracket UI wiring: starting a tie, the
 * explicit advance dialog, propagation, and the team rubbers dialog.
 */
function e2eBrowserAdmin(): User
{
    return User::factory()->admin()->create();
}

function e2eBrowserTournament(string $category, int $drawSize): array
{
    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => $category,
        'name' => ucfirst($category),
        'slug' => $category,
        'draw_size' => $drawSize,
    ]);

    foreach (range(1, $drawSize) as $i) {
        TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]);
    }

    $admin = e2eBrowserAdmin();

    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.store', [
        'tournament' => $tournament->id,
        'division' => $division->id,
    ]), ['seeding_mode' => 'auto']);

    return [$admin, $tournament, $division];
}

function e2eBrowserLogin(User $admin)
{
    return visit('/admin/login')
        ->fill('email', $admin->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->wait(1)
        ->assertPathIs('/admin/dashboard');
}

function e2eBrowserGotoBracket(Tournament $tournament, Division $division)
{
    return visit(route('admin.tournaments.divisions.show', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'tab' => 'matches',
    ]))->wait(2);
}

/**
 * Record a completed Fast4 win for side 1 through the scoring endpoints, then
 * confirm the match, mirroring how an umpire would finish a rubber.
 */
function e2eBrowserFinishMatch(User $admin, TennisMatch $match): void
{
    test()->actingAs($admin)->patch(route('admin.tennis-matches.update', $match), [
        'sets_to_win' => 1,
        'games_to_win' => 4,
        'extend_at_tie' => false,
    ]);

    test()->actingAs($admin)->postJson(route('admin.tennis-matches.server', $match), ['side' => 1])->assertOk();

    for ($i = 0; $i < 16; $i++) {
        test()->actingAs($admin)->postJson(route('admin.tennis-matches.points', $match), ['side' => 1])->assertOk();
    }

    test()->actingAs($admin)->patch(route('admin.tennis-matches.update', $match), ['status' => 'completed']);
}

describe('end to end tournament through the admin UI', function () {
    it('plays a singles draw to a champion through the bracket', function () {
        [$admin, $tournament, $division] = e2eBrowserTournament('singles', 4);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();

        e2eBrowserLogin($admin);

        // Each opening tie: start it from the UI, finish the match, then advance
        // the higher seed through the "Set winner" dialog.
        foreach ($round1 as $slot) {
            $page = e2eBrowserGotoBracket($tournament, $division);

            $page->click("@start-slot-{$slot->id}")->wait(3);

            $match = TennisMatch::where('bracket_slot_id', $slot->id)->latest('id')->firstOrFail();
            e2eBrowserFinishMatch($admin, $match);

            $page = e2eBrowserGotoBracket($tournament, $division);

            $page->assertSee('Awaiting result')
                ->click("@advance-slot-{$slot->id}-side-1")
                ->wait(1)
                ->assertSee('Set winner')
                ->click('@confirm-modal-accept')
                ->wait(2);

            expect($slot->fresh()->winner_entrant_id)->toBe($slot->entrant_a_id);
        }

        // Both opening winners reach the final; play it and crown a champion.
        $final = $bracket->fresh()->finalSlot();
        $page = e2eBrowserGotoBracket($tournament, $division);

        $page->click("@start-slot-{$final->id}")->wait(3);

        $finalMatch = TennisMatch::where('bracket_slot_id', $final->id)->latest('id')->firstOrFail();
        e2eBrowserFinishMatch($admin, $finalMatch);

        $page = e2eBrowserGotoBracket($tournament, $division);

        $page->click("@advance-slot-{$final->id}-side-1")
            ->wait(1)
            ->click('@confirm-modal-accept')
            ->wait(2)
            ->assertSee('Winner')
            ->assertNoJavaScriptErrors();

        $bracket->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($final->entrant_a_id)
            ->and($bracket->status)->toBe(BracketStatus::Completed)
            ->and(TennisMatch::where('status', 'completed')->count())->toBe(3);
    });

    it('stacks rubbers on a team tie and leaves the tie winner to the admin', function () {
        [$admin, $tournament, $division] = e2eBrowserTournament('team', 2);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->firstOrFail();

        e2eBrowserLogin($admin);
        $page = e2eBrowserGotoBracket($tournament, $division);

        $page->assertSeeIn('@rubber-summary', 'No rubbers set up')
            ->click('@rubbers')
            ->wait(1)
            ->click('@rubber-type-singles')
            ->click('@rubber-submit')
            ->wait(3);

        // The dialog stays open in list mode; add a second rubber from its footer.
        $page->click('@rubber-add')
            ->wait(1)
            ->click('@rubber-type-singles')
            ->click('@rubber-submit')
            ->wait(3);

        $page->assertScript(
            "document.querySelectorAll('[data-testid=\"rubber-row\"]').length === 2"
        )->assertNoJavaScriptErrors();

        $page->click('Close')
            ->wait(1)
            ->assertSeeIn('@rubber-summary', '2 rubbers');

        // Two rubbers share one tie and neither finishes it: the winner is manual.
        expect(TennisMatch::where('bracket_slot_id', $slot->id)->count())->toBe(2)
            ->and($slot->fresh()->winner_entrant_id)->toBeNull()
            ->and($slot->fresh()->isComplete())->toBeFalse();
    });
});
