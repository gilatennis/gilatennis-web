<?php

use App\Enums\BracketStatus;
use App\Models\Bracket;
use App\Models\BracketGroupEntrant;
use App\Models\Division;
use App\Models\PaymentEvent;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function withdrawalDivision(int $drawSize = 8): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'draw_size' => $drawSize,
    ]);
}

function withdrawalEntrants(Division $division, int $count): array
{
    return collect(range(1, $count))
        ->map(fn ($i) => TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]))
        ->all();
}

describe('Registration withdrawal', function () {
    it('removes a placed entrant from a draft draw and frees its spot', function () {
        $division = withdrawalDivision(8);
        $entrants = withdrawalEntrants($division, 4);
        $target = $entrants[0];

        app(BracketService::class)->generate($division, 'auto');
        $bracket = Bracket::where('division_id', $division->id)->sole();

        expect($division->fresh()->spots_available)->toBe(4);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.withdraw', [
                'tournament' => $division->tournament_id,
                'registration' => $target,
            ]), ['reason' => 'Injured'])
            ->assertRedirect();

        $target->refresh();

        expect($target->withdrawn_at)->not->toBeNull()
            ->and($target->withdrawal_reason)->toBe('Injured')
            ->and($target->payment_status)->toBe('paid')
            ->and($bracket->slots()->where('round', 1)
                ->where(fn ($q) => $q->where('entrant_a_id', $target->id)->orWhere('entrant_b_id', $target->id))
                ->exists())->toBeFalse()
            ->and($division->fresh()->spots_available)->toBe(5);
    });

    it('optionally refunds the fee when withdrawing', function () {
        $division = withdrawalDivision(8);
        $target = withdrawalEntrants($division, 2)[0];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.withdraw', [
                'tournament' => $division->tournament_id,
                'registration' => $target,
            ]), ['refund' => true])
            ->assertRedirect();

        $target->refresh();

        expect($target->payment_status)->toBe('refunded')
            ->and($target->withdrawn_at)->not->toBeNull();

        expect(PaymentEvent::where([
            'tournament_registration_id' => $target->id,
            'source' => 'admin',
            'status_after' => 'refunded',
        ])->exists())->toBeTrue();
    });

    it('is rejected once the draw has started', function () {
        $division = withdrawalDivision(8);
        $entrants = withdrawalEntrants($division, 4);
        $target = $entrants[0];

        app(BracketService::class)->generate($division, 'auto');
        Bracket::where('division_id', $division->id)->update(['status' => BracketStatus::Active]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.withdraw', [
                'tournament' => $division->tournament_id,
                'registration' => $target,
            ]), [])
            ->assertRedirect()
            ->assertSessionHasErrors('registration');

        expect($target->fresh()->withdrawn_at)->toBeNull();
    });

    it('removes a member from a group stage and rebuilds its fixtures', function () {
        $division = withdrawalDivision(8);
        $entrants = withdrawalEntrants($division, 8);
        $target = $entrants[0];

        $bracket = app(BracketService::class)->generateGroups($division, 2, [2, 2]);

        $membership = BracketGroupEntrant::where('tournament_registration_id', $target->id)->firstOrFail();
        $group = $membership->group;
        $fixturesBefore = $group->slots()->count();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.withdraw', [
                'tournament' => $division->tournament_id,
                'registration' => $target,
            ]), [])
            ->assertRedirect();

        expect(BracketGroupEntrant::where('tournament_registration_id', $target->id)->exists())->toBeFalse()
            ->and($group->slots()->count())->toBeLessThan($fixturesBefore)
            ->and($target->fresh()->withdrawn_at)->not->toBeNull()
            ->and($bracket->groups()->count())->toBe(2);
    });

    it('lets a replacement register into the freed spot', function () {
        $division = withdrawalDivision(2);
        $entrants = withdrawalEntrants($division, 2);

        expect($division->fresh()->spots_available)->toBe(0)
            ->and($division->fresh()->is_sold_out)->toBeTrue();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.withdraw', [
                'tournament' => $division->tournament_id,
                'registration' => $entrants[0],
            ]), [])
            ->assertRedirect();

        $division->refresh();
        expect($division->spots_available)->toBe(1)
            ->and($division->is_sold_out)->toBeFalse();
    });

    it('filters and counts withdrawn registrations', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create(['tournament_id' => $tournament->id]);

        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
        ]);

        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'withdrawn_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', [
                'tournament' => $tournament,
                'status' => 'withdrawn',
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('stats.withdrawn', 1)
            );
    });
});
