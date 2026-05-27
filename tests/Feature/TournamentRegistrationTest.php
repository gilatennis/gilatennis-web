<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;

function makeTournamentWithDivisions(array $tournamentAttrs = [], array $divisionAttrs = []): Tournament
{
    $t = Tournament::factory()->create(array_merge(['status' => 'open'], $tournamentAttrs));

    Division::factory()->forTournament($t)->singles()->create(array_merge([
        'draw_size' => 32, 'fee' => 50000, 'sort_order' => 0,
    ], $divisionAttrs));

    return $t;
}

function makeSinglesDivision(Tournament $t, array $attrs = []): Division
{
    return Division::factory()->forTournament($t)->singles()->create(array_merge([
        'draw_size' => 32, 'fee' => 50000, 'sort_order' => 0,
    ], $attrs));
}

function makeTeamDivision(Tournament $t, array $attrs = []): Division
{
    return Division::factory()->forTournament($t)->team()->create(array_merge([
        'draw_size' => 8, 'fee' => 120000, 'min_team_size' => 2, 'max_team_size' => 4, 'sort_order' => 1,
    ], $attrs));
}

function fakeInvoiceService()
{
    test()->swap(DuitkuService::class, new class extends DuitkuService
    {
        public function __construct() {}

        public function createInvoice($registration): DuitkuInvoiceResponse
        {
            return new DuitkuInvoiceResponse(
                merchantCode: 'DEMO0001',
                reference: 'DK-TESTREF123',
                paymentUrl: 'https://sandbox.duitku.com/pay/DEMO123',
                statusCode: '00',
                statusMessage: 'Invoice created',
            );
        }
    });
}

function singlesFormData(Division $division, array $overrides = []): array
{
    return array_merge([
        'division_id' => $division->id,
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'phone' => '+1 555 000 0000',
        'date_of_birth' => '1990-01-01',
        'tshirt_size' => 'M',
        'waiver_accepted' => true,
        'players' => [
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '+1 555 000 0000',
                'date_of_birth' => '1990-01-01',
                'rating_type' => 'UTR',
                'rating_value' => '5.0',
            ],
        ],
    ], $overrides);
}

describe('Tournament Detail Page', function () {
    it('loads', function () {
        $t = makeTournamentWithDivisions();

        $this->get(route('tournaments.show', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/Show'));
    });

    it('shows registration count', function () {
        $t = makeTournamentWithDivisions();
        $div = $t->divisions->first();
        TournamentRegistration::factory()->forTournament($t)->create([
            'division_id' => $div->id,
            'payment_status' => 'paid',
        ]);

        $this->get(route('tournaments.show', $t->slug))
            ->assertInertia(fn ($page) => $page
                ->where('registrationCount', 1)
            );
    });
});

describe('Registration Form', function () {
    it('loads for open tournament', function () {
        $t = makeTournamentWithDivisions();

        $this->get(route('tournaments.register', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/Register'));
    });

    it('blocked for sold out tournament', function () {
        $t = makeTournamentWithDivisions(['status' => 'open'], ['draw_size' => 1]);
        $div = $t->divisions->first();
        TournamentRegistration::factory()->forTournament($t)->create([
            'division_id' => $div->id, 'payment_status' => 'paid',
        ]);

        $this->get(route('tournaments.register', $t->slug))
            ->assertStatus(403);
    });

    it('creates guest user', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'is_guest' => true,
        ]);
    });

    it('reuses existing guest user', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        User::factory()->create(['email' => 'jane@example.com', 'is_guest' => true]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        expect(User::where('email', 'jane@example.com')->count())->toBe(1);
    });

    it('creates registration with processing status', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'jane@example.com',
            'payment_status' => 'processing',
            'division_id' => $div->id,
        ]);
    });

    it('redirects existing paid registration to success', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $existing = TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'email' => $user->email,
            'division_id' => $div->id,
            'payment_status' => 'paid',
        ]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertRedirect(route('tournaments.register.success', $existing->confirmation_code));
    });
});

describe('Registration - Team', function () {
    it('creates team registration with players', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'John Captain',
            'email' => 'john@test.com',
            'phone' => '0812000000',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Ace Breakers',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'John Captain', 'email' => 'john@test.com', 'phone' => '0812000000', 'date_of_birth' => '1990-01-01'],
                ['name' => 'Mary Player', 'email' => 'mary@test.com', 'phone' => '0813000000', 'date_of_birth' => '1992-02-02'],
                ['name' => 'Bob Player', 'email' => 'bob@test.com', 'phone' => '0814000000', 'date_of_birth' => '1988-03-03'],
            ],
        ])->assertStatus(302);

        $this->assertDatabaseHas('teams', ['name' => 'Ace Breakers']);
        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'john@test.com',
            'division_id' => $div->id,
            'payment_status' => 'processing',
        ]);
        $this->assertDatabaseCount('tournament_registration_players', 3);
    });

    it('rejects team with too few players', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, ['min_team_size' => 3]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'John Captain',
            'email' => 'john@test.com',
            'phone' => '0812000000',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Solo Team',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'John Captain', 'email' => 'john@test.com', 'phone' => '0812000000', 'date_of_birth' => '1990-01-01'],
                ['name' => 'Mary Player', 'email' => 'mary@test.com', 'phone' => '0813000000', 'date_of_birth' => '1992-02-02'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['players']);
    });
});

describe('Registration Validation', function () {
    it('rejects empty fields', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);

        $this->postJson(route('tournaments.register.store', $t->slug), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['division_id', 'name', 'email', 'phone', 'date_of_birth', 'waiver_accepted', 'players']);
    });

    it('rejects invalid email', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);

        $this->postJson(route('tournaments.register.store', $t->slug), singlesFormData($div, ['email' => 'not-an-email']))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    });

    it('rejects out-of-range rating', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);

        $this->postJson(route('tournaments.register.store', $t->slug), singlesFormData($div, ['players' => [['rating_value' => '20']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['players.0.rating_value']);
    });
});

describe('Duitku Webhook', function () {
    beforeEach(function () {
        config(['services.duitku.api_key' => 'testapikey']);
    });

    it('marks payment as paid on resultCode 00', function () {
        $t = makeTournamentWithDivisions();
        $div = $t->divisions->first();
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST',
            'payment_status' => 'processing',
            'amount_cents' => 5000,
            'division_id' => $div->id,
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantCode' => 'TESTMERCHANT',
            'amount' => '5000',
            'merchantOrderId' => 'GO-2026-TEST',
            'resultCode' => '00',
            'reference' => 'DK-PAID-REF',
            'signature' => md5('TESTMERCHANT'.'5000'.'GO-2026-TEST'.'testapikey'),
        ])->assertOk();

        $reg->refresh();
        expect($reg->payment_status)->toBe('paid');
        expect($reg->payment_reference)->toBe('DK-PAID-REF');
    });

    it('marks payment as failed on non-00 resultCode', function () {
        $t = makeTournamentWithDivisions();
        $div = $t->divisions->first();
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST2',
            'payment_status' => 'processing',
            'amount_cents' => 5000,
            'division_id' => $div->id,
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantCode' => 'TESTMERCHANT',
            'amount' => '5000',
            'merchantOrderId' => 'GO-2026-TEST2',
            'resultCode' => '01',
            'signature' => md5('TESTMERCHANT'.'5000'.'GO-2026-TEST2'.'testapikey'),
        ])->assertOk();

        $reg->refresh();
        expect($reg->payment_status)->toBe('failed');
    });

    it('division spots_available reflects paid registrations', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = Division::factory()->forTournament($t)->singles()->create([
            'draw_size' => 32, 'fee' => 50000,
        ]);
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST3',
            'payment_status' => 'processing',
            'amount_cents' => 5000,
            'division_id' => $div->id,
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantCode' => 'TESTMERCHANT',
            'amount' => '5000',
            'merchantOrderId' => 'GO-2026-TEST3',
            'resultCode' => '00',
            'signature' => md5('TESTMERCHANT'.'5000'.'GO-2026-TEST3'.'testapikey'),
        ])->assertOk();

        $div->refresh();
        expect($div->spots_available)->toBe(31);
    });
});

describe('Registration Result Pages', function () {
    it('success page loads for paid registration', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'paid']);

        $this->get(route('tournaments.register.success', $reg->confirmation_code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/RegistrationSuccess'));
    });

    it('failed page loads for failed registration', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'failed']);

        $this->get(route('tournaments.register.failed', $reg->confirmation_code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/RegistrationFailed'));
    });

    it('success page redirects non-paid registration to failed', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'pending']);

        $this->get(route('tournaments.register.success', $reg->confirmation_code))
            ->assertRedirect(route('tournaments.register.failed', $reg->confirmation_code));
    });
});

describe('Duitku Return Controller', function () {
    it('redirects paid registration to success page', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'paid']);

        $response = $this->get('/payments/duitku/return?merchantOrderId='.$reg->confirmation_code);

        $response->assertRedirect(route('tournaments.register.success', $reg->confirmation_code));
    });

    it('redirects failed registration to failed page', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'failed']);

        $response = $this->get('/payments/duitku/return?merchantOrderId='.$reg->confirmation_code);

        $response->assertRedirect(route('tournaments.register.failed', $reg->confirmation_code));
    });

    it('shows pending page for processing registration', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'processing']);

        $this->get('/payments/duitku/return?merchantOrderId='.$reg->confirmation_code)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/PaymentPending'));
    });
});
