<?php

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\TennisMatchState;
use App\Models\Tournament;
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
        expect($match->status)->toBe(MatchStatus::Pending);
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

    it('validates games_to_win range', function (int $gamesToWin) {
        $this->actingAs($this->admin)
            ->post(route('admin.tennis-matches.store'), [
                'side_a_name' => 'Player A',
                'side_b_name' => 'Player B',
                'sets_to_win' => 2,
                'games_to_win' => $gamesToWin,
                'extend_at_tie' => false,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('games_to_win');
    })->with([0, 1]);

    it('admin can view show page', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TennisMatches/Score')
                ->where('match.id', $match->id)
                ->where('match.format_label', $match->format_label)
                ->where('match.server', 1)
                ->where('match.first_server', null)
                ->where('match.set_a', 0)
                ->where('match.set_b', 0)
                ->where('match.win', null)
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
                ->where('match.format_label', 'Best of 3 set(s), 6 games, TB to 7')
            );
    });

    it('exposes the bracket url for a bracket match', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->forTournament($tournament)->create();
        $bracket = Bracket::factory()->forDivision($division)->create();
        $slot = BracketSlot::factory()->forBracket($bracket)->create();
        $match = TennisMatch::factory()->create([
            'division_id' => $division->id,
            'bracket_slot_id' => $slot->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('bracketUrl', route(
                'admin.tournaments.divisions.show',
                [
                    'tournament' => $tournament->id,
                    'division' => $division->id,
                    'tab' => 'brackets',
                ],
            )));
    });

    it('omits the bracket url for a standalone match', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('bracketUrl', null));
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

describe('Admin TennisMatch status transitions', function () {
    it('transitions pending to running and stamps started_at', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Running);
        expect($match->started_at)->not->toBeNull();
        expect($match->ended_at)->toBeNull();
    });

    it('transitions running to completed and stamps ended_at', function () {
        $match = TennisMatch::factory()->running()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'completed'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Completed);
        expect($match->ended_at)->not->toBeNull();
    });

    it('rejects invalid transition from pending to completed', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Pending);
    });

    it('allows reverting a completed match back to running', function () {
        $match = TennisMatch::factory()->completed()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Running);
    });

    it('clears ended_at when reopening a completed match', function () {
        $match = TennisMatch::factory()->completed()->create();

        expect($match->ended_at)->not->toBeNull();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->ended_at)->toBeNull()
            ->and($match->started_at)->not->toBeNull();
    });

    it('preserves started_at if already set', function () {
        $existing = now()->subHour();
        $match = TennisMatch::factory()->create([
            'status' => 'running',
            'started_at' => $existing,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'completed'])
            ->assertRedirect();

        $match->refresh();
        expect($match->started_at->toIso8601String())->toBe($existing->toIso8601String());
        expect($match->ended_at)->not->toBeNull();
    });

    it('validates status enum', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'bogus'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    });

    it('non-admin cannot transition match status', function () {
        $user = User::factory()->create();
        $match = TennisMatch::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertStatus(403);

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Pending);
    });

    it('allows running to pending and clears timestamps', function () {
        $match = TennisMatch::factory()->running()->create([
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'pending'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Pending);
        expect($match->started_at)->toBeNull();
        expect($match->ended_at)->toBeNull();
    });

    it('allows running to canceled', function () {
        $match = TennisMatch::factory()->running()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'canceled'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Canceled);
    });

    it('allows pending to canceled', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'canceled'])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Canceled);
    });
});

describe('Admin TennisMatch state persistence', function () {
    it('creating a match auto-creates its state row via model event', function () {
        $match = TennisMatch::factory()->create();

        expect($match->state()->exists())->toBeTrue();
        $row = $match->state()->first();
        expect($row->server)->toBe(1);
        expect($row->first_server)->toBeNull();
        expect($row->set_a)->toBe(0);
        expect($row->win)->toBeNull();
    });

    it('show page does not lazy-create a missing state row', function () {
        $match = TennisMatch::factory()->create();
        $stateId = $match->state->id;
        $match->state->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200);

        expect(TennisMatchState::find($stateId))->toBeNull();
    });

    it('show page reflects existing state row', function () {
        $match = TennisMatch::factory()->create();
        $match->state->update([
            'server' => 2, 'first_server' => 2,
            'set_a' => 1, 'set_b' => 0, 'game_a' => 3, 'game_b' => 2,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tennis-matches.show', $match))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('match.server', 2)
                ->where('match.first_server', 2)
                ->where('match.set_a', 1)
                ->where('match.set_b', 0)
                ->where('match.game_a', 3)
                ->where('match.game_b', 2)
            );
    });

    it('ignores client-supplied score state on update', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), [
                'status' => 'running',
                'state' => [
                    'server' => 1,
                    'first_server' => 1,
                    'set_a' => 1, 'set_b' => 0,
                    'game_a' => 2, 'game_b' => 1,
                    'tiebreak' => false, 'tb_a' => 0, 'tb_b' => 0, 'tb_first' => null,
                    'sets' => [['a' => 6, 'b' => 4]],
                    'win' => null,
                ],
            ])
            ->assertRedirect();

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->state->set_a)->toBe(0)
            ->and($match->state->game_a)->toBe(0)
            ->and($match->state->sets)->toBe([]);
    });

    it('treats a state-only payload as a no-op', function () {
        $match = TennisMatch::factory()->running()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), [
                'state' => ['server' => 2, 'first_server' => 2],
            ])
            ->assertRedirect();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->state->server)->toBe(1);
    });

    it('re-sending the same status is a no-op (not a transition error)', function () {
        $match = TennisMatch::factory()->running()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $match->refresh();
        expect($match->status)->toBe(MatchStatus::Running);
    });

    it('deleting a match cascades to the state row', function () {
        $match = TennisMatch::factory()->create();
        $stateId = $match->state->id;

        $match->delete();

        expect(TennisMatchState::find($stateId))->toBeNull();
    });
});

describe('Admin TennisMatch rubber edits', function () {
    it('updates the sides and format and redirects back without a score payload', function () {
        $match = TennisMatch::factory()->create([
            'side_a_name' => 'Old A',
            'side_b_name' => 'Old B',
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tennis-matches.update', $match), [
                'side_a_name' => 'Ana / Bea',
                'side_b_name' => 'Cara / Dana',
                'sets_to_win' => 1,
                'games_to_win' => 6,
                'extend_at_tie' => true,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect();

        $match->refresh();

        expect($match->side_a_name)->toBe('Ana / Bea')
            ->and($match->side_b_name)->toBe('Cara / Dana')
            ->and($match->sets_to_win)->toBe(1)
            ->and($match->games_to_win)->toBe(6)
            ->and($match->extend_at_tie)->toBeTrue()
            ->and($match->tiebreak_to)->toBe(7)
            ->and($match->format_label)->toBe('Best of 1 set(s), 6 games, TB to 7');
    });

    it('leaves the scoring state untouched when only rubber fields change', function () {
        $match = TennisMatch::factory()->running()->create();
        $match->state->update(['set_a' => 1, 'set_b' => 0, 'game_a' => 3, 'game_b' => 2]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tennis-matches.update', $match), [
                'side_a_name' => 'Renamed A',
            ])
            ->assertRedirect();

        $match->refresh();

        expect($match->side_a_name)->toBe('Renamed A')
            ->and($match->status)->toBe(MatchStatus::Running)
            ->and($match->state->set_a)->toBe(1)
            ->and($match->state->game_a)->toBe(3);
    });

    it('validates rubber field ranges on update', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tennis-matches.update', $match), [
                'side_a_name' => str_repeat('a', 61),
                'sets_to_win' => 4,
                'tiebreak_to' => 30,
            ])
            ->assertSessionHasErrors(['side_a_name', 'sets_to_win', 'tiebreak_to']);
    });
});
