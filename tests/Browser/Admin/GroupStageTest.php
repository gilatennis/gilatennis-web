<?php

use App\Models\Bracket;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Support\Facades\Vite;

beforeEach(fn () => Vite::useHotFile(sys_get_temp_dir().'/vite-hot-file-disabled'));

/**
 * @return array{0: User, 1: Tournament, 2: Division}
 */
function groupStageSetup(): array
{
    $admin = User::factory()->admin()->create();

    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'singles',
        'draw_size' => 8,
    ]);

    foreach (range(1, 8) as $i) {
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
            'tab' => 'matches',
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
        ->where('type', Bracket::TYPE_GROUP_STAGE)
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
        ->where('type', Bracket::TYPE_SINGLE_ELIMINATION)
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
