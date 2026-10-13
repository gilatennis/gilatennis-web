<?php

use App\Models\Division;
use App\Models\PaymentEvent;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

describe('Admin Registration Management', function () {
    it('admin can list registrations for a tournament', function () {
        $tournament = Tournament::factory()->create();
        TournamentRegistration::factory()->count(5)->create([
            'tournament_id' => $tournament->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', $tournament))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Registrations')
                ->has('registrations.data', 5)
            );
    });

    it('admin can filter registrations by payment status', function () {
        $tournament = Tournament::factory()->create();
        TournamentRegistration::factory()->count(3)->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'pending',
        ]);
        TournamentRegistration::factory()->count(2)->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', [
                'tournament' => $tournament,
                'status' => 'paid',
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 2)
            );
    });

    it('admin can filter registrations by division', function () {
        $tournament = Tournament::factory()->create();
        $division1 = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Mens Singles',
            'slug' => 'mens-singles',
            'draw_size' => 32,
            'fee' => 45000,
        ]);
        $division2 = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Womens Singles',
            'slug' => 'womens-singles',
            'draw_size' => 32,
            'fee' => 45000,
        ]);

        TournamentRegistration::factory()->count(3)->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division1->id,
        ]);
        TournamentRegistration::factory()->count(2)->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division2->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', [
                'tournament' => $tournament,
                'division_id' => $division1->id,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 3)
            );
    });

    it('admin can search registrations by name', function () {
        $tournament = Tournament::factory()->create();
        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', [
                'tournament' => $tournament,
                'search' => 'John',
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
            );
    });

    it('admin can mark registration as paid', function () {
        $tournament = Tournament::factory()->create();
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'pending',
            'amount_cents' => 45000,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.payment', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]), ['status' => 'paid'])
            ->assertRedirect();

        $registration->refresh();
        expect($registration->payment_status)->toBe('paid');
        expect($registration->paid_at)->not->toBeNull();
    });

    it('filters registrations that are stuck in an unsettled state', function () {
        $tournament = Tournament::factory()->create();

        $stuck = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'processing',
        ]);
        $stuck->forceFill(['created_at' => now()->subMinutes(45)])->save();

        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'processing',
        ])->forceFill(['created_at' => now()->subMinutes(5)])->save();

        TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $tournament->id,
        ])->forceFill(['created_at' => now()->subMinutes(45)])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.index', [
                'tournament' => $tournament,
                'stuck' => 1,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.id', $stuck->id)
                ->where('stats.stuck', 1)
                ->where('filters.stuck', true)
            );
    });

    it('records an admin audit event when overriding payment status', function () {
        $tournament = Tournament::factory()->create();
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'processing',
            'amount_cents' => 45000,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.payment', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]), ['status' => 'paid'])
            ->assertRedirect();

        expect(PaymentEvent::where([
            'tournament_registration_id' => $registration->id,
            'source' => 'admin',
            'status_before' => 'processing',
            'status_after' => 'paid',
        ])->exists())->toBeTrue();
    });

    it('admin can mark registration as refunded', function () {
        $tournament = Tournament::factory()->create();
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'payment_status' => 'paid',
            'amount_cents' => 45000,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.payment', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]), ['status' => 'refunded'])
            ->assertRedirect();

        $registration->refresh();
        expect($registration->payment_status)->toBe('refunded');
    });

    it('rejects a payment override for a registration from another tournament', function () {
        $tournamentA = Tournament::factory()->create();
        $tournamentB = Tournament::factory()->create();
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournamentB->id,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.payment', [
                'tournament' => $tournamentA,
                'registration' => $registration,
            ]), ['status' => 'paid'])
            ->assertNotFound();

        expect($registration->fresh()->payment_status)->toBe('pending');
    });

    it('admin can view a registration detail', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create(['tournament_id' => $tournament->id]);
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.registrations.show', [
                'tournament' => $tournament,
                'division' => $division,
                'registration' => $registration,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Registrations/Show')
                ->where('registration.id', $registration->id)
            );
    });
});
