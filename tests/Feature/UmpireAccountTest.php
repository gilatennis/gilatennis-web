<?php

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\TennisMatchEvent;
use App\Models\Tournament;
use App\Models\User;

function makeUmpire(): User
{
    return User::factory()->withRole(User::ROLE_UMPIRE, 'Umpire')->create();
}

function umpireScoringUrl(string $name, TennisMatch $match): string
{
    return route("umpire.matches.{$name}", $match);
}

describe('Umpire console access', function () {
    it('redirects guests to the umpire login', function () {
        $this->get(route('umpire.tournaments.index'))
            ->assertRedirect(route('umpire.login'));
    });

    it('forbids users without the umpire or admin role', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('umpire.tournaments.index'))
            ->assertForbidden();
    });

    it('lets an umpire and an admin in', function () {
        $this->actingAs(makeUmpire())
            ->get(route('umpire.tournaments.index'))
            ->assertOk();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('umpire.tournaments.index'))
            ->assertOk();
    });

    it('lists only scoreable tournaments for the umpire', function () {
        $umpire = makeUmpire();

        Tournament::factory()->create(['status' => TournamentStatus::Open, 'name' => 'Open Meet']);
        Tournament::factory()->create(['status' => TournamentStatus::Draft, 'name' => 'Draft Meet']);
        Tournament::factory()->create(['status' => TournamentStatus::Canceled, 'name' => 'Canceled Meet']);

        $this->actingAs($umpire)
            ->get(route('umpire.tournaments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Umpire/Tournaments')
                ->has('tournaments.data', 1)
                ->where('tournaments.data.0.name', 'Open Meet'));
    });

    it('404s a draft tournament the umpire opens directly', function () {
        $umpire = makeUmpire();
        $draft = Tournament::factory()->create(['status' => TournamentStatus::Draft]);

        $this->actingAs($umpire)
            ->get(route('umpire.tournaments.show', $draft))
            ->assertNotFound();
    });
});

describe('Umpire console match list', function () {
    it('separates matches on court from the rest and groups by division', function () {
        $umpire = makeUmpire();
        $tournament = Tournament::factory()->create();

        $division = Division::factory()->create(['tournament_id' => $tournament->id]);

        $running = TennisMatch::factory()->running()->create([
            'division_id' => $division->id,
            'side_a_name' => 'On Court A',
            'side_b_name' => 'On Court B',
        ]);

        $pending = TennisMatch::factory()->create([
            'division_id' => $division->id,
            'side_a_name' => 'Waiting A',
            'side_b_name' => 'Waiting B',
        ]);

        $this->actingAs($umpire)
            ->get(route('umpire.tournaments.show', $tournament))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Umpire/Tournament')
                ->where('tournament.id', $tournament->id)
                ->has('onCourt', 1)
                ->where('onCourt.0.id', $running->id)
                ->has('divisions', 1)
                ->has('divisions.0.matches', 1)
                ->where('divisions.0.matches.0.id', $pending->id));

        // On-court matches are never repeated in the division lists, and
        // canceled matches never reach the umpire at all.
        TennisMatch::factory()->canceled()->create(['division_id' => $division->id]);

        $this->actingAs($umpire)
            ->get(route('umpire.tournaments.show', $tournament))
            ->assertInertia(fn ($page) => $page
                ->has('onCourt', 1)
                ->has('divisions.0.matches', 1));
    });
});

describe('Umpire console score page access', function () {
    it('refuses the score page for a match that is not on court yet', function () {
        $umpire = makeUmpire();
        $match = TennisMatch::factory()->create();

        $this->actingAs($umpire)
            ->get(route('umpire.matches.show', $match))
            ->assertForbidden();

        $match->update(['status' => MatchStatus::Running]);

        $this->actingAs($umpire)
            ->get(route('umpire.matches.show', $match))
            ->assertOk();
    });
});

describe('Umpire console scoring', function () {
    it('scores through the authenticated endpoints and attributes the events', function () {
        $umpire = makeUmpire();
        $division = Division::factory()->create();
        $match = TennisMatch::factory()->create(['division_id' => $division->id]);

        $this->actingAs($umpire)
            ->postJson(umpireScoringUrl('server', $match), ['side' => 1])
            ->assertOk()
            ->assertJsonPath('match.status', 'running');

        $this->actingAs($umpire)
            ->postJson(umpireScoringUrl('points', $match), ['side' => 1])
            ->assertOk();

        $match->refresh();

        expect($match->status)->toBe(MatchStatus::Running)
            ->and($match->events()->count())->toBe(2)
            ->and(TennisMatchEvent::where('type', 'point')->firstOrFail()->created_by)->toBe($umpire->id);
    });

    it('rejects scoring by a user without the umpire role', function () {
        $user = User::factory()->create();
        $match = TennisMatch::factory()->create();

        $this->actingAs($user)
            ->postJson(umpireScoringUrl('server', $match), ['side' => 1])
            ->assertForbidden();

        expect($match->events()->count())->toBe(0);
    });
});

describe('Umpire login', function () {
    it('signs an umpire in and lands on the console', function () {
        $umpire = makeUmpire();

        $this->post(route('umpire.login.store'), [
            'email' => $umpire->email,
            'password' => 'password',
        ])->assertRedirect('/umpire');

        $this->assertAuthenticatedAs($umpire);
    });

    it('rejects a user without the umpire role', function () {
        $user = User::factory()->create();

        $this->post(route('umpire.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/umpire/login');

        $this->assertGuest();
    });
});
