<?php

use App\Models\Division;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentPrize;
use App\Models\TournamentRegistration;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

describe('Admin Division Detail', function () {
    it('admin can view division detail with registrants', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Mens Singles 3.5',
            'slug' => 'mens-singles-3-5',
            'draw_size' => 32,
            'fee' => 45000,
        ]);
        TournamentRegistration::factory()->count(3)->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $tournament,
                'division' => $division,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Division')
                ->has('registrations.data', 3)
                ->has('stats')
            );
    });

    it('division detail shows prizes when configured', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Doubles Championship',
            'slug' => 'doubles-championship',
            'draw_size' => 16,
            'fee' => 60000,
        ]);
        TournamentPrize::create([
            'division_id' => $division->id,
            'tournament_id' => $tournament->id,
            'place' => 'Champion',
            'value' => 'Trophy + Rp 1,000,000',
            'sort_order' => 0,
        ]);
        TournamentPrize::create([
            'division_id' => $division->id,
            'tournament_id' => $tournament->id,
            'place' => 'Finalist',
            'value' => 'Medal',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $tournament,
                'division' => $division,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('division.prizes', 2)
            );
    });

    it('division detail shows empty prizes when none', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Singles Open',
            'slug' => 'singles-open',
            'draw_size' => 32,
            'fee' => 45000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $tournament,
                'division' => $division,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('division.prizes', 0)
            );
    });

    it('returns 404 when division does not belong to tournament', function () {
        $tournament1 = Tournament::factory()->create();
        $tournament2 = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament1->id,
            'name' => 'Wrong Tourney',
            'slug' => 'wrong-tourney',
            'draw_size' => 32,
            'fee' => 45000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $tournament2,
                'division' => $division,
            ]))
            ->assertStatus(404);
    });

    it('search filters registrations by team name', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Mens Singles 3.5',
            'slug' => 'mens-singles-3-5',
            'draw_size' => 32,
            'fee' => 45000,
        ]);
        $matchingTeam = Team::factory()->create(['name' => 'Bojana Eagles']);
        $otherTeam = Team::factory()->create(['name' => 'Schulzport Foxes']);

        $matching = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => 'Alice Smith',
            'team_id' => $matchingTeam->id,
        ]);
        TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => 'Bob Jones',
            'team_id' => $otherTeam->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $tournament,
                'division' => $division,
                'search' => 'Bojana',
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.id', $matching->id)
            );
    });
});
