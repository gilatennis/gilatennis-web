<?php

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;

const SAVE_WAIT_S = 2;

function loginAsAdmin(User $user)
{
    return visit('/admin/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->wait(1)
        ->assertPathIs('/admin/dashboard');
}

function openMatchScorePage(User $user, TennisMatch $match)
{
    return loginAsAdmin($user)
        ->navigate(route('admin.tennis-matches.show', $match))
        ->wait(1)
        ->assertPathIs('/admin/tennis-matches/'.$match->id);
}

function refreshMatch(TennisMatch $match): TennisMatch
{
    return $match->fresh(['state']);
}

function scoreToMatchPoint(TennisMatch $match, User $user): void
{
    test()->actingAs($user)
        ->postJson(route('admin.tennis-matches.server', $match), ['side' => 1])
        ->assertOk();

    for ($i = 0; $i < 15; $i++) {
        test()->actingAs($user)
            ->postJson(route('admin.tennis-matches.points', $match), ['side' => 1])
            ->assertOk();
    }
}

function oneSetFast4Match(): TennisMatch
{
    return TennisMatch::factory()->create([
        'sets_to_win' => 1,
        'games_to_win' => 4,
        'extend_at_tie' => false,
    ]);
}

describe('match score page access', function () {
    it('shows pending state on a fresh match for an admin', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);

        $page->assertSee('Pending')
            ->assertSee('Not started yet')
            ->assertSee('Who serves first?')
            ->assertSee('Best of 3 set(s), 6 games, TB to 7')
            ->assertMissing('@start-match')
            ->assertPresent('@server-pick-a')
            ->assertPresent('@server-pick-b')
            ->assertMissing('@point-a')
            ->assertMissing('@point-b')
            ->assertMissing('@undo');
    });

    it('stacks doubles partner names on separate lines in the heading', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create([
            'side_a_name' => 'Andi Wijaya / Budi Santoso',
            'side_b_name' => 'Citra Dewi / Dedi Kurnia',
        ]);

        $page = openMatchScorePage($user, $match);

        $page->assertScript(
            'Array.from(document.querySelectorAll("h1 span.block")).map((el) => el.textContent.trim()).join("|")',
            'Andi Wijaya|/ Budi Santoso|Citra Dewi|/ Dedi Kurnia',
        )->assertNoJavaScriptErrors();
    });

    it('keeps a singles side name on a single line in the heading', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create([
            'side_a_name' => 'Novak Djokovic',
            'side_b_name' => 'Carlos Alcaraz',
        ]);

        $page = openMatchScorePage($user, $match);

        $page->assertScript(
            'document.querySelectorAll("h1 span.block").length',
            0,
        )->assertSee('Novak Djokovic');
    });

    it('scales the heading font size up on wider viewports', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);

        $headingSize =
            'getComputedStyle(document.querySelector(\'[data-testid="match-heading"]\')).fontSize';

        $page->resize(390, 844)->assertScript($headingSize, '16px');
        $page->resize(700, 900)->assertScript($headingSize, '28px');
        $page->resize(1280, 900)->assertScript($headingSize, '28px');
    });

    it('links back to the brackets for a bracket match', function () {
        $user = User::factory()->admin()->create();
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->forTournament($tournament)->create();
        $bracket = Bracket::factory()->forDivision($division)->create();
        $slot = BracketSlot::factory()->forBracket($bracket)->create();
        $match = TennisMatch::factory()->create([
            'division_id' => $division->id,
            'bracket_slot_id' => $slot->id,
        ]);

        $page = openMatchScorePage($user, $match);

        $page->assertPresent('@back-to-brackets')
            ->click('@back-to-brackets')
            ->wait(1)
            ->assertPathIs(
                '/admin/tournaments/'.$tournament->id.'/divisions/'.$division->id,
            )
            ->assertQueryStringHas('tab', 'brackets');
    });

    it('denies non-admin users and redirects to admin login', function () {
        $user = User::factory()->create();
        $match = TennisMatch::factory()->create();

        visit(route('admin.tennis-matches.show', $match))
            ->wait(1)
            ->assertPathIs('/admin/login');
    });
});

describe('match start flow', function () {
    it('starts the match with side A serving and persists state', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();
        $a = $match->side_a_name;

        $page = openMatchScorePage($user, $match);

        $page->click('@server-pick-a')
            ->assertButtonEnabled('@start-match')
            ->click('@start-match')
            ->wait(1)
            ->assertSee('Running')
            ->assertDontSee('Not started yet')
            ->assertSee($a.' serving');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->status->value)->toBe('running');
        expect($match->state->first_server)->toBe(1);
        expect($match->state->server)->toBe(1);
    });

    it('starts the match with side B serving when picked', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();
        $b = $match->side_b_name;

        $page = openMatchScorePage($user, $match);

        $page->click('@server-pick-b')
            ->click('@start-match')
            ->wait(1)
            ->assertSee($b.' serving');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->first_server)->toBe(2);
    });
});

describe('game scoring', function () {
    it('awards four points to side A to win the first game', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();
        $b = $match->side_b_name;

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        for ($i = 0; $i < 4; $i++) {
            $page->click('@point-a');
        }

        $page->wait(1)
            ->assertSee($b.' serving');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->set_a)->toBe(1);
        expect($match->state->set_b)->toBe(0);
        expect($match->state->sets)->toBe([]);
        expect($match->state->server)->toBe(2);
    });

    it('rotates the server after every game', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();
        $a = $match->side_a_name;
        $b = $match->side_b_name;

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        for ($i = 0; $i < 4; $i++) {
            $page->click('@point-a');
        }
        $page->wait(1)
            ->assertSee($b.' serving');

        for ($i = 0; $i < 4; $i++) {
            $page->click('@point-b');
        }
        $page->wait(1)
            ->assertSee($a.' serving');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->set_a)->toBe(1);
        expect($match->state->set_b)->toBe(1);
        expect($match->state->server)->toBe(1);
    });

    it('renders the Deuce banner when the state is at 3-3 and a single point flips the banner', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create(['sudden_death' => false]);
        $a = $match->side_a_name;

        $match->update(['status' => 'running', 'started_at' => now()]);
        $match->events()->create(['seq' => 1, 'type' => 'server', 'side' => 1]);

        foreach ([1, 2, 1, 2, 1, 2] as $index => $side) {
            $match->events()->create(['seq' => $index + 2, 'type' => 'point', 'side' => $side]);
        }

        $match->state->update([
            'server' => 1,
            'first_server' => 1,
            'set_a' => 0,
            'set_b' => 0,
            'game_a' => 3,
            'game_b' => 3,
            'tiebreak' => false,
            'tb_a' => 0,
            'tb_b' => 0,
            'tb_first' => null,
        ]);

        $page = openMatchScorePage($user, $match);

        $page->wait(1)
            ->assertSee('Deuce');

        $page->click('@point-a')
            ->wait(SAVE_WAIT_S)
            ->assertSee('Advantage · '.$a);

        $page->click('@point-b')
            ->wait(SAVE_WAIT_S)
            ->assertSee('Deuce');

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(3);
        expect($match->state->game_b)->toBe(3);
    });
});

describe('shot and event recording', function () {
    it('toggles a shot tag without scoring a point', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->click('@shot-ace')
            ->assertSee('Shot: Ace')
            ->assertSee('now tap a player');

        $page->click('@shot-ace')
            ->assertSee('Tap a shot type before awarding a point');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(0);
        expect($match->state->game_b)->toBe(0);
    });

    it('toggles a let without scoring a point', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->click('@event-let')
            ->assertSee('Shot: Let');

        $page->click('@event-let')
            ->assertSee('Tap a shot type before awarding a point');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(0);
        expect($match->state->game_b)->toBe(0);
    })->skip('Let button hidden for now');

    it('disables double fault until a fault is recorded', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->assertButtonDisabled('@event-double-fault');

        $page->click('@event-fault');

        $page->assertButtonEnabled('@event-double-fault');
    });

    it('awards a point to the receiver on a double fault', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->click('@event-fault')
            ->click('@event-double-fault');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(0);
        expect($match->state->game_b)->toBe(1);
    });
});

describe('undo', function () {
    it('is enabled after the match starts (to allow cancelling the server pick)', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->assertButtonEnabled('@undo');
    });

    it('rolls back a scored point', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->click('@point-a')
            ->click('@point-a')
            ->wait(1);

        $page->click('@undo')->wait(1);

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(1);
    });

    it('returns to the pending state when undone past the first server pick', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $page = openMatchScorePage($user, $match);
        $page->click('@server-pick-a')->click('@start-match')->wait(1);

        $page->click('@undo')
            ->wait(1)
            ->assertSee('Who serves first?')
            ->assertSee('Pending');
    });
});

describe('tiebreak and match completion', function () {
    it('renders the Tiebreak banner and tiebreak scores when the state is already in a tiebreak', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create();

        $match->update(['status' => 'running']);
        $match->state->update([
            'server' => 1,
            'first_server' => 1,
            'set_a' => 6,
            'set_b' => 6,
            'game_a' => 0,
            'game_b' => 0,
            'tiebreak' => true,
            'tb_a' => 2,
            'tb_b' => 1,
            'tb_first' => 1,
        ]);

        $page = openMatchScorePage($user, $match);

        $page->wait(1)
            ->assertSee('Tiebreak')
            ->assertSeeIn('@point-a', $match->side_a_name)
            ->assertScript('document.querySelectorAll("[data-testid=serving-dot]").length', 1)
            ->assertNoJavaScriptErrors();
    });

    it('shows the Match Winner panel when one side has won two sets in fast4', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->fast4()->create();
        $a = $match->side_a_name;

        $match->update(['status' => 'completed']);
        $match->state->update([
            'server' => 1,
            'first_server' => 1,
            'set_a' => 0,
            'set_b' => 0,
            'game_a' => 0,
            'game_b' => 0,
            'tiebreak' => false,
            'tb_a' => 0,
            'tb_b' => 0,
            'tb_first' => null,
            'sets' => [
                ['a' => 4, 'b' => 0],
                ['a' => 4, 'b' => 0],
            ],
            'win' => 1,
        ]);

        $page = openMatchScorePage($user, $match);

        $page->wait(1)
            ->assertSee('Match Winner')
            ->assertSeeIn('@match-winner', $a);

        $winnerSize =
            'getComputedStyle(document.querySelector("[data-testid=match-winner]")).fontSize';

        $page->assertScript($winnerSize, '28px');
        $page->resize(390, 844)->assertScript($winnerSize, '16px');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->status->value)->toBe('completed');
        expect($match->state->win)->toBe(1);
        expect($match->state->sets)->toHaveCount(2);
    });

    it('shows a neutral completed panel when no winner was recorded', function () {
        $user = User::factory()->admin()->create();
        $match = TennisMatch::factory()->create(['status' => 'completed']);

        $page = openMatchScorePage($user, $match);

        $page->wait(1)
            ->assertVisible('@match-completed')
            ->assertMissing('@match-winner')
            ->assertMissing('@back-to-brackets')
            ->assertSee('Match completed')
            ->assertSee('No winner was recorded for this match.')
            ->assertSeeIn('@reopen-match', 'Reopen match');
    });

    it('surfaces the server message when the score is frozen', function () {
        $user = User::factory()->admin()->create();
        $match = oneSetFast4Match();

        test()->actingAs($user)
            ->postJson(route('admin.tennis-matches.server', $match), ['side' => 1])
            ->assertOk();

        test()->actingAs($user)
            ->postJson(route('admin.tennis-matches.points', $match), ['side' => 1])
            ->assertOk();

        $page = openMatchScorePage($user, $match);

        $page->assertMissing('@save-error')->assertButtonEnabled('@point-a');

        test()->actingAs($user)
            ->patch(route('admin.tennis-matches.update', $match), ['status' => 'completed']);

        $page->click('@point-a')->wait(1);

        $page->assertSeeIn(
            '@save-error',
            'The match is completed. Reopen it before changing the score.',
        );

        $match = refreshMatch($match);
        expect($match->state->game_a)->toBe(1)
            ->and($match->state->game_b)->toBe(0);
    });

    it('keeps scoring controls after the deciding point and completes on request', function () {
        $user = User::factory()->admin()->create();
        $match = oneSetFast4Match();

        scoreToMatchPoint($match, $user);

        $page = openMatchScorePage($user, $match);

        $page->assertMissing('@match-winner')
            ->assertButtonEnabled('@point-a')
            ->click('@point-a')
            ->wait(1);

        $page->assertVisible('@match-winner')
            ->assertVisible('@mark-completed')
            ->assertButtonDisabled('@point-a')
            ->assertButtonDisabled('@point-b')
            ->assertButtonEnabled('@undo');

        $page->click('@mark-completed')->wait(1);

        $page->assertVisible('@reopen-match')
            ->assertMissing('@point-a')
            ->assertMissing('@mark-completed');

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->status->value)->toBe('completed');
        expect($match->state->win)->toBe(1);
        expect($match->ended_at)->not->toBeNull();
    });

    it('reopens a completed match so the deciding point can be undone', function () {
        $user = User::factory()->admin()->create();
        $match = oneSetFast4Match();

        scoreToMatchPoint($match, $user);

        $page = openMatchScorePage($user, $match);

        $page->click('@point-a')->wait(1);
        $page->click('@mark-completed')->wait(1);

        $page->assertVisible('@reopen-match')
            ->assertMissing('@point-a');

        $page->click('@reopen-match')->wait(1);

        $page->assertVisible('@point-a')
            ->assertVisible('@match-winner');

        $page->click('@undo')->wait(1);

        $page->assertMissing('@match-winner')
            ->assertButtonEnabled('@point-a')
            ->assertNoJavaScriptErrors();

        $page->wait(SAVE_WAIT_S);

        $match = refreshMatch($match);
        expect($match->status->value)->toBe('running');
        expect($match->state->win)->toBeNull();
        expect($match->ended_at)->toBeNull();
    });
});
