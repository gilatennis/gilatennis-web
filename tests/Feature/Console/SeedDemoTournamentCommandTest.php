<?php

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Services\BracketService;

it('seeds twelve paid teams with ten players each and generates a 16-slot bracket', function () {
    $this->artisan('tournaments:seed-demo', ['--fresh' => true])->assertSuccessful();

    $tournament = Tournament::where('slug', 'turnamen-gilatennis-2026')->firstOrFail();
    $division = $tournament->divisions()->where('slug', 'team-competition')->firstOrFail();

    expect($division->draw_size)->toBe(12)
        ->and($division->paidRegistrations()->count())->toBe(12)
        ->and(Team::count())->toBe(12)
        ->and(TournamentRegistrationPlayer::count())->toBe(120);

    $division->paidRegistrations()->with('players')->get()->each(
        fn (TournamentRegistration $registration) => expect($registration->players)->toHaveCount(10),
    );

    $bracket = app(BracketService::class)->generate($division);

    expect($bracket->size)->toBe(16)
        ->and($bracket->slots()->count())->toBe(15)
        ->and($bracket->slots()->where('round', 1)->where('bye', true)->count())->toBe(4);
});

it('removes existing tournaments when the fresh option is used', function () {
    Tournament::factory()->create(['slug' => 'temp-tournament']);

    $this->artisan('tournaments:seed-demo', ['--fresh' => true])->assertSuccessful();

    expect(Tournament::where('slug', 'temp-tournament')->exists())->toBeFalse()
        ->and(Tournament::count())->toBe(1);
});

it('keeps the demo dataset when the same division is already seeded', function () {
    $this->artisan('tournaments:seed-demo', ['--fresh' => true])->assertSuccessful();
    $this->artisan('tournaments:seed-demo')->assertSuccessful();

    expect(TournamentRegistrationPlayer::count())->toBe(120)
        ->and(TournamentRegistration::where('payment_status', 'paid')->count())->toBe(12);
});

it('refuses to run in production and leaves data untouched', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $tournament = Tournament::factory()->create();

    $this->artisan('tournaments:seed-demo', ['--fresh' => true])->assertFailed();

    expect(Tournament::whereKey($tournament->id)->exists())->toBeTrue();
});
