<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\Division;
use App\Models\Role;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;

/*
|--------------------------------------------------------------------------
| End-to-end helpers
|--------------------------------------------------------------------------
|
| Shared by the lifecycle tests in tests/Feature/EndToEnd. These are plain
| global functions (Pest style) loaded from tests/Pest.php, not a test file.
|
*/

/**
 * A super-admin able to drive the admin bracket endpoints.
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
function e2eTournament(int $drawSize = 4): array
{
    $tournament = Tournament::factory()->create(['status' => 'open']);

    $division = Division::factory()->forTournament($tournament)->singles()->create([
        'draw_size' => $drawSize,
        'fee' => 50000,
        'sort_order' => 0,
    ]);

    return ['tournament' => $tournament, 'division' => $division];
}

/**
 * Swap in a Duitku service whose invoice creation never leaves the process.
 */
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

/**
 * Register a singles entrant through the public route and return the record.
 */
function e2eRegister(Tournament $tournament, Division $division, string $name, ?string $email = null): TournamentRegistration
{
    $email ??= strtolower($name).'@example.com';

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

/**
 * Record the advancing winner of a slot.
 */
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

/**
 * Play the slot for the given side and advance that side's entrant — the two
 * steps an admin performs to send a winner through.
 */
function e2ePlayAndAdvance(User $admin, Division $division, int $slotId, int $winningSide): void
{
    $match = e2ePlaySlot($admin, $division, $slotId, $winningSide);

    $slot = $match->bracketSlot()->firstOrFail();

    $winner = $winningSide === 1 ? $slot->entrant_a_id : $slot->entrant_b_id;

    e2eAdvance($admin, $division, $slotId, $winner);
}
