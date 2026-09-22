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

function bracketTestDivision(int $drawSize = 8, string $category = 'singles'): Division
{
    $tournament = Tournament::factory()->create();

    return Division::factory()->create([
        'tournament_id' => $tournament->id,
        'participation_category' => $category,
        'draw_size' => $drawSize,
    ]);
}

function bracketTestEntrants(Division $division, int $count): array
{
    return collect(range(1, $count))
        ->map(fn ($i) => TournamentRegistration::factory()->paid()->create([
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
            'name' => "Entrant {$i}",
        ]))
        ->all();
}

describe('Admin Bracket Generation', function () {
    it('generates an 8-draw bracket with byes for six paid entrants in auto mode', function () {
        $division = bracketTestDivision(8);
        $entrants = bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['seeding_mode' => 'auto'])
            ->assertRedirect();

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();

        expect($bracket->size)->toBe(8)
            ->and($bracket->type)->toBe(Bracket::TYPE_SINGLE_ELIMINATION)
            ->and($bracket->slots()->count())->toBe(7);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        expect($round1)->toHaveCount(4);

        // Seed order for size 8 is [1,8,4,5,2,7,3,6].
        // Slot 0 (seed 1 v bye) and slot 2 (seed 2 v bye) should be byes that advance.
        expect($round1[0]->bye)->toBeTrue()
            ->and($round1[0]->winner_entrant_id)->toBe($entrants[0]->id)
            ->and($round1[1]->bye)->toBeFalse()
            ->and($round1[2]->bye)->toBeTrue()
            ->and($round1[2]->winner_entrant_id)->toBe($entrants[1]->id)
            ->and($round1[3]->bye)->toBeFalse();

        // Bye winners propagate into the second round.
        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->get();
        expect($round2[0]->entrant_a_id)->toBe($entrants[0]->id)
            ->and($round2[1]->entrant_a_id)->toBe($entrants[1]->id)
            ->and($round2[0]->entrant_b_id)->toBeNull();
    });

    it('generates an empty first round in manual mode', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['seeding_mode' => 'manual'])
            ->assertRedirect();

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $round1 = $bracket->slots()->where('round', 1)->get();

        expect($round1)->toHaveCount(4)
            ->and($round1->every(fn (BracketSlot $s) => $s->entrant_a_id === null && $s->entrant_b_id === null))->toBeTrue();
    });

    it('requires at least two paid entrants', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 1);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.store', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['seeding_mode' => 'auto'])
            ->assertSessionHasErrors('bracket');

        expect(Bracket::where('division_id', $division->id)->exists())->toBeFalse();
    });
});

describe('Admin Bracket Seeding', function () {
    it('seeds a manual bracket from an ordered entrant list', function () {
        $division = bracketTestDivision(8);
        $entrants = bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'manual']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();

        // Reverse order: entrant 6 is seed 1.
        $ordered = array_reverse(array_map(fn ($e) => $e->id, $entrants));

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.seed', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['entrant_ids' => $ordered])
            ->assertRedirect();

        $round1 = $bracket->fresh()->slots()->where('round', 1)->orderBy('position')->get();

        // Seed 1 is the last entrant in the reversed list (entrants[5]).
        expect($round1[0]->entrant_a_id)->toBe($entrants[5]->id)
            ->and($round1[0]->bye)->toBeTrue()
            ->and($round1[0]->winner_entrant_id)->toBe($entrants[5]->id);
    });

    it('rejects a seeding list that does not cover every paid entrant', function () {
        $division = bracketTestDivision(8);
        $entrants = bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'manual']);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.seed', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['entrant_ids' => [$entrants[0]->id, $entrants[1]->id]])
            ->assertSessionHasErrors('entrant_ids');
    });

    it('rejects a seeding list containing duplicates', function () {
        $division = bracketTestDivision(8);
        $entrants = bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'manual']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();

        $duplicated = [$entrants[0]->id, $entrants[0]->id, $entrants[2]->id, $entrants[3]->id, $entrants[4]->id, $entrants[5]->id];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.seed', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]), ['entrant_ids' => $duplicated])
            ->assertSessionHasErrors('entrant_ids.1');

        $round1 = $bracket->fresh()->slots()->where('round', 1)->get();

        expect($round1)->toHaveCount(4)
            ->and($round1->every(fn (BracketSlot $s) => $s->entrant_a_id === null && $s->entrant_b_id === null))->toBeTrue();
    });
});

describe('Admin Bracket Progression', function () {
    it('creates a match when a ready slot starts', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 8);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.slots.start', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $slot->id,
            ]))
            ->assertRedirect(route('admin.tennis-matches.show', TennisMatch::firstOrFail()));

        $match = TennisMatch::firstOrFail();

        expect($match->bracket_slot_id)->toBe($slot->id)
            ->and($match->entrant_a_id)->toBe($slot->entrant_a_id)
            ->and($match->status)->toBe(MatchStatus::Pending)
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);
    });

    it('does not auto-advance a slot when a bracket match completes', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 8);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.slots.start', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slot->id,
        ]));

        $match = TennisMatch::firstOrFail();

        $this->actingAs($this->admin)->patch(route('admin.tennis-matches.update', $match), [
            'sets_to_win' => 1,
            'games_to_win' => 4,
            'extend_at_tie' => false,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tennis-matches.server', $match), ['side' => 1]);

        for ($i = 0; $i < 16; $i++) {
            $this->actingAs($this->admin)
                ->postJson(route('admin.tennis-matches.points', $match), ['side' => 1]);
        }

        expect($match->fresh()->status)->toBe(MatchStatus::Running)
            ->and($slot->fresh()->winner_entrant_id)->toBeNull();

        $this->actingAs($this->admin)->patch(route('admin.tennis-matches.update', $match), [
            'status' => 'completed',
        ]);

        expect($match->fresh()->status)->toBe(MatchStatus::Completed)
            ->and($slot->fresh()->winner_entrant_id)->toBeNull();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();
        expect($round2->fresh()->entrant_a_id)->toBeNull();
    });

    it('awards a singles slot manually and propagates the winner', function () {
        $division = bracketTestDivision(4);
        bracketTestEntrants($division, 4);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $semi = $bracket->slots()->where('round', 1)->where('position', 0)->firstOrFail();
        $final = $bracket->slots()->where('round', 2)->firstOrFail();
        $winnerId = $semi->entrant_a_id;

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $semi->id,
            ]), ['winner_entrant_id' => $winnerId])
            ->assertRedirect();

        expect($semi->fresh()->winner_entrant_id)->toBe($winnerId)
            ->and($final->fresh()->entrant_a_id)->toBe($winnerId)
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);
    });

    it('propagates winners through multiple rounds of a larger draw', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 8);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();

        $roundOne = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $semi = $bracket->slots()->where('round', 2)->where('position', 0)->firstOrFail();
        $final = $bracket->slots()->where('round', 3)->firstOrFail();

        $award = function (BracketSlot $slot, int $entrantId) use ($division) {
            $this->actingAs($this->admin)->patch(
                route('admin.tournaments.divisions.bracket.slots.update', [
                    'tournament' => $division->tournament_id,
                    'division' => $division->id,
                    'slot' => $slot->id,
                ]),
                ['winner_entrant_id' => $entrantId],
            );
        };

        $award($roundOne[0], $roundOne[0]->entrant_a_id);
        $award($roundOne[1], $roundOne[1]->entrant_a_id);

        expect($semi->fresh()->entrant_a_id)->toBe($roundOne[0]->entrant_a_id)
            ->and($semi->fresh()->entrant_b_id)->toBe($roundOne[1]->entrant_a_id);

        $award($semi->fresh(), $semi->fresh()->entrant_a_id);

        expect($final->fresh()->entrant_a_id)->toBe($roundOne[0]->entrant_a_id)
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);

        $award($final->fresh(), $final->fresh()->entrant_a_id);

        expect($final->fresh()->winner_entrant_id)->toBe($roundOne[0]->entrant_a_id)
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_COMPLETED);
    });

    it('clears downstream entrants when a winner is reset', function () {
        $division = bracketTestDivision(4);
        bracketTestEntrants($division, 4);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $semi = $bracket->slots()->where('round', 1)->where('position', 0)->firstOrFail();
        $final = $bracket->slots()->where('round', 2)->firstOrFail();

        $updateArgs = fn (int $slotId) => [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slotId,
        ];

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($semi->id)),
            ['winner_entrant_id' => $semi->entrant_a_id],
        );

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($final->id)),
            ['winner_entrant_id' => $final->fresh()->entrant_a_id],
        );

        expect($bracket->fresh()->status)->toBe(Bracket::STATUS_COMPLETED);

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($semi->id)),
            ['winner_entrant_id' => null],
        );

        expect($semi->fresh()->winner_entrant_id)->toBeNull()
            ->and($final->fresh()->entrant_a_id)->toBeNull()
            ->and($final->fresh()->winner_entrant_id)->toBeNull()
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);
    });

    it('awards a slot manually and completes the bracket at the final', function () {
        $division = bracketTestDivision(2);
        $entrants = bracketTestEntrants($division, 2);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $final = $bracket->slots()->where('round', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $final->id,
            ]), ['winner_entrant_id' => $entrants[0]->id])
            ->assertRedirect();

        expect($final->fresh()->winner_entrant_id)->toBe($entrants[0]->id)
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_COMPLETED);
    });

    it('resolves and completes a multi-round bracket at the correct final slot', function () {
        $division = bracketTestDivision(4);
        bracketTestEntrants($division, 4);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();

        expect($bracket->finalSlot()->round)->toBe(2);

        $semi1 = $bracket->slots()->where('round', 1)->where('position', 0)->firstOrFail();
        $semi2 = $bracket->slots()->where('round', 1)->where('position', 1)->firstOrFail();
        $final = $bracket->slots()->where('round', 2)->firstOrFail();

        $updateArgs = fn (int $slotId) => [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slotId,
        ];

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($semi1->id)),
            ['winner_entrant_id' => $semi1->entrant_a_id],
        );

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($semi2->id)),
            ['winner_entrant_id' => $semi2->entrant_a_id],
        );

        expect($final->fresh()->entrant_a_id)->not->toBeNull()
            ->and($final->fresh()->entrant_b_id)->not->toBeNull()
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);

        $this->actingAs($this->admin)->patch(
            route('admin.tournaments.divisions.bracket.slots.update', $updateArgs($final->id)),
            ['winner_entrant_id' => $final->fresh()->entrant_a_id],
        );

        expect($final->fresh()->winner_entrant_id)->not->toBeNull()
            ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_COMPLETED);
    });

    it('rejects manually awarding an entrant from another division', function () {
        $division = bracketTestDivision(2);
        $other = bracketTestDivision(2);

        bracketTestEntrants($division, 2);
        $foreign = bracketTestEntrants($other, 1);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $final = $bracket->slots()->where('round', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $final->id,
            ]), ['winner_entrant_id' => $foreign[0]->id])
            ->assertNotFound();
    });
});

describe('Admin Bracket Cleanup', function () {
    it('deletes the bracket and its matches', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 8);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.slots.start', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slot->id,
        ]));

        expect(TennisMatch::count())->toBe(1);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.destroy', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertRedirect();

        expect(Bracket::where('division_id', $division->id)->exists())->toBeFalse()
            ->and(BracketSlot::where('bracket_id', $bracket->id)->exists())->toBeFalse()
            ->and(TennisMatch::count())->toBe(0);
    });
});

describe('Admin Division Bracket Payload', function () {
    it('includes the bracket payload on the division page', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 6);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Division')
                ->where('bracket.size', 8)
                ->has('bracket.rounds', 3)
                ->has('bracketEntrants', 6)
            );
    });

    it('returns a null bracket when none exists', function () {
        $division = bracketTestDivision(8);
        bracketTestEntrants($division, 2);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('bracket', null));
    });
});

describe('Admin Team Rubber Formats', function () {
    it('creates a doubles rubber with the supplied format for a team division', function () {
        $division = bracketTestDivision(2, 'team');
        bracketTestEntrants($division, 2);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.slots.start', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $slot->id,
            ]), [
                'side_a_name' => 'Ana / Bea',
                'side_b_name' => 'Cara / Dana',
                'sets_to_win' => 1,
                'games_to_win' => 6,
                'extend_at_tie' => true,
                'tiebreak_to' => 7,
            ])
            ->assertRedirect(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'tab' => 'matches',
            ]));

        $match = TennisMatch::firstOrFail();

        expect($match->side_a_name)->toBe('Ana / Bea')
            ->and($match->side_b_name)->toBe('Cara / Dana')
            ->and($match->sets_to_win)->toBe(1)
            ->and($match->games_to_win)->toBe(6)
            ->and($match->extend_at_tie)->toBeTrue()
            ->and($match->tiebreak_to)->toBe(7)
            ->and($match->bracket_slot_id)->toBe($slot->id)
            ->and($match->format_label)->toBe('Best of 1 set(s), 6 games, TB to 7');
    });

    it('falls back to the built-in singles format when no overrides are supplied', function () {
        $division = bracketTestDivision(2, 'team');
        bracketTestEntrants($division, 2);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.slots.start', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
                'slot' => $slot->id,
            ]), [
                'side_a_name' => 'Ana',
                'side_b_name' => 'Cara',
            ]);

        $match = TennisMatch::firstOrFail();

        expect($match->sets_to_win)->toBe(2)
            ->and($match->games_to_win)->toBe(6)
            ->and($match->extend_at_tie)->toBeFalse()
            ->and($match->tiebreak_to)->toBe(7);
    });
});

describe('Admin Bracket Advancement', function () {
    function bracketAdvanceSetup(int $drawSize = 4): array
    {
        $division = bracketTestDivision($drawSize);
        bracketTestEntrants($division, $drawSize);

        test()->actingAs(test()->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $slot = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

        test()->actingAs(test()->admin)->post(route('admin.tournaments.divisions.bracket.slots.start', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slot->id,
        ]));

        return [$division, $bracket, $slot, TennisMatch::firstOrFail()];
    }

    function advanceArgs(Division $division, int $slotId): array
    {
        return [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slotId,
        ];
    }

    it('sets the winner explicitly with a reason', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_b_id,
                'reason' => 'walkover',
            ])
            ->assertRedirect();

        expect($slot->fresh()->winner_entrant_id)->toBe($slot->entrant_b_id)
            ->and($slot->fresh()->advance_reason)->toBe('walkover')
            ->and($round2->fresh()->entrant_a_id)->toBe($slot->entrant_b_id);
    });

    it('allows setting a winner without a reason', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
            ])
            ->assertRedirect();

        expect($slot->fresh()->winner_entrant_id)->toBe($slot->entrant_a_id)
            ->and($slot->fresh()->advance_reason)->toBeNull();
    });

    it('rejects an unknown reason', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
                'reason' => 'bribery',
            ])
            ->assertSessionHasErrors('reason');

        expect($slot->fresh()->winner_entrant_id)->toBeNull();
    });

    it('clears the winner and reason when the winner is removed', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
                'reason' => 'retirement',
            ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => null,
            ]);

        expect($slot->fresh()->winner_entrant_id)->toBeNull()
            ->and($slot->fresh()->advance_reason)->toBeNull()
            ->and($round2->fresh()->entrant_a_id)->toBeNull();
    });

    it('clears the winner and reason when the match is reverted', function () {
        [$division, $bracket, $slot, $match] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();

        $match->update(['status' => MatchStatus::Completed, 'ended_at' => now()]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
                'reason' => 'walkover',
            ]);

        expect($slot->fresh()->advance_reason)->toBe('walkover');

        $this->actingAs($this->admin)->patch(route('admin.tennis-matches.update', $match), [
            'status' => 'running',
        ]);

        expect($slot->fresh()->winner_entrant_id)->toBeNull()
            ->and($slot->fresh()->advance_reason)->toBeNull()
            ->and($round2->fresh()->entrant_a_id)->toBeNull();
    });

    it('exposes the advance reason in the division payload', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
                'reason' => 'disqualification',
            ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.show', [
                'tournament' => $division->tournament_id,
                'division' => $division->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Division')
                ->where('bracket.rounds.0.slots.0.advance_reason', 'disqualification')
            );
    });

    it('undoes an advance and clears the parent entrant', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
                'reason' => 'walkover',
            ]);

        expect($round2->fresh()->entrant_a_id)->toBe($slot->entrant_a_id);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $slot->id)))
            ->assertRedirect();

        expect($slot->fresh()->winner_entrant_id)->toBeNull()
            ->and($slot->fresh()->advance_reason)->toBeNull()
            ->and($round2->fresh()->entrant_a_id)->toBeNull();
    });

    it('reverts a completed bracket to active when the final is undone', function () {
        $division = bracketTestDivision(2);
        bracketTestEntrants($division, 2);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $final = $bracket->slots()->where('round', 1)->orderBy('position')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $final->id)), [
                'winner_entrant_id' => $final->entrant_a_id,
            ]);

        expect($bracket->fresh()->status)->toBe(Bracket::STATUS_COMPLETED);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $final->id)))
            ->assertRedirect();

        expect($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE)
            ->and($final->fresh()->winner_entrant_id)->toBeNull();
    });

    it('refuses to undo while a later round has a winner', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();
        $sibling = $bracket->slots()->where('round', 1)->where('position', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
            ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $sibling->id)), [
                'winner_entrant_id' => $sibling->entrant_a_id,
            ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $round2->id)), [
                'winner_entrant_id' => $round2->fresh()->entrant_a_id,
            ]);

        expect($round2->fresh()->winner_entrant_id)->not->toBeNull();

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $slot->id)))
            ->assertRedirect()
            ->assertSessionHasErrors('slot');

        expect($slot->fresh()->winner_entrant_id)->not->toBeNull();
    });

    it('refuses to undo while a later round has a started match', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $round2 = $bracket->slots()->where('round', 2)->orderBy('position')->firstOrFail();
        $sibling = $bracket->slots()->where('round', 1)->where('position', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $slot->id)), [
                'winner_entrant_id' => $slot->entrant_a_id,
            ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.bracket.slots.update', advanceArgs($division, $sibling->id)), [
                'winner_entrant_id' => $sibling->entrant_a_id,
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.slots.start', advanceArgs($division, $round2->id)));

        expect($round2->matches()->count())->toBe(1);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $slot->id)))
            ->assertRedirect()
            ->assertSessionHasErrors('slot');

        expect($slot->fresh()->winner_entrant_id)->not->toBeNull();
    });

    it('refuses to undo a bye', function () {
        $division = bracketTestDivision(4);
        bracketTestEntrants($division, 3);

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.store', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
        ]), ['seeding_mode' => 'auto']);

        $bracket = Bracket::where('division_id', $division->id)->firstOrFail();
        $byeSlot = $bracket->slots()->where('round', 1)->where('bye', true)->firstOrFail();

        expect($byeSlot->winner_entrant_id)->not->toBeNull();

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $byeSlot->id)))
            ->assertRedirect()
            ->assertSessionHasErrors('slot');

        expect($byeSlot->fresh()->winner_entrant_id)->not->toBeNull();
    });

    it('refuses to undo a slot with no winner', function () {
        [$division, $bracket, $slot] = bracketAdvanceSetup();

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.divisions.bracket.slots.undo', advanceArgs($division, $slot->id)))
            ->assertRedirect()
            ->assertSessionHasErrors('slot');

        expect($slot->fresh()->winner_entrant_id)->toBeNull();
    });
});
