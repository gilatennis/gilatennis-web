<?php

use App\Enums\MatchStatus;
use App\Enums\PaymentEventSource;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\Division;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\BracketService;
use App\Services\TournamentPaymentService;

/**
 * Exercises the full group-stage pipeline in one sitting: paid registration,
 * group generation, playing every matchday through the slot/match endpoints,
 * live standings, picking the advancing winners, promotion into the knockout,
 * and crowning a champion.
 *
 * Draw size 4, two groups of two, one winner advancing per group: two group
 * ties feed a two-entrant final, and the final produces the champion.
 */
function groupE2eAdmin(): User
{
    $admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $admin->roles()->syncWithoutDetaching([$role->id]);

    return $admin;
}

/**
 * @return array{tournament: Tournament, division: Division}
 */
function groupE2eTournament(): array
{
    $tournament = Tournament::factory()->create(['status' => 'open']);

    $division = Division::factory()->forTournament($tournament)->singles()->create([
        'draw_size' => 4,
        'fee' => 50000,
        'sort_order' => 0,
    ]);

    return ['tournament' => $tournament, 'division' => $division];
}

function groupE2eRegister(Tournament $tournament, Division $division, string $name): TournamentRegistration
{
    $email = strtolower($name).'@example.com';

    test()->post(route('tournaments.register.store', $tournament), [
        'division_id' => $division->id,
        'name' => $name,
        'email' => $email,
        'phone' => '+62 812 000 0000',
        'date_of_birth' => '1990-01-01',
        'waiver_accepted' => true,
        'players' => [
            [
                'name' => $name,
                'email' => $email,
                'phone' => '+62 812 000 0000',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'rating_type' => 'UTR',
                'rating_value' => '8.0',
            ],
        ],
    ])->assertRedirect();

    return TournamentRegistration::where('email', $email)->firstOrFail();
}

/**
 * Play a group tie through the shared slot endpoints and advance the winner.
 */
function groupE2ePlayTie(User $admin, Division $division, int $slotId, int $winningSide): void
{
    $args = [
        'tournament' => $division->tournament_id,
        'division' => $division->id,
        'slot' => $slotId,
    ];

    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.slots.match.store', $args))
        ->assertRedirect();

    $match = TennisMatch::where('bracket_slot_id', $slotId)->latest('id')->firstOrFail();

    test()->actingAs($admin)->patch(route('admin.tennis-matches.update', $match), [
        'sets_to_win' => 1,
        'games_to_win' => 4,
        'extend_at_tie' => false,
    ])->assertRedirect();

    test()->actingAs($admin)
        ->postJson(route('admin.tennis-matches.server', $match), ['side' => $winningSide])
        ->assertOk();

    for ($i = 0; $i < 16; $i++) {
        test()->actingAs($admin)
            ->postJson(route('admin.tennis-matches.points', $match), ['side' => $winningSide])
            ->assertOk();
    }

    expect($match->refresh()->state->win)->toBe($winningSide);

    test()->actingAs($admin)->patch(route('admin.tennis-matches.update', $match), [
        'status' => 'completed',
    ])->assertRedirect();

    $slot = $match->bracketSlot()->firstOrFail();

    $winner = $winningSide === 1 ? $slot->entrant_a_id : $slot->entrant_b_id;

    test()->actingAs($admin)->patch(route('admin.tournaments.divisions.bracket.slots.update', $args), [
        'winner_entrant_id' => $winner,
    ])->assertRedirect();
}

describe('Group stage lifecycle end to end', function () {
    beforeEach(function () {
        $this->admin = groupE2eAdmin();

        ['tournament' => $this->tournament, 'division' => $this->division] = groupE2eTournament();
    });

    it('runs from registration through the groups and a promoted champion', function () {
        // --- 1. Register and settle four entrants ---------------------------
        $names = ['Ana', 'Budi', 'Citra', 'Dewi'];

        $registrations = collect($names)
            ->map(fn (string $name) => groupE2eRegister($this->tournament, $this->division, $name));

        $settle = app(TournamentPaymentService::class);

        foreach ($registrations as $registration) {
            $settle->markPaid($registration, $registration->payment_reference, PaymentEventSource::System);
        }

        expect($this->division->orderedPaidRegistrations()->count())->toBe(4);

        // --- 2. Generate two groups of two ---------------------------------
        $args = [
            'tournament' => $this->tournament->id,
            'division' => $this->division->id,
        ];

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.store', $args), [
            'group_count' => 2,
            'advance_counts' => [1, 1],
            'double_round_robin' => false,
            'seeding_mode' => 'auto',
        ])->assertRedirect();

        $bracket = Bracket::where('division_id', $this->division->id)
            ->where('type', Bracket::TYPE_GROUP_STAGE)
            ->firstOrFail();

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->groups->every(fn (BracketGroup $g) => $g->slots()->count() === 1))->toBeTrue();

        $service = app(BracketService::class);

        // --- 3. Play every group tie; group winners advance ----------------
        foreach ($bracket->groups as $group) {
            $slot = $group->slots()->firstOrFail();

            groupE2ePlayTie($this->admin, $this->division, $slot->id, winningSide: 1);
        }

        $bracket->refresh();

        // Standings reflect the decided ties: one win per group winner.
        foreach ($bracket->groups as $group) {
            $standings = $service->standings($group);

            expect($standings->first()['wins'])->toBe(1)
                ->and($standings->first()['rank'])->toBe(1)
                ->and($standings->last()['wins'])->toBe(0);
        }

        // --- 4. Pick the advancing winner in each group --------------------
        $seedOrder = [];

        foreach ($bracket->groups as $group) {
            $winnerId = $service->standings($group)->first()['entrant_id'];

            $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.winners.store', [
                ...$args,
                'group' => $group->id,
            ]), ['winner_ids' => [$winnerId]])->assertRedirect();

            $seedOrder[] = $winnerId;
        }

        expect($seedOrder)->toHaveCount(2);

        // --- 5. Promote into the knockout ----------------------------------
        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.promote', $args), [
            'winner_ids' => $seedOrder,
            'seed_pairs_apart' => true,
        ])->assertRedirect();

        $knockout = Bracket::where('division_id', $this->division->id)
            ->where('type', Bracket::TYPE_SINGLE_ELIMINATION)
            ->firstOrFail();

        expect(Bracket::where('division_id', $this->division->id)->count())->toBe(2)
            ->and($knockout->size)->toBe(2)
            ->and($knockout->slots()->count())->toBe(1);

        $final = $knockout->finalSlot();

        expect($final->isReady())->toBeTrue()
            ->and(collect([$final->entrant_a_id, $final->entrant_b_id])->sort()->values()->all())
            ->toBe(collect($seedOrder)->sort()->values()->all());

        // --- 6. Play the final and crown the champion ----------------------
        groupE2ePlayTie($this->admin, $this->division, $final->id, winningSide: 2);

        $champion = $final->entrant_b_id;

        $knockout->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($champion)
            ->and($knockout->status)->toBe(Bracket::STATUS_COMPLETED)
            ->and(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(3);
    });
});
