<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\Division;
use App\Models\DivisionPlayerFieldRequirement;
use App\Models\Role;
use App\Models\Settings;
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
        'waiver_accepted' => true,
        'players' => [
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '+1 555 000 0000',
                'gender' => 'female',
                'date_of_birth' => '1990-01-01',
                'rating_type' => 'UTR',
                'rating_value' => '5.0',
            ],
        ],
    ], $overrides);
}

function setPfrConfig(Division $division, array $overrides): void
{
    $defaults = [
        'name' => true,
        'gender' => true,
        'email' => true,
        'phone' => true,
        'level' => true,
        'dob' => false,
    ];

    $config = array_merge($defaults, $overrides);

    foreach ($config as $key => $required) {
        DivisionPlayerFieldRequirement::updateOrCreate(
            ['division_id' => $division->id, 'field_key' => $key],
            ['required' => (bool) $required],
        );
    }
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

    it('returns 404 for canceled tournament whose end date has passed', function () {
        $t = Tournament::factory()->canceled()->create([
            'end' => now()->subDay()->toDateString(),
        ]);

        $this->get(route('tournaments.show', $t->slug))
            ->assertStatus(404);
    });

    it('shows canceled tournament whose end date is in the future', function () {
        $t = Tournament::factory()->canceled()->create([
            'end' => now()->addWeek()->toDateString(),
        ]);

        $this->get(route('tournaments.show', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournaments/Show')
                ->where('tournament.status', 'canceled')
            );
    });

    it('shows canceled tournament with no end date set', function () {
        $t = Tournament::factory()->canceled()->create(['end' => null]);

        $this->get(route('tournaments.show', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournaments/Show')
                ->where('tournament.status', 'canceled')
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

    it('blocked for canceled tournament', function () {
        $t = Tournament::factory()->canceled()->create();
        Division::factory()->forTournament($t)->singles()->create([
            'draw_size' => 32, 'fee' => 50000, 'sort_order' => 0,
        ]);

        $this->get(route('tournaments.register', $t->slug))
            ->assertStatus(403);
    });

    it('store blocks canceled tournament', function () {
        $t = Tournament::factory()->canceled()->create();
        $div = Division::factory()->forTournament($t)->singles()->create([
            'draw_size' => 32, 'fee' => 50000, 'sort_order' => 0,
        ]);

        $this->postJson(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(403);

        expect(TournamentRegistration::where('tournament_id', $t->id)->count())->toBe(0);
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

    it('does not downgrade an existing registered user to guest', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        expect($user->fresh()->is_guest)->toBeFalse();
        expect(TournamentRegistration::where('email', 'jane@example.com')->first()->user_id)
            ->toBe($user->id);
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

    it('rejects duplicate paid registration with validation error', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'confirmation_code' => 'GO-2026-EXIST',
            'email' => $user->email,
            'division_id' => $div->id,
            'payment_status' => 'paid',
        ]);

        $initialCount = TournamentRegistration::where('division_id', $div->id)->count();

        $response = $this->postJson(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duplicate_registration']);

        expect($response->json('errors.duplicate_registration.0'))->toContain('GO-2026-EXIST');
        expect(TournamentRegistration::where('division_id', $div->id)->count())->toBe($initialCount);
    });

    it('allows re-registration when existing is pending', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'email' => $user->email,
            'division_id' => $div->id,
            'payment_status' => 'pending',
        ]);

        $initialCount = TournamentRegistration::where('email', $user->email)->count();

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        expect(TournamentRegistration::where('email', $user->email)->count())->toBe($initialCount + 1);
    });

    it('allows re-registration when existing is failed', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'email' => $user->email,
            'division_id' => $div->id,
            'payment_status' => 'failed',
        ]);

        $initialCount = TournamentRegistration::where('email', $user->email)->count();

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        expect(TournamentRegistration::where('email', $user->email)->count())->toBe($initialCount + 1);
    });

    it('allows re-registration when existing is processing', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 5000]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'email' => $user->email,
            'division_id' => $div->id,
            'payment_status' => 'processing',
        ]);

        $initialCount = TournamentRegistration::where('email', $user->email)->count();

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        expect(TournamentRegistration::where('email', $user->email)->count())->toBe($initialCount + 1);
    });

    it('adds transaction fee to amount_cents and persists breakdown', function () {
        Settings::query()->updateOrCreate(
            ['id' => 1],
            ['contact_wa_number' => '+6280000000000', 'transaction_fee' => 5000],
        );

        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t, ['fee' => 50000]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), singlesFormData($div))
            ->assertStatus(302);

        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'jane@example.com',
            'amount_cents' => 5500000,
            'transaction_fee_cents' => 500000,
        ]);
    });

    it('passes transaction fee to register page', function () {
        Settings::query()->updateOrCreate(
            ['id' => 1],
            ['contact_wa_number' => '+6280000000000', 'transaction_fee' => 5000],
        );

        $t = makeTournamentWithDivisions();

        $this->get(route('tournaments.register', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('transactionFee', 5000)
            );
    });
});

describe('Registration - Team', function () {
    it('creates team registration with players', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);
        setPfrConfig($div, ['level' => false]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'John Captain',
            'email' => 'john@test.com',
            'phone' => '0812000000',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Ace Breakers',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'John Captain', 'email' => 'john@test.com', 'phone' => '0812000000', 'gender' => 'male', 'date_of_birth' => '1990-01-01'],
                ['name' => 'Mary Player', 'email' => 'mary@test.com', 'phone' => '0813000000', 'gender' => 'female', 'date_of_birth' => '1992-02-02'],
                ['name' => 'Bob Player', 'email' => 'bob@test.com', 'phone' => '0814000000', 'gender' => 'male', 'date_of_birth' => '1988-03-03'],
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
                ['name' => 'John Captain', 'email' => 'john@test.com', 'phone' => '0812000000', 'gender' => 'male', 'date_of_birth' => '1990-01-01'],
                ['name' => 'Mary Player', 'email' => 'mary@test.com', 'phone' => '0813000000', 'gender' => 'female', 'date_of_birth' => '1992-02-02'],
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
            ->assertJsonValidationErrors(['division_id', 'name', 'email', 'phone', 'waiver_accepted', 'players']);
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
        app()->forgetInstance(DuitkuService::class);
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
            'amount' => '50',
            'merchantOrderId' => 'GO-2026-TEST',
            'resultCode' => '00',
            'reference' => 'DK-PAID-REF',
            'signature' => hash_hmac('sha256', 'TESTMERCHANT'.'50'.'GO-2026-TEST', 'testapikey'),
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
            'amount' => '50',
            'merchantOrderId' => 'GO-2026-TEST2',
            'resultCode' => '01',
            'signature' => hash_hmac('sha256', 'TESTMERCHANT'.'50'.'GO-2026-TEST2', 'testapikey'),
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
            'amount' => '50',
            'merchantOrderId' => 'GO-2026-TEST3',
            'resultCode' => '00',
            'signature' => hash_hmac('sha256', 'TESTMERCHANT'.'50'.'GO-2026-TEST3', 'testapikey'),
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

describe('Registration - Team Levels', function () {
    it('passes when level counts meet requirements', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 6,
            'max_team_size' => 8,
            'level_requirements' => ['Upper Beginner' => 4, 'Intermediate' => 2],
        ]);

        fakeInvoiceService();

        $players = array_merge(
            [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male', 'level' => 'Upper Beginner'],
            ],
            array_map(fn ($i) => [
                'name' => "UB Player {$i}",
                'email' => "ub{$i}@test.com",
                'phone' => "0812{$i}",
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'level' => 'Upper Beginner',
            ], range(1, 3)),
            array_map(fn ($i) => [
                'name' => "INT Player {$i}",
                'email' => "int{$i}@test.com",
                'phone' => "0813{$i}",
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'level' => 'Intermediate',
            ], range(1, 2)),
        );

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Level Team',
            'waiver_accepted' => true,
            'players' => $players,
        ])->assertStatus(302);

        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'cap@test.com',
            'division_id' => $div->id,
            'payment_status' => 'processing',
        ]);

        $reg = TournamentRegistration::where('email', 'cap@test.com')->first();
        expect($reg->players()->where('level', 'Upper Beginner')->count())->toBe(4);
        expect($reg->players()->where('level', 'Intermediate')->count())->toBe(2);
    });

    it('fails when level minimum not met', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 4,
            'max_team_size' => 8,
            'level_requirements' => ['Upper Beginner' => 4, 'Intermediate' => 2],
        ]);

        $players = [
            ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male', 'level' => 'Upper Beginner'],
            ['name' => 'UB2', 'email' => 'ub2@test.com', 'phone' => '0812', 'gender' => 'male', 'level' => 'Upper Beginner'],
            ['name' => 'UB3', 'email' => 'ub3@test.com', 'phone' => '0813', 'gender' => 'female', 'level' => 'Upper Beginner'],
            ['name' => 'UB4', 'email' => 'ub4@test.com', 'phone' => '0814', 'gender' => 'female', 'level' => 'Upper Beginner'],
            ['name' => 'INT1', 'email' => 'int1@test.com', 'phone' => '0815', 'gender' => 'male', 'level' => 'Intermediate'],
        ];

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Under Level',
            'waiver_accepted' => true,
            'players' => $players,
        ])->assertStatus(422)->assertJsonValidationErrors(['players']);
    });

    it('returns multiple player error messages as an array when several constraints fail', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 6,
            'max_team_size' => 8,
            'min_female_players' => 2,
            'min_male_players' => 2,
            'level_requirements' => ['Upper Beginner' => 4, 'Intermediate' => 2],
        ]);

        $players = [
            ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male', 'level' => 'Upper Beginner'],
        ];

        $response = $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Many Errors',
            'waiver_accepted' => true,
            'players' => $players,
        ])->assertStatus(422)->assertJsonValidationErrors(['players']);

        $playerErrors = $response->json('errors.players');

        expect($playerErrors)->toBeArray();
        expect(count($playerErrors))->toBeGreaterThanOrEqual(4);
        expect(collect($playerErrors)->implode(' '))->toContain('Team requires at least 6 players')
            ->toContain('female')
            ->toContain('male')
            ->toContain('Upper Beginner')
            ->toContain('Intermediate');
    });

    it('ignores level requirements when not configured', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 2,
            'max_team_size' => 4,
            'level_requirements' => null,
        ]);
        setPfrConfig($div, ['level' => false]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'No Levels',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'P2', 'email' => 'p2@test.com', 'phone' => '0812', 'gender' => 'female'],
            ],
        ])->assertStatus(302);

        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'cap@test.com',
            'division_id' => $div->id,
            'payment_status' => 'processing',
        ]);
    });

    it('rejects invalid level value', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Bad Level',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male', 'level' => 'Pro Hacker'],
                ['name' => 'P2', 'email' => 'p2@test.com', 'phone' => '0812', 'gender' => 'female'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['players.0.level']);
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

    it('does not settle payment from a forged resultCode=00 return url', function () {
        $reg = TournamentRegistration::factory()->create(['payment_status' => 'processing']);

        $this->get('/payments/duitku/return?merchantOrderId='.$reg->confirmation_code.'&resultCode=00&reference=FORGED')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/PaymentPending'));

        $reg->refresh();
        expect($reg->payment_status)->toBe('processing');
        expect($reg->payment_reference)->not->toBe('FORGED');

        $this->assertDatabaseHas('payment_events', [
            'tournament_registration_id' => $reg->id,
            'source' => 'return',
            'signature_valid' => false,
            'result_code' => '00',
        ]);
    });
});

describe('Player Field Requirements', function () {
    it('allows teammate without email and phone when division relaxes them', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, ['min_team_size' => 2, 'max_team_size' => 4]);
        setPfrConfig($div, ['email' => false, 'phone' => false, 'level' => false]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Relaxed',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'No Contact', 'gender' => 'female'],
            ],
        ])->assertStatus(302);

        $reg = TournamentRegistration::where('email', 'cap@test.com')->first();
        expect($reg)->not->toBeNull();

        $teammate = $reg->players()->where('name', 'No Contact')->first();
        expect($teammate)->not->toBeNull();
        expect($teammate->email)->toBeNull();
        expect($teammate->phone)->toBeNull();
    });

    it('still requires captain email and phone when division relaxes them', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);
        setPfrConfig($div, ['email' => false, 'phone' => false]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => '',
            'phone' => '',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'NoCaptain',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'Teammate', 'gender' => 'female'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);
    });

    it('still requires teammate name when everything else is relaxed', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);
        setPfrConfig($div, [
            'name' => true,
            'gender' => false,
            'email' => false,
            'phone' => false,
            'level' => false,
            'dob' => false,
        ]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'NoName',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => '', 'gender' => 'female'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['players.1.name']);
    });

    it('strict config behaves like before (regression)', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'Strict',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'Teammate'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'players.1.email',
                'players.1.phone',
                'players.1.gender',
                'players.1.level',
            ]);
    });

    it('forces level required when level_requirements are set, regardless of toggle', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 3,
            'max_team_size' => 4,
            'level_requirements' => ['Upper Beginner' => 1],
        ]);
        setPfrConfig($div, ['level' => false]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'ForcedLevel',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'Teammate1', 'email' => 't1@test.com', 'phone' => '0812', 'gender' => 'female', 'level' => 'Upper Beginner'],
                ['name' => 'Teammate2', 'email' => 't2@test.com', 'phone' => '0813', 'gender' => 'female'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['players.2.level']);
    });

    it('forces teammate gender required when min_male_players is set, even with cfg.gender=false', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 2,
            'max_team_size' => 4,
            'min_male_players' => 1,
        ]);
        setPfrConfig($div, ['gender' => false, 'level' => false]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'ForcedGenderMale',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'NoGender', 'email' => 'ng@test.com', 'phone' => '0812'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['players.1.gender']);
    });

    it('forces teammate gender required when min_female_players is set, even with cfg.gender=false', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 2,
            'max_team_size' => 4,
            'min_female_players' => 1,
        ]);
        setPfrConfig($div, ['gender' => false, 'level' => false]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'ForcedGenderFemale',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'NoGender', 'email' => 'ng@test.com', 'phone' => '0812'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['players.1.gender']);
    });

    it('does not require teammate gender when no min gender constraints and cfg.gender=false', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeTeamDivision($t, [
            'min_team_size' => 2,
            'max_team_size' => 4,
        ]);
        setPfrConfig($div, [
            'gender' => false,
            'email' => false,
            'phone' => false,
            'level' => false,
        ]);

        fakeInvoiceService();

        $this->post(route('tournaments.register.store', $t->slug), [
            'division_id' => $div->id,
            'name' => 'Captain',
            'email' => 'cap@test.com',
            'phone' => '0811',
            'date_of_birth' => '1990-01-01',
            'team_name' => 'OptionalGender',
            'waiver_accepted' => true,
            'players' => [
                ['name' => 'Captain', 'email' => 'cap@test.com', 'phone' => '0811', 'gender' => 'male'],
                ['name' => 'NoGender'],
            ],
        ])->assertStatus(302);

        $reg = TournamentRegistration::where('email', 'cap@test.com')->first();
        expect($reg)->not->toBeNull();

        $teammate = $reg->players()->where('name', 'NoGender')->first();
        expect($teammate)->not->toBeNull();
        expect($teammate->gender)->toBeNull();
    });

    it('Division::playerFieldConfig returns safe defaults when no rows exist', function () {
        $t = Tournament::factory()->create(['status' => 'open']);
        $div = makeSinglesDivision($t);

        DivisionPlayerFieldRequirement::where('division_id', $div->id)->delete();

        $config = $div->playerFieldConfig();
        expect($config['name'])->toBeTrue();
        expect($config['gender'])->toBeTrue();
        expect($config['email'])->toBeTrue();
        expect($config['phone'])->toBeTrue();
        expect($config['level'])->toBeTrue();
        expect($config['dob'])->toBeFalse();
    });

    it('rejects name.required = false via admin form', function () {
        $t = Tournament::factory()->create(['status' => 'draft']);
        $div = makeTeamDivision($t);

        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin'],
        );
        $admin = User::factory()->create();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        $this->actingAs($admin);

        $response = $this->putJson(route('admin.tournaments.update', $t), [
            'name' => $t->name,
            'slug' => $t->slug,
            'tagline' => '',
            'dates' => 'TBD',
            'start' => '2026-09-01',
            'end' => '2026-09-02',
            'city' => 'Jakarta',
            'venue' => '',
            'surface' => 'hard',
            'ball_type' => '',
            'venue_type' => 'indoor',
            'perks' => [],
            'registration_closes' => '',
            'is_featured' => false,
            'status' => 'draft',
            'contact_person_name' => '',
            'contact_person_phone' => '',
            'divisions' => [
                [
                    'id' => $div->id,
                    'name' => $div->name,
                    'slug' => $div->slug,
                    'participation_category' => 'team',
                    'draw_size' => $div->draw_size,
                    'fee' => $div->fee,
                    'format' => null,
                    'gender' => null,
                    'rating_type' => null,
                    'rating_min' => null,
                    'rating_max' => null,
                    'min_team_size' => 2,
                    'max_team_size' => 4,
                    'min_female_players' => null,
                    'min_male_players' => null,
                    'level_requirements' => null,
                    'player_field_requirements' => [
                        ['field_key' => 'name', 'required' => false],
                        ['field_key' => 'gender', 'required' => true],
                        ['field_key' => 'email', 'required' => true],
                        ['field_key' => 'phone', 'required' => true],
                        ['field_key' => 'level', 'required' => true],
                        ['field_key' => 'dob', 'required' => false],
                    ],
                    'prizes' => [],
                ],
            ],
            'schedule' => [],
            'rules' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'divisions.0.player_field_requirements.0.required',
        ]);
    });
});
