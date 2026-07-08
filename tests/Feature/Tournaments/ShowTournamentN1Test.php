<?php

use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Support\Facades\DB;

it('does not run a per-division count query on the public tournament show page', function () {
    $tournament = Tournament::factory()->create();

    $divisions = Division::factory()
        ->count(3)
        ->forTournament($tournament)
        ->create();

    foreach ($divisions as $division) {
        TournamentRegistration::factory()
            ->paid()
            ->count(2)
            ->create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
            ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get(route('tournaments.show', $tournament->slug))->assertOk();

    $perDivisionCountQueries = collect(DB::getQueryLog())->filter(
        fn ($q) => preg_match('/^select count\(\*\)/i', $q['query']) === 1
            && str_contains($q['query'], 'tournament_registrations')
            && str_contains($q['query'], 'division_id'),
    );

    expect($perDivisionCountQueries->count())->toBe(0);
});
