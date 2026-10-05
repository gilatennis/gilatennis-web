<?php

use App\Enums\MatchStatus;
use App\Enums\PaymentEventSource;
use App\Models\Bracket;
use App\Models\PaymentEvent;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use App\Services\TournamentPaymentService;

/**
 * Exercises the whole tournament pipeline in one sitting: public registration,
 * payment settlement, bracket generation, scoring each rubber to a win, the
 * explicit advancement step, and the crowning of a champion.
 *
 * Draw size 4 keeps the path complete but minimal: two opening rubbers feed a
 * single final, and the final produces the winner.
 *
 * The shared helpers live in tests/Feature/EndToEnd/EndToEndHelpers.php.
 */
describe('Tournament lifecycle end to end', function () {
    beforeEach(function () {
        $this->admin = e2eAdmin();
        e2eFakeInvoiceService();

        ['tournament' => $this->tournament, 'division' => $this->division] = e2eTournament();
    });

    it('runs from registration through bracket and scoring to a champion', function () {
        // --- 1. Register four entrants through the public route -------------
        $names = ['Ana', 'Budi', 'Citra', 'Dewi'];

        $registrations = collect($names)
            ->map(fn (string $name) => e2eRegister(
                $this->tournament,
                $this->division,
                $name,
                strtolower($name).'@example.com',
            ));

        expect($registrations)->toHaveCount(4)
            ->and($registrations->every(fn (TournamentRegistration $r) => $r->payment_status === 'processing'))->toBeTrue()
            ->and($this->division->registrations()->count())->toBe(4);

        // --- 2. Settle every registration as paid ---------------------------
        $settle = app(TournamentPaymentService::class);

        foreach ($registrations as $registration) {
            $settle->markPaid($registration, $registration->payment_reference, PaymentEventSource::System);
        }

        expect(TournamentRegistration::where('payment_status', 'paid')->count())->toBe(4)
            ->and(PaymentEvent::count())->toBe(4);

        // A bracket may only be generated from paid entrants.
        expect($this->division->orderedPaidRegistrations()->count())->toBe(4);

        // --- 3. Generate the bracket ----------------------------------------
        $bracketArgs = [
            'tournament' => $this->tournament->id,
            'division' => $this->division->id,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.divisions.bracket.store', $bracketArgs), ['seeding_mode' => 'auto'])
            ->assertRedirect();

        $bracket = Bracket::where('division_id', $this->division->id)->firstOrFail();

        expect($bracket->size)->toBe(4)
            ->and($bracket->status)->toBe(Bracket::STATUS_DRAFT)
            ->and($bracket->slots()->count())->toBe(3);

        $round1 = $bracket->slots()->where('round', 1)->orderBy('position')->get();
        $final = $bracket->finalSlot();

        expect($round1)->toHaveCount(2)
            ->and($round1->every(fn ($slot) => $slot->isReady()))->toBeTrue()
            ->and($final->round)->toBe(2);

        // --- 4. Play the two opening rubbers --------------------------------
        // The higher seed (entrant A) wins each opener.
        foreach ($round1 as $slot) {
            $match = e2ePlaySlot($this->admin, $this->division, $slot->id, winningSide: 1);

            expect($match->status)->toBe(MatchStatus::Completed)
                ->and($bracket->fresh()->status)->toBe(Bracket::STATUS_ACTIVE);

            // Completion alone must NOT advance anyone.
            expect($slot->fresh()->winner_entrant_id)->toBeNull();

            e2eAdvance($this->admin, $this->division, $slot->id, $slot->entrant_a_id ?? 0);
        }

        // Both opening winners now meet in the final.
        $r1Winners = $round1->map(fn ($slot) => $slot->fresh()->winner_entrant_id)->all();
        $final->refresh();

        expect($final->entrant_a_id)->toBe($r1Winners[0])
            ->and($final->entrant_b_id)->toBe($r1Winners[1])
            ->and($final->isReady())->toBeTrue();

        // --- 5. Play the final ----------------------------------------------
        $finalMatch = e2ePlaySlot($this->admin, $this->division, $final->id, winningSide: 2);

        expect($finalMatch->status)->toBe(MatchStatus::Completed)
            ->and($final->fresh()->winner_entrant_id)->toBeNull();

        $champion = $final->entrant_b_id;
        e2eAdvance($this->admin, $this->division, $final->id, $champion ?? 0);

        // --- 6. Crowning assertions -----------------------------------------
        $championRegistration = TournamentRegistration::findOrFail($champion);
        $bracket->refresh();

        expect($final->fresh()->winner_entrant_id)->toBe($champion)
            ->and($bracket->status)->toBe(Bracket::STATUS_COMPLETED)
            ->and(TennisMatch::count())->toBe(3)
            ->and(TennisMatch::where('status', MatchStatus::Completed)->count())->toBe(3)
            ->and(TournamentRegistration::where('payment_status', 'processing')->count())->toBe(0)
            // The champion came out of the second opening rubber's top half.
            ->and($round1[1]->fresh()->winner_entrant_id)->toBe($championRegistration->id);
    });
});
