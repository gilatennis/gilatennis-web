<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Enums\MatchStatus;
use App\Enums\PaymentEventSource;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\PaymentEvent;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;
use App\Services\TournamentPaymentService;

/**
 * Exercises the whole tournament pipeline in one sitting: public registration,
 * payment settlement, bracket generation, scoring each rubber to a win, the
 * explicit advancement step, and the crowning of a champion.
 *
 * Draw size 4 keeps the path complete but minimal: two opening rubbers feed a
 * single final, and the final produces the winner.
 */
function e2eAdmin(): User
{
    $admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $admin->roles()->syncWithoutDetaching([$role->id]);

    return $admin;
}

/**
 * @return array{tournament: Tournament, division: Division}
 */
function e2eTournament(): array
{
    $tournament = Tournament::factory()->create(['status' => 'open']);

    $division = Division::factory()->forTournament($tournament)->singles()->create([
        'draw_size' => 4,
        'fee' => 50000,
        'sort_order' => 0,
    ]);

    return ['tournament' => $tournament, 'division' => $division];
}

function e2eFakeInvoiceService(): void
{
    test()->swap(DuitkuService::class, new class extends DuitkuService
    {
        public function __construct() {}

        public function createInvoice($registration): DuitkuInvoiceResponse
        {
            return new DuitkuInvoiceResponse(
                merchantCode: 'DEMO0001',
                reference: 'DK-E2E'.strtoupper(substr(md5($registration->email), 0, 6)),
                paymentUrl: 'https://sandbox.duitku.com/pay/E2E',
                statusCode: '00',
                statusMessage: 'Invoice created',
            );
        }
    });
}

function e2eRegister(Tournament $tournament, Division $division, string $name, string $email): TournamentRegistration
{
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
 * Start the slot's match, win it for the given side with a Fast4 set, confirm
 * the result, and return the completed match.
 */
function e2ePlaySlot(User $admin, Division $division, int $slotId, int $winningSide): TennisMatch
{
    $args = [
        'tournament' => $division->tournament_id,
        'division' => $division->id,
        'slot' => $slotId,
    ];

    test()->actingAs($admin)->post(route('admin.tournaments.divisions.bracket.slots.match.store', $args))
        ->assertRedirect();

    $match = TennisMatch::where('bracket_slot_id', $slotId)->latest('id')->firstOrFail();

    // Table tennis is Fast4: first to 4 games, single set decides.
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

    $match->refresh();
    expect($match->state->win)->toBe($winningSide);

    test()->actingAs($admin)->patch(route('admin.tennis-matches.update', $match), [
        'status' => 'completed',
    ])->assertRedirect();

    return $match->refresh();
}

function e2eAdvance(User $admin, Division $division, int $slotId, int $winnerEntrantId): void
{
    test()->actingAs($admin)
        ->patch(route('admin.tournaments.divisions.bracket.slots.update', [
            'tournament' => $division->tournament_id,
            'division' => $division->id,
            'slot' => $slotId,
        ]), ['winner_entrant_id' => $winnerEntrantId])
        ->assertRedirect();
}

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

        // --- 3. Generate the bracket ---------------------------------------
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

        // --- 5. Play the final ---------------------------------------------
        $finalMatch = e2ePlaySlot($this->admin, $this->division, $final->id, winningSide: 2);

        expect($finalMatch->status)->toBe(MatchStatus::Completed)
            ->and($final->fresh()->winner_entrant_id)->toBeNull();

        $champion = $final->entrant_b_id;
        e2eAdvance($this->admin, $this->division, $final->id, $champion ?? 0);

        // --- 6. Crowning assertions ----------------------------------------
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
