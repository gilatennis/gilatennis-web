<?php

use App\Enums\MatchStatus;
use App\Enums\PaymentEventSource;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;
use App\Services\TournamentPaymentService;

/**
 * Exercises the full group-stage pipeline in one sitting: paid registration,
 * group generation, playing every matchday through the slot/match endpoints,
 * live standings, picking the advancing winners, promotion into the knockout,
 * and crowning a champion.
 *
 * Three shapes are covered:
 *  - 4 paid, 2 groups of 2, one winner each: the minimal promotion path.
 *  - 8 paid, 2 groups of 4, two winners each: a full field, no byes, with
 *    group-mates seeded into opposite halves.
 *  - 6 paid, 3 groups of 2, one winner each: a non-power-of-two field that
 *    promotes with a bye.
 */

/**
 * Register each name and settle it as paid.
 *
 * @param  array<int, string>  $names
 * @return array<int, TournamentRegistration>
 */
function groupE2eSettlePaid(Tournament $tournament, Division $division, array $names): array
{
    $settle = app(TournamentPaymentService::class);
    $registrations = [];

    foreach ($names as $name) {
        $registration = e2eRegister($tournament, $division, $name);
        $settle->markPaid($registration, $registration->payment_reference, PaymentEventSource::System);
        $registrations[] = $registration;
    }

    return $registrations;
}

/**
 * The group-stage bracket for a division.
 */
function groupE2eGroupBracket(Division $division): Bracket
{
    return Bracket::where('division_id', $division->id)
        ->where('type', Bracket::TYPE_GROUP_STAGE)
        ->firstOrFail();
}

describe('Group stage lifecycle end to end', function () {
    beforeEach(function () {
        $this->admin = e2eAdmin();
        e2eFakeInvoiceService();

        ['tournament' => $this->tournament, 'division' => $this->division] = e2eTournament(
            drawSize: 4,
        );
    });

    it('runs from registration through the groups and a promoted champion', function () {
        // --- 1. Register and settle four entrants ---------------------------
        $registrations = groupE2eSettlePaid($this->tournament, $this->division, ['Ana', 'Budi', 'Citra', 'Dewi']);

        expect($this->division->orderedPaidRegistrations()->count())->toBe(4)
            ->and($registrations)->toHaveCount(4);

        // --- 2. Generate two groups of two ----------------------------------
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

        $bracket = groupE2eGroupBracket($this->division);

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->groups->every(fn (BracketGroup $g) => $g->slots()->count() === 1))->toBeTrue();

        $service = app(BracketService::class);

        // --- 3. Play every group tie; group winners advance -----------------
        foreach ($bracket->groups as $group) {
            $slot = $group->slots()->firstOrFail();

            e2ePlayAndAdvance($this->admin, $this->division, $slot->id, winningSide: 1);
        }

        $bracket->refresh();

        // Standings reflect the decided ties: one win per group winner.
        foreach ($bracket->groups as $group) {
            $standings = $service->standings($group);

            expect($standings->first()['wins'])->toBe(1)
                ->and($standings->first()['rank'])->toBe(1)
                ->and($standings->last()['wins'])->toBe(0);
        }

        // --- 4. Pick the advancing winner in each group ---------------------
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

        // --- 5. Promote into the knockout -----------------------------------
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

        // --- 6. Play the final and crown the champion -----------------------
        e2ePlayAndAdvance($this->admin, $this->division, $final->id, winningSide: 2);

        $champion = $final->entrant_b_id;

        $knockout->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($champion)
            ->and($knockout->status)->toBe(Bracket::STATUS_COMPLETED)
            ->and(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(3);
    });

    it('plays a full group field and seeds group-mates into opposite halves', function () {
        // --- 1. Register and settle eight entrants --------------------------
        $names = ['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4'];

        // The shared division is draw size 4; rebuild it for an 8-draw field.
        $division = $this->division->fresh();
        $division->update(['draw_size' => 8]);

        groupE2eSettlePaid($this->tournament, $division, $names);

        expect($division->orderedPaidRegistrations()->count())->toBe(8);

        // --- 2. Generate two groups of four ---------------------------------
        $args = [
            'tournament' => $this->tournament->id,
            'division' => $division->id,
        ];

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.store', $args), [
            'group_count' => 2,
            'advance_counts' => [2, 2],
            'double_round_robin' => false,
            'seeding_mode' => 'auto',
        ])->assertRedirect();

        $bracket = groupE2eGroupBracket($division);

        expect($bracket->groups)->toHaveCount(2)
            ->and($bracket->groups->every(fn (BracketGroup $g) => $g->slots()->count() === 6))->toBeTrue();

        $service = app(BracketService::class);

        // --- 3. Play all twelve group ties ----------------------------------
        foreach ($bracket->groups as $group) {
            foreach ($group->slots()->get() as $slot) {
                e2ePlayAndAdvance($this->admin, $division, $slot->id, winningSide: 1);
            }
        }

        $bracket->refresh();

        expect(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(12);

        // --- 4. Standings: each group ranks 1-4 on wins ---------------------
        foreach ($bracket->groups as $group) {
            $standings = $service->standings($group);

            expect($standings)->toHaveCount(4)
                ->and($standings->pluck('rank')->all())->toBe([1, 2, 3, 4])
                ->and($standings->sum('wins'))->toBe(6)
                ->and($standings->take(2)->sum('wins'))->toBe(4);
        }

        // --- 5. Pick the top two from each group ----------------------------
        $picksByGroup = [];
        $seedOrder = [];

        foreach ($bracket->groups as $group) {
            $picks = $service->standings($group)->take(2)->pluck('entrant_id')->all();

            $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.winners.store', [
                ...$args,
                'group' => $group->id,
            ]), ['winner_ids' => $picks])->assertRedirect();

            $picksByGroup[$group->id] = $picks;
            $seedOrder = array_merge($seedOrder, $picks);
        }

        expect($seedOrder)->toHaveCount(4);

        // --- 6. Promote: a four-draw field with no byes ---------------------
        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.promote', $args), [
            'winner_ids' => $seedOrder,
            'seed_pairs_apart' => true,
        ])->assertRedirect();

        $knockout = Bracket::where('division_id', $division->id)
            ->where('type', Bracket::TYPE_SINGLE_ELIMINATION)
            ->firstOrFail();

        expect($knockout->size)->toBe(4)
            ->and($knockout->slots()->where('round', 1)->where('bye', true)->count())->toBe(0);

        // Group-mates are seeded into opposite halves: each group's two picks
        // sit in different first-round ties, hence different semifinal halves.
        $round1 = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        foreach ($picksByGroup as $picks) {
            $halves = $round1->filter(fn ($slot) => in_array($slot->entrant_a_id, $picks, true)
                || in_array($slot->entrant_b_id, $picks, true));

            expect($halves)->toHaveCount(2);
        }

        // --- 7. Play the semifinals and final to a champion -----------------
        foreach ($knockout->slots()->where('round', 1)->orderBy('position')->get() as $semi) {
            e2ePlayAndAdvance($this->admin, $division, $semi->id, winningSide: 1);
        }

        $final = $knockout->fresh()->finalSlot();

        e2ePlayAndAdvance($this->admin, $division, $final->id, winningSide: 2);

        $champion = $final->entrant_b_id;

        $knockout->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($champion)
            ->and($knockout->status)->toBe(Bracket::STATUS_COMPLETED)
            ->and(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(15);
    });

    it('promotes a non-power-of-two field with a bye', function () {
        // --- 1. Register and settle six entrants ----------------------------
        $division = $this->division->fresh();
        $division->update(['draw_size' => 6]);

        groupE2eSettlePaid($this->tournament, $division, ['P1', 'P2', 'P3', 'P4', 'P5', 'P6']);

        expect($division->orderedPaidRegistrations()->count())->toBe(6);

        // --- 2. Generate three groups of two --------------------------------
        $args = [
            'tournament' => $this->tournament->id,
            'division' => $division->id,
        ];

        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.store', $args), [
            'group_count' => 3,
            'advance_counts' => [1, 1, 1],
            'double_round_robin' => false,
            'seeding_mode' => 'auto',
        ])->assertRedirect();

        $bracket = groupE2eGroupBracket($division);

        expect($bracket->groups)->toHaveCount(3);

        $service = app(BracketService::class);

        // --- 3. Play the three group ties -----------------------------------
        foreach ($bracket->groups as $group) {
            $slot = $group->slots()->firstOrFail();

            e2ePlayAndAdvance($this->admin, $division, $slot->id, winningSide: 1);
        }

        $bracket->refresh();

        // --- 4. Pick one winner per group -----------------------------------
        $seedOrder = [];

        foreach ($bracket->groups as $group) {
            $winnerId = $service->standings($group)->first()['entrant_id'];

            $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.groups.winners.store', [
                ...$args,
                'group' => $group->id,
            ]), ['winner_ids' => [$winnerId]])->assertRedirect();

            $seedOrder[] = $winnerId;
        }

        expect($seedOrder)->toHaveCount(3);

        // --- 5. Promote: three qualifiers into a four-draw, one bye ---------
        $this->actingAs($this->admin)->post(route('admin.tournaments.divisions.bracket.promote', $args), [
            'winner_ids' => $seedOrder,
            'seed_pairs_apart' => true,
        ])->assertRedirect();

        $knockout = Bracket::where('division_id', $division->id)
            ->where('type', Bracket::TYPE_SINGLE_ELIMINATION)
            ->firstOrFail();

        $firstRound = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        expect($knockout->size)->toBe(4)
            ->and($firstRound)->toHaveCount(2)
            ->and($firstRound->where('bye', true))->toHaveCount(1);

        // The bye entrant is pre-advanced: its first-round slot is resolved and
        // it already sits in a semifinal.
        $byeSlot = $firstRound->firstWhere('bye', true);
        $byeEntrantId = $byeSlot->entrant_a_id ?? $byeSlot->entrant_b_id;

        expect($byeSlot->winner_entrant_id)->toBe($byeEntrantId);

        $final = $knockout->finalSlot();

        expect(collect([$final->entrant_a_id, $final->entrant_b_id])->filter())
            ->toContain($byeEntrantId);

        // --- 6. Play the one live tie and the final -------------------------
        $liveSlot = $firstRound->firstWhere('bye', false);

        e2ePlayAndAdvance($this->admin, $division, $liveSlot->id, winningSide: 1);

        $final->refresh();

        expect($final->isReady())->toBeTrue();

        e2ePlayAndAdvance($this->admin, $division, $final->id, winningSide: 2);

        $champion = $final->entrant_b_id;

        $knockout->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($champion)
            ->and($knockout->status)->toBe(Bracket::STATUS_COMPLETED)
            // three group ties + one semifinal + one final
            ->and(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(5);
    });
});
