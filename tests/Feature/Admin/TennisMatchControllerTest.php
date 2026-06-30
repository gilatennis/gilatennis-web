<?php

use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

describe('Admin TennisMatch CRUD', function () {
    it('admin can list matches (empty)', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TennisMatches/Index')
                ->has('matches.data', 0)
                ->where('stats.total', 0)
                ->where('stats.pending', 0)
                ->where('stats.running', 0)
                ->where('stats.completed', 0)
            );
    });

    it('admin can list matches with paginated data', function () {
        TennisMatch::factory()->count(3)->create();
        TennisMatch::factory()->running()->create();
        TennisMatch::factory()->completed()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('matches.data', 5)
                ->where('stats.total', 5)
                ->where('stats.pending', 3)
                ->where('stats.running', 1)
                ->where('stats.completed', 1)
            );
    });

    it('admin can view create form', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.create'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TennisMatches/Create')
            );
    });

    it('admin can create match with valid data', function () {
        $data = [
            'side_a_name' => 'Roger Federer',
            'side_b_name' => 'Rafael Nadal',
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), $data)
            ->assertRedirect();

        $match = TennisMatch::where('side_a_name', 'Roger Federer')->first();
        expect($match)->not->toBeNull();
        expect($match->status)->toBe('pending');
        expect($match->started_at)->toBeNull();
        expect($match->ended_at)->toBeNull();
        expect($match->side_b_name)->toBe('Rafael Nadal');
        expect($match->sets_to_win)->toBe(2);
    });

    it('redirects to show page after creating match', function () {
        $data = [
            'side_a_name' => 'Player A',
            'side_b_name' => 'Player B',
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), $data);

        $match = TennisMatch::where('side_a_name', 'Player A')->first();
        expect($match)->not->toBeNull();

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString(
            (string) $match->id,
            urldecode($location)
        );
    });

    it('validates missing side_a_name', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => '',
                'side_b_name' => 'Rafael Nadal',
                'sets_to_win' => 2,
                'games_to_win' => 6,
                'extend_at_tie' => false,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('side_a_name');
    });

    it('requires tiebreak_to always', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => 'Player A',
                'side_b_name' => 'Player B',
                'sets_to_win' => 2,
                'games_to_win' => 6,
                'extend_at_tie' => false,
                'tiebreak_to' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('tiebreak_to');
    });

    it('requires tiebreak_to even when extend_at_tie is true', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => 'Player A',
                'side_b_name' => 'Player B',
                'sets_to_win' => 2,
                'games_to_win' => 4,
                'extend_at_tie' => true,
                'tiebreak_to' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('tiebreak_to');
    });

    it('validates sets_to_win range', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => 'Player A',
                'side_b_name' => 'Player B',
                'sets_to_win' => 6,
                'games_to_win' => 6,
                'extend_at_tie' => false,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sets_to_win');
    });

    it('validates games_to_win range', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => 'Player A',
                'side_b_name' => 'Player B',
                'sets_to_win' => 2,
                'games_to_win' => 0,
                'extend_at_tie' => false,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('games_to_win');
    });

    it('admin can view show page', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TennisMatches/Score')
                ->where('match.id', $match->id)
                ->where('match.format_label', $match->format_label)
            );
    });

    it('show page payload includes format_label and sides', function () {
        $match = TennisMatch::factory()->create([
            'side_a_name' => 'Alcaraz',
            'side_b_name' => 'Sinner',
            'sets_to_win' => 2,
            'games_to_win' => 6,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('match.side_a_name', 'Alcaraz')
                ->where('match.side_b_name', 'Sinner')
                ->where('match.format_label', 'Best of 3, 6 games, TB to 7')
            );
    });

    it('non-admin cannot access admin match routes', function () {
        $user = User::factory()->create();
        $match = TennisMatch::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.tennis-matches.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.tennis-matches.create'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(403);

        $this->actingAs($user)
            ->post(route('admin.tennis-matches.store'), [])
            ->assertStatus(403);
    });

    it('guest is redirected to admin login', function () {
        $this->get(route('admin.tennis-matches.index'))
            ->assertRedirect('/admin/login');

        $this->get(route('admin.tennis-matches.create'))
            ->assertRedirect('/admin/login');
    });
});
