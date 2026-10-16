<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Enums\BracketType;
use App\Enums\TournamentStatus;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\DivisionPlayerFieldRequirement;
use App\Models\Team;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;
use Illuminate\Support\Str;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Execution;
use Pest\Browser\Playwright\Playwright;

/*
|--------------------------------------------------------------------------
| Browser journey helpers
|--------------------------------------------------------------------------
|
| Shared by the team-tournament browser journey. These are plain global
| functions (Pest style) loaded from tests/Pest.php. They drive the real
| public and admin UI; the in-process browser server shares the app
| container, so test()->swap() and the database apply to the browser.
|
| Pest opens a fresh browser context (a new window in --headed) on every
| visit() call, so a journey that re-visits would spray windows. Instead we
| keep ONE page per test and navigate it in place via Page::goto(); the
| journey therefore stays in a single window. The page is reset before each
| test because the plugin closes the context after every test.
|
*/

/**
 * Shared state for the team-tournament journey: the single browser page reused
 * across a test, plus fixed fixture values.
 *
 * @internal
 */
final class TeamJourney
{
    /**
     * Points to win a Fast-4 set: four games, each won with four straight
     * points by the same side.
     */
    public const FAST4_POINTS = 16;

    /**
     * The one page reused across a test's journey steps.
     */
    public static ?AwaitableWebpage $page = null;
}

/**
 * Forget the current page. Call before each test so a closed context from the
 * previous test is never reused.
 */
function teamJourneyReset(): void
{
    TeamJourney::$page = null;
}

/**
 * Navigate to a URL, reusing the current window when one is already open.
 */
function teamJourneyNavigate(string $url, float $wait = 2): AwaitableWebpage
{
    // Page::goto() needs an absolute URL, so resolve anything relative here.
    $absolute = Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);

    if (TeamJourney::$page === null) {
        return TeamJourney::$page = visit($absolute)->wait($wait);
    }

    TeamJourney::$page->page()->goto($absolute);
    TeamJourney::$page->wait($wait);

    return TeamJourney::$page;
}

/**
 * An extra pause that only applies when watching headed, so the flow is easy
 * to follow without slowing down headless CI runs.
 */
function teamJourneyBeat(float $seconds = 1.5): void
{
    if (! Playwright::isHeadless()) {
        Execution::instance()->wait($seconds);
    }
}

/**
 * An admin able to drive the admin bracket endpoints.
 */
function teamJourneyAdmin(): User
{
    return User::factory()->admin()->create();
}

/**
 * A team tournament with a single team division and N seeded paid teams.
 *
 * @return array{0: User, 1: Tournament, 2: Division}
 */
function teamJourneyTournament(int $seededTeams = 6): array
{
    $admin = teamJourneyAdmin();

    $tournament = Tournament::factory()->create(['status' => TournamentStatus::Open]);

    $division = Division::factory()
        ->forTournament($tournament)
        ->team()
        ->create([
            'draw_size' => 8,
            'fee' => 120000,
            'min_team_size' => 2,
            'max_team_size' => 2,
            'gender' => null,
            'sort_order' => 0,
        ]);

    // Relax the level requirement so the public team form stays short; the
    // division already carries no level requirements of its own.
    DivisionPlayerFieldRequirement::updateOrCreate(
        ['division_id' => $division->id, 'field_key' => 'level'],
        ['required' => false],
    );

    foreach (range(1, $seededTeams) as $i) {
        teamJourneySeedTeam($division, "Seeded Team {$i}");
    }

    return [$admin, $tournament, $division];
}

/**
 * A paid team entrant built directly (a Team plus its registration).
 */
function teamJourneySeedTeam(Division $division, string $name): TournamentRegistration
{
    $team = Team::factory()->create(['name' => $name]);

    return TournamentRegistration::factory()->paid()->create([
        'tournament_id' => $division->tournament_id,
        'division_id' => $division->id,
        'team_id' => $team->id,
        'name' => $name,
    ]);
}

function teamJourneyLogin(User $admin): AwaitableWebpage
{
    return teamJourneyNavigate('/admin/login', 1)
        ->fill('email', $admin->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->assertPathIs('/admin/dashboard');
}

function teamJourneyGotoDivision(Tournament $tournament, Division $division, string $tab = 'brackets'): AwaitableWebpage
{
    return teamJourneyNavigate(route('admin.tournaments.divisions.show', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'tab' => $tab,
    ]));
}

/**
 * Swap in a Duitku service whose invoice redirects back into the app, so the
 * browser never leaves the test server. Settlement stays with the admin UI.
 */
function teamJourneyFakeInvoice(): void
{
    test()->swap(DuitkuService::class, new class extends DuitkuService
    {
        public function __construct() {}

        #[Override]
        public function createInvoice(TournamentRegistration $registration): DuitkuInvoiceResponse
        {
            return new DuitkuInvoiceResponse(
                merchantCode: 'DEMO0001',
                reference: 'DK-TEAM'.strtoupper(substr(md5($registration->confirmation_code), 0, 6)),
                paymentUrl: route('payments.duitku.return', [
                    'merchantOrderId' => $registration->confirmation_code,
                    'resultCode' => '00',
                ]),
                statusCode: '00',
                statusMessage: 'Invoice created',
            );
        }
    });
}

/**
 * Register a two-player team through the public form. Returns the (pending)
 * registration the form created.
 */
function teamJourneyRegisterTeamViaUi(
    Tournament $tournament,
    Division $division,
    string $teamName,
    string $captainEmail,
): TournamentRegistration {
    teamJourneyFakeInvoice();

    $page = teamJourneyNavigate(route('tournaments.register', $tournament->slug), 1);

    $page->fill('team_name', $teamName)
        ->click('Next')
        ->wait(1);

    $page->fill('name', 'Captain '.$teamName)
        ->fill('email', $captainEmail)
        ->fill('phone', '0812000000')
        ->click('#gender');
    $page->click('[data-value="male"]');

    $page->click('Add player');
    $page->fill('#player-1-name', 'Player '.$teamName)
        ->fill('#player-1-email', 'player-'.strtolower(str_replace(' ', '-', $teamName)).'@example.com')
        ->fill('#player-1-phone', '0813000000')
        ->click('#player-1-gender');
    $page->click('[data-value="male"]');

    $page->click('Review order')
        ->assertSee('Review & pay')
        ->check('waiver_accepted')
        ->click('Proceed to Payment')
        ->wait(2);

    return TournamentRegistration::where('email', $captainEmail)->firstOrFail();
}

/**
 * Open the registration from the division's registrants tab and mark it paid.
 */
function teamJourneyMarkPaidViaUi(
    Tournament $tournament,
    Division $division,
    TournamentRegistration $registration,
): TournamentRegistration {
    $page = teamJourneyGotoDivision($tournament, $division, 'registrants');

    $page->click('@registrant-row-'.$registration->id)->wait(2);

    $page->assertPathIs(
        "/admin/tournaments/{$tournament->id}/divisions/{$division->id}/registrations/{$registration->confirmation_code}"
    );

    $page->click('@mark-paid')
        ->assertPresent('@confirm-payment')
        ->click('@confirm-payment')->wait(2);

    return $registration->fresh();
}

/**
 * Generate the round-robin groups through the dialog (default one advance per
 * group).
 */
function teamJourneyGenerateGroups(AwaitableWebpage $page, int $groupCount = 3): void
{
    $page->click('Generate Groups')->assertPresent('@generate-groups');

    // The dialog opens on two groups; bump it to the requested count.
    for ($i = 2; $i < $groupCount; $i++) {
        $page->click('@groups-inc');
    }

    // The promote control only exists once the group stage has been created.
    $page->click('@generate-groups')->assertPresent('@open-promote');
}

function teamJourneyGroupStage(Division $division): Bracket
{
    return Bracket::where('division_id', $division->id)
        ->where('type', BracketType::GroupStage)
        ->firstOrFail();
}

function teamJourneyKnockout(Division $division): Bracket
{
    return Bracket::where('division_id', $division->id)
        ->where('type', BracketType::SingleElimination)
        ->firstOrFail();
}

/**
 * Play one team tie end to end through the UI: add a singles rubber at Fast4,
 * score it on the scoreboard, then set the tie winner by hand (team ties never
 * auto-resolve). Returns the completed rubber.
 */
function teamJourneyPlayTieViaUi(
    Tournament $tournament,
    Division $division,
    int $slotId,
    int $winningSide = 1,
): TennisMatch {
    $page = teamJourneyGotoDivision($tournament, $division, 'brackets');

    // Open this tie's rubbers dialog (the trigger is scoped by slot id).
    $page->click('[data-testid="rubbers"][data-slot="'.$slotId.'"]')
        ->assertPresent('@rubber-type-singles');

    // One singles rubber, first to four games (a Fast-4 set).
    $page->click('@rubber-type-singles');

    // The format toggles are switches: sudden death defaults on, and clicking
    // the switch flips its aria-checked state.
    $page->assertScript(
        "document.querySelector('[data-testid=\"rubber-sudden-death\"]').getAttribute('aria-checked') === 'true'"
    );
    $page->click('@rubber-sudden-death');
    $page->assertScript(
        "document.querySelector('[data-testid=\"rubber-sudden-death\"]').getAttribute('aria-checked') === 'false'"
    );

    $page->select('@rubber-sets-to-win', '1');
    $page->select('@rubber-games-to-win', '4');
    $page->click('@rubber-submit');

    $page->assertScript(
        "document.querySelectorAll('[data-testid=\"rubber-row\"]').length === 1"
    );

    // Open the rubber's scoreboard from its edit form and play a Fast-4 set.
    $page->click('@rubber-edit')
        ->assertPresent('@rubber-score-link')
        ->click('@rubber-score-link')
        ->assertPresent('@server-pick-a');

    $match = TennisMatch::where('bracket_slot_id', $slotId)->latest('id')->firstOrFail();

    $page->click($winningSide === 1 ? '@server-pick-a' : '@server-pick-b')
        ->click('@start-match')->wait(1);

    for ($i = 0; $i < TeamJourney::FAST4_POINTS; $i++) {
        $page->click($winningSide === 1 ? '@point-a' : '@point-b');
    }

    $page->wait(1)->click('@mark-completed')->wait(2);

    // Back to the bracket: the tie winner is an explicit admin decision.
    $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
    $page->click('@advance-slot-'.$slotId.'-side-'.$winningSide)
        ->assertPresent('@confirm-modal-accept')
        ->click('@confirm-modal-accept')->wait(2);

    return $match->refresh();
}

/**
 * Pick the recorded winner of each group as the one that advances.
 */
function teamJourneyPickGroupWinners(AwaitableWebpage $page, Bracket $groupStage): void
{
    foreach ($groupStage->groups as $group) {
        $winnerId = $group->slots()->first()?->winner_entrant_id;

        if ($winnerId === null) {
            continue;
        }

        $page->click('@pick-winner-'.$winnerId)->wait(2);
    }
}

/**
 * The shared run-up to the knockout: generate the round-robin groups, play
 * every tie, and record each group's advancing winner.
 */
function teamJourneyCompleteGroupStage(Tournament $tournament, Division $division, int $groupCount = 3): void
{
    $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
    teamJourneyGenerateGroups($page, $groupCount);

    foreach (teamJourneyGroupStage($division)->groups()->with('slots')->get() as $group) {
        teamJourneyPlayTieViaUi($tournament, $division, $group->slots->first()->id, 1);
    }

    $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
    teamJourneyPickGroupWinners($page, teamJourneyGroupStage($division));
}

/**
 * Promote the picked group winners into the knockout. With three groups the
 * four-slot draw carries a bye, which the dialog warns about.
 */
function teamJourneyPromoteWinners(Tournament $tournament, Division $division): void
{
    $page = teamJourneyGotoDivision($tournament, $division, 'brackets');

    // Promotion is an async POST that creates the knockout bracket.
    $page->click('@open-promote')
        ->assertPresent('@promote-winners')
        ->assertPresent('@bye-warning')
        ->click('@promote-winners')
        ->wait(3);
}
