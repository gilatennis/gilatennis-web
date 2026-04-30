<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;

describe('Tournament Detail Page', function () {
    it('loads', function () {
        $t = Tournament::factory()->create(['status' => 'open', 'spots_left' => 10]);

        $this->get(route('tournaments.show', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/Show'));
    });

    it('shows registration count', function () {
        $t = Tournament::factory()->create(['status' => 'open', 'spots_left' => 10]);
        TournamentRegistration::factory()->forTournament($t)->create(['payment_status' => 'paid']);

        $this->get(route('tournaments.show', $t->slug))
            ->assertInertia(fn ($page) => $page
                ->where('registrationCount', 1)
            );
    });
});

describe('Registration Form', function () {
    it('loads for open tournament', function () {
        $t = Tournament::factory()->create(['status' => 'open', 'spots_left' => 10]);

        $this->get(route('tournaments.register', $t->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tournaments/Register'));
    });

    it('blocked for sold out tournament', function () {
        $t = Tournament::factory()->create(['spots_left' => 0]);

        $this->get(route('tournaments.register', $t->slug))
            ->assertStatus(403);
    });

    it('creates guest user', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);

        $this->swap(DuitkuService::class, new class extends DuitkuService
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

        $this->post(route('tournaments.register.store', $t->slug), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '5.0',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'is_guest' => true,
        ]);
    });

    it('reuses existing guest user', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);
        User::factory()->create(['email' => 'jane@example.com', 'is_guest' => true]);

        $this->swap(DuitkuService::class, new class extends DuitkuService
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

        $this->post(route('tournaments.register.store', $t->slug), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '5.0',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertStatus(302);

        expect(User::where('email', 'jane@example.com')->count())->toBe(1);
    });

    it('creates registration with processing status', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);

        $this->swap(DuitkuService::class, new class extends DuitkuService
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

        $this->post(route('tournaments.register.store', $t->slug), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '5.0',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertStatus(302);

        $this->assertDatabaseHas('tournament_registrations', [
            'email' => 'jane@example.com',
            'payment_status' => 'processing',
        ]);
    });

    it('redirects existing paid registration to success', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);
        $user = User::factory()->create();
        $existing = TournamentRegistration::factory()->forTournament($t)->forUser($user)->create([
            'email' => $user->email,
            'payment_status' => 'paid',
        ]);

        $this->swap(DuitkuService::class, new class extends DuitkuService
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

        $this->post(route('tournaments.register.store', $t->slug), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '5.0',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertRedirect(route('tournaments.register.success', $existing->confirmation_code));
    });
});

describe('Registration Validation', function () {
    it('rejects empty fields', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);

        $this->postJson(route('tournaments.register.store', $t->slug), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'date_of_birth', 'rating_value', 'event_type', 'tshirt_size', 'waiver_accepted']);
    });

    it('rejects invalid email', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'name' => 'Jane Smith',
            'email' => 'not-an-email',
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '5.0',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    });

    it('rejects out-of-range rating', function () {
        $t = Tournament::factory()->create(['fee' => 5000, 'status' => 'open', 'spots_left' => 10]);

        $this->postJson(route('tournaments.register.store', $t->slug), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1 555 000 0000',
            'date_of_birth' => '1990-01-01',
            'rating_type' => 'UTR',
            'rating_value' => '20',
            'event_type' => 'singles',
            'tshirt_size' => 'M',
            'waiver_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['rating_value']);
    });
});

describe('Duitku Webhook', function () {
    it('marks payment as paid on resultCode 00', function () {
        $t = Tournament::factory()->create(['spots_left' => 10]);
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST',
            'payment_status' => 'processing',
            'amount_cents' => 5000,
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantOrderId' => 'GO-2026-TEST',
            'resultCode' => '00',
            'reference' => 'DK-PAID-REF',
        ])->assertOk();

        $reg->refresh();
        expect($reg->payment_status)->toBe('paid');
        expect($reg->payment_reference)->toBe('DK-PAID-REF');
    });

    it('marks payment as failed on non-00 resultCode', function () {
        $t = Tournament::factory()->create(['spots_left' => 10]);
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST2',
            'payment_status' => 'processing',
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantOrderId' => 'GO-2026-TEST2',
            'resultCode' => '01',
        ])->assertOk();

        $reg->refresh();
        expect($reg->payment_status)->toBe('failed');
    });

    it('decrements spots on successful payment', function () {
        $t = Tournament::factory()->create(['spots_left' => 10]);
        $reg = TournamentRegistration::factory()->forTournament($t)->create([
            'confirmation_code' => 'GO-2026-TEST3',
            'payment_status' => 'processing',
        ]);

        $this->post(route('webhooks.duitku'), [
            'merchantOrderId' => 'GO-2026-TEST3',
            'resultCode' => '00',
        ])->assertOk();

        $t->refresh();
        expect($t->spots_left)->toBe(9);
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
