<?php

use App\Models\Division;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
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
});
