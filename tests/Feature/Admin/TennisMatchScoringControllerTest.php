<?php

use App\Enums\MatchStatus;
use App\Enums\TennisMatchEventType;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\TennisMatchEvent;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

function scoreUrl(string $name, TennisMatch $match): string
{
    return route("admin.tennis-matches.{$name}", $match);
}

function winFast4Match(TennisMatch $match): void
{
    test()->actingAs(test()->admin)
        ->postJson(scoreUrl('server', $match), ['side' => 1])
        ->assertOk();

    for ($i = 0; $i < 16; $i++) {
        test()->actingAs(test()->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 1])
            ->assertOk();
    }
}

describe('Admin TennisMatch scoring', function () {
    it('records the first server and starts the match', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('server', $match), ['side' => 2])
            ->assertOk()
            ->assertJsonPath('match.first_server', 2)
            ->assertJsonPath('match.status', 'running')
            ->assertJsonPath('match.event_count', 1);

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->started_at)->not->toBeNull()
            ->and($match->state->first_server)->toBe(2)
            ->and($match->events()->count())->toBe(1);
    });

    it('projects points into games and sets', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)->postJson(scoreUrl('server', $match), ['side' => 1]);

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->admin)
                ->postJson(scoreUrl('points', $match), ['side' => 1])
                ->assertOk();
        }

        $match->refresh();

        expect($match->state->set_a)->toBe(1)
            ->and($match->state->game_a)->toBe(0)
            ->and($match->events()->where('type', TennisMatchEventType::Point->value)->count())->toBe(4);
    });

    it('stores the shot tag on a point', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 1, 'shot' => 'Ace'])
            ->assertOk();

        $event = TennisMatchEvent::firstOrFail();

        expect($event->type)->toBe(TennisMatchEventType::Point)
            ->and($event->side)->toBe(1)
            ->and($event->shot)->toBe('Ace');
    });

    it('records a double fault as a point to the receiver', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 2, 'shot' => 'Double fault'])
            ->assertOk();

        $match->refresh();

        expect($match->state->game_b)->toBe(1)
            ->and(TennisMatchEvent::firstOrFail()->shot)->toBe('Double fault');
    });

    it('keeps the match running once a side wins the required sets', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 1,
            'games_to_win' => 4,
            'extend_at_tie' => false,
        ]);

        winFast4Match($match);

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->ended_at)->toBeNull()
            ->and($match->state->win)->toBe(1)
            ->and($match->state->sets)->toBe([['a' => 4, 'b' => 0]]);
    });

    it('completes the match when the admin marks it completed', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 1,
            'games_to_win' => 4,
            'extend_at_tie' => false,
        ]);

        winFast4Match($match);

        $this->actingAs($this->admin)
            ->patch(route('admin.tennis-matches.update', $match), ['status' => 'completed'])
            ->assertRedirect();

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Completed)
            ->and($match->ended_at)->not->toBeNull()
            ->and($match->state->win)->toBe(1);
    });

    it('refuses to score a completed match until it is reopened', function () {
        $match = TennisMatch::factory()->create([
            'status' => MatchStatus::Completed,
            'ended_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score'])
            ->assertJsonPath('errors.score.0', 'The match is completed. Reopen it before changing the score.');

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('server', $match), ['side' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('undo', $match))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);

        expect($match->events()->count())->toBe(0);
    });

    it('refuses to score once a side has won but the match is not completed', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 1,
            'games_to_win' => 4,
            'extend_at_tie' => false,
        ]);

        winFast4Match($match);

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score'])
            ->assertJsonPath('errors.score.0', 'The match has already been decided. Undo the last point to change the score.');

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('server', $match), ['side' => 2])
            ->assertStatus(422);

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->state->win)->toBe(1)
            ->and($match->events()->count())->toBe(17);
    });

    it('completes a match with no winner when nothing has been scored', function () {
        $match = TennisMatch::factory()->running()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tennis-matches.update', $match), ['status' => 'completed'])
            ->assertRedirect();

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Completed)
            ->and($match->state->win)->toBeNull()
            ->and($match->ended_at)->not->toBeNull();
    });

    it('undoes the last event and reverts the winner', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 1,
            'games_to_win' => 4,
            'extend_at_tie' => false,
        ]);

        winFast4Match($match);
        expect($match->fresh()->status)->toBe(MatchStatus::Running);

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('undo', $match))
            ->assertOk()
            ->assertJsonPath('match.status', 'running')
            ->assertJsonPath('match.win', null);

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->state->win)->toBeNull();
    });

    it('undoes the server pick back to pending', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)->postJson(scoreUrl('server', $match), ['side' => 1]);
        $this->actingAs($this->admin)->postJson(scoreUrl('undo', $match))->assertOk();

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Pending)
            ->and($match->state->first_server)->toBeNull()
            ->and($match->events()->count())->toBe(0);
    });

    it('sends a pending match to court and can recall it', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('status', $match), ['status' => 'running'])
            ->assertOk()
            ->assertJsonPath('match.status', 'running');

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->started_at)->not->toBeNull();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('status', $match), ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('match.status', 'pending');

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Pending)
            ->and($match->started_at)->toBeNull();
    });

    it('rejects a status outside the scorekeeper set', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('status', $match), ['status' => 'canceled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        expect($match->fresh()->status)->toBe(MatchStatus::Pending);
    });

    it('validates the side and shot', function () {
        $match = TennisMatch::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(scoreUrl('points', $match), ['side' => 3, 'shot' => 'Bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['side', 'shot']);
    });

    it('forbids non-admin users', function () {
        $user = User::factory()->create();
        $match = TennisMatch::factory()->create();

        $this->actingAs($user)
            ->postJson(scoreUrl('points', $match), ['side' => 1])
            ->assertStatus(403);

        expect($match->events()->count())->toBe(0);
    });
});
