<?php

use App\Enums\MatchStatus;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

function umpireScoreUrl(string $name, TennisMatch $match): string
{
    return route("umpire.score.{$name}", ['match' => $match->share_token]);
}

/**
 * Stand up a knockout division with a started match on the first slot, the way
 * the Rubbers dialog would. Returns [$division, $slot, $match].
 *
 * @return array{0: Division, 1: BracketSlot, 2: TennisMatch}
 */
function umpireBracketMatch(): array
{
    $tournament = Tournament::factory()->create();

    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => 'singles',
        'draw_size' => 8,
    ]);

    collect(range(1, 8))->each(fn ($i) => TournamentRegistration::factory()->paid()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'name' => "Entrant {$i}",
    ]));

    test()->actingAs(test()->admin)->post(route('admin.tournaments.divisions.bracket.store', [
        'tournament' => $tournament->id,
        'division' => $division->id,
    ]), ['seeding_mode' => 'auto']);

    $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
    $slot = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

    test()->actingAs(test()->admin)->post(route('admin.tournaments.divisions.bracket.slots.match.store', [
        'tournament' => $tournament->id,
        'division' => $division->id,
        'slot' => $slot->id,
    ]));

    return [$division, $slot, TennisMatch::firstOrFail()];
}

describe('Umpire share links', function () {
    it('generates an unguessable token that never serializes', function () {
        $match = TennisMatch::factory()->create();

        expect($match->share_token)->not->toBeNull()
            ->and(strlen((string) $match->share_token))->toBe(48)
            ->and($match->toArray())->not->toHaveKey('share_token');
    });

    it('exposes a share url on each rubber in the division payload', function () {
        [$division, $slot, $match] = umpireBracketMatch();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'bracket.rounds.0.slots.0.matches.0.share_url',
                route('umpire.score', ['match' => $match->share_token]),
            ));
    });

    it('renders the scoreboard for a valid token without authentication', function () {
        $match = TennisMatch::factory()->create();

        $this->get(route('umpire.score', ['match' => $match->share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Umpire/Score')
                ->where('match.id', $match->id)
                ->has('endpoints.point')
                ->has('endpoints.server')
                ->has('endpoints.undo')
                ->has('endpoints.status'));
    });

    it('returns 404 for an unknown share token', function () {
        $this->get(route('umpire.score', ['match' => 'not-a-real-token']))
            ->assertNotFound();
    });

    it('records points, server and undo without authentication', function () {
        $match = TennisMatch::factory()->create();

        $this->postJson(umpireScoreUrl('server', $match), ['side' => 1])
            ->assertOk()
            ->assertJsonPath('match.first_server', 1);

        $this->postJson(umpireScoreUrl('points', $match), ['side' => 1])
            ->assertOk()
            ->assertJsonPath('match.event_count', 2);

        $this->postJson(umpireScoreUrl('undo', $match))
            ->assertOk()
            ->assertJsonPath('match.event_count', 1);
    });

    it('completes and reopens a match through the status endpoint', function () {
        $match = TennisMatch::factory()->create();

        $this->postJson(umpireScoreUrl('server', $match), ['side' => 1])->assertOk();

        $this->postJson(umpireScoreUrl('status', $match), ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('match.status', 'completed');

        expect($match->fresh()->ended_at)->not->toBeNull();

        $this->postJson(umpireScoreUrl('status', $match), ['status' => 'running'])
            ->assertOk()
            ->assertJsonPath('match.status', 'running');

        expect($match->fresh()->ended_at)->toBeNull();
    });

    it('rejects a status outside running and completed', function () {
        $match = TennisMatch::factory()->create();

        $this->postJson(umpireScoreUrl('status', $match), ['status' => 'canceled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });

    it('clears the bracket winner when a completed match is reopened', function () {
        [$division, $slot, $match] = umpireBracketMatch();

        $winner = $slot->entrant_a_id;

        // Start the match first: appending the serve event would otherwise clear
        // the winner we set below.
        $this->postJson(umpireScoreUrl('server', $match), ['side' => 1])->assertOk();

        $slot->update(['winner_entrant_id' => $winner]);

        $this->postJson(umpireScoreUrl('status', $match), ['status' => 'completed'])->assertOk();

        expect($slot->fresh()->winner_entrant_id)->toBe($winner);

        $this->postJson(umpireScoreUrl('status', $match), ['status' => 'running'])->assertOk();

        expect($slot->fresh()->winner_entrant_id)->toBeNull();
    });

    it('keeps the admin match update route behind authentication', function () {
        $match = TennisMatch::factory()->create();

        $this->patch(route('admin.tennis-matches.update', $match), ['status' => 'running'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasNoErrors();

        expect($match->fresh()->status)->toBe(MatchStatus::Pending);
    });

    it('surfaces an umpire-started match on the public live board', function () {
        $tournament = Tournament::factory()->create();

        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'participation_category' => 'singles',
            'draw_size' => 8,
        ]);

        $match = TennisMatch::factory()->create([
            'division_id' => $division->id,
            'side_a_name' => 'Umpire Live A',
            'side_b_name' => 'Umpire Live B',
        ]);

        $this->postJson(umpireScoreUrl('server', $match), ['side' => 1])->assertOk();

        $this->get(route('tournaments.live', $tournament))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournaments/Live')
                ->where('liveMatches.0.id', $match->id)
                ->where('liveMatches.0.server', 1));
    });
});
