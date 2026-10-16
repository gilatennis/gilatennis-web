<?php

use App\Models\Division;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;

/**
 * Create a team registration with its roster, mirroring the production shape:
 * a team row, a registration row and one player row per name (in order).
 *
 * @param  array<int, string>  $players
 */
function createTeamRegistration(
    Tournament $tournament,
    Division $division,
    string $teamName,
    array $players,
    array $registration = [],
): TournamentRegistration {
    $team = Team::create(['name' => $teamName]);

    $registration = TournamentRegistration::factory()
        ->forTournament($tournament)
        ->create($registration + [
            'division_id' => $division->id,
            'team_id' => $team->id,
            'payment_status' => 'paid',
        ]);

    foreach ($players as $index => $name) {
        TournamentRegistrationPlayer::create([
            'tournament_registration_id' => $registration->id,
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'name' => $name,
            'gender' => 'female',
            'sort_order' => $index,
        ]);
    }

    return $registration;
}

beforeEach(function () {
    $this->tournament = Tournament::factory()->create([
        'slug' => 'gilatennis-recreational-team-tournament',
    ]);

    $this->division = Division::factory()->for($this->tournament)->create([
        'participation_category' => 'team',
        'min_team_size' => 6,
        'max_team_size' => 10,
        'min_female_players' => 4,
        'min_male_players' => 2,
    ]);

    $this->path = base_path('tests/Fixtures/rosters-test.php');

    $this->registration = createTeamRegistration(
        $this->tournament,
        $this->division,
        'Tuesday Tribe',
        ['Dwi Ana', 'Adinda', 'FABIOLA', 'Elin', 'Estu', 'Ronny'],
    );
});

it('does not write anything on a dry run', function () {
    $this->artisan('tournament:sync-team-rosters', [
        'tournament' => $this->tournament->slug,
        '--path' => $this->path,
    ])->assertSuccessful();

    expect($this->registration->fresh()->team->name)->toBe('Tuesday Tribe');
    expect($this->registration->players()->pluck('name')->all())
        ->toBe(['Dwi Ana', 'Adinda', 'FABIOLA', 'Elin', 'Estu', 'Ronny']);
});

it('renames, adds and removes players and the team name', function () {
    $this->artisan('tournament:sync-team-rosters', [
        'tournament' => $this->tournament->slug,
        '--path' => $this->path,
        '--apply' => true,
    ])->assertSuccessful();

    $registration = $this->registration->fresh();

    expect($registration->team->name)->toBe('TUESDAY TRIBE');
    expect($registration->players()->pluck('name')->all())->toBe([
        'Dwi Anna',
        'Adinda R',
        'Fabiola Dona',
        'Herlina Nasution',
        'Eka Pangestu',
        'Haerul',
    ]);
    expect($registration->players()->pluck('sort_order')->all())->toBe([0, 1, 2, 3, 4, 5]);
});

it('keeps the recorded gender of renamed players', function () {
    $this->division->update(['min_female_players' => 0, 'min_male_players' => 0]);
    $this->registration->players()->where('name', 'Dwi Ana')->update(['gender' => 'male']);

    $this->artisan('tournament:sync-team-rosters', [
        'tournament' => $this->tournament->slug,
        '--path' => $this->path,
        '--apply' => true,
    ])->assertSuccessful();

    expect($this->registration->players()->where('name', 'Dwi Anna')->value('gender'))->toBe('male');
});

it('is idempotent', function () {
    $command = ['tournament' => $this->tournament->slug, '--path' => $this->path, '--apply' => true];

    $this->artisan('tournament:sync-team-rosters', $command)->assertSuccessful();
    $this->artisan('tournament:sync-team-rosters', $command)->assertSuccessful();

    expect($this->registration->players()->count())->toBe(6);
    expect($this->registration->fresh()->team->name)->toBe('TUESDAY TRIBE');
});

it('prunes duplicate and extra registrations but keeps withdrawn ones', function () {
    $duplicate = createTeamRegistration(
        $this->tournament,
        $this->division,
        'Tuesday Tribe',
        ['Dwi Ana', 'Adinda'],
        ['payment_status' => 'processing'],
    );

    $extra = createTeamRegistration(
        $this->tournament,
        $this->division,
        'Hardi',
        ['Hardi', 'Susi'],
        ['payment_status' => 'processing'],
    );

    $withdrawn = createTeamRegistration(
        $this->tournament,
        $this->division,
        'pasir putih',
        ['emilia oei'],
        ['payment_status' => 'refunded', 'withdrawn_at' => now()],
    );

    $this->artisan('tournament:sync-team-rosters', [
        'tournament' => $this->tournament->slug,
        '--path' => $this->path,
        '--apply' => true,
        '--prune' => true,
    ])->assertSuccessful();

    expect(TournamentRegistration::find($duplicate->id))->toBeNull();
    expect(TournamentRegistration::find($extra->id))->toBeNull();
    expect(TournamentRegistration::find($withdrawn->id))->not->toBeNull();
    expect(TournamentRegistration::find($this->registration->id))->not->toBeNull();
    expect(Team::where('name', 'Hardi')->exists())->toBeFalse();
});

it('does not prune without the flag', function () {
    $duplicate = createTeamRegistration(
        $this->tournament,
        $this->division,
        'Tuesday Tribe',
        ['Dwi Ana'],
        ['payment_status' => 'processing'],
    );

    $this->artisan('tournament:sync-team-rosters', [
        'tournament' => $this->tournament->slug,
        '--path' => $this->path,
        '--apply' => true,
    ])->assertSuccessful();

    expect(TournamentRegistration::find($duplicate->id))->not->toBeNull();
});
