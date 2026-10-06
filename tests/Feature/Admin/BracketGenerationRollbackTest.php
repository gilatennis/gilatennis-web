<?php

use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;

function rollbackDivision(int $drawSize = 8): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'draw_size' => $drawSize,
    ]);
}

function rollbackEntrants(Division $division, int $count): void
{
    foreach (range(1, $count) as $i) {
        TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]);
    }
}

it('rolls back a group stage when a group-entrant write fails mid-generation', function () {
    $division = rollbackDivision();
    rollbackEntrants($division, 6);

    BracketGroupEntrant::created(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(BracketService::class)->generateGroups($division, 3, [1, 1, 1]))
        ->toThrow(RuntimeException::class);

    expect(Bracket::where('division_id', $division->id)->exists())->toBeFalse()
        ->and(BracketGroup::count())->toBe(0)
        ->and(BracketGroupEntrant::count())->toBe(0);
});

it('rolls back a knockout bracket when a slot write fails mid-generation', function () {
    $division = rollbackDivision();
    rollbackEntrants($division, 6);

    BracketSlot::created(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(BracketService::class)->generate($division))
        ->toThrow(RuntimeException::class);

    expect(Bracket::where('division_id', $division->id)->exists())->toBeFalse()
        ->and(BracketSlot::count())->toBe(0);
});
