<?php

use App\Enums\PaymentEventSource;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use App\Services\TournamentPaymentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('services.duitku.api_key', 'testapikey');
    app()->forgetInstance(DuitkuService::class);
});

/**
 * The Duitku POP callback amount is the rupiah amount (amount_cents / 100),
 * not amount_cents.
 */
function webhookAmount(TournamentRegistration $registration): string
{
    return (string) (int) ($registration->amount_cents / 100);
}

function webhookSignature(string $merchantCode, string $amount, string $merchantOrderId): string
{
    return hash_hmac('sha256', $merchantCode.$amount.$merchantOrderId, 'testapikey');
}

function legacyWebhookSignature(string $merchantCode, string $amount, string $merchantOrderId): string
{
    return md5($merchantCode.$amount.$merchantOrderId.'testapikey');
}

function webhookPayload(TournamentRegistration $registration, string $resultCode = '00'): array
{
    $merchantCode = 'TESTMERCHANT';
    $amount = webhookAmount($registration);
    $merchantOrderId = $registration->confirmation_code;

    return [
        'merchantCode' => $merchantCode,
        'amount' => $amount,
        'merchantOrderId' => $merchantOrderId,
        'resultCode' => $resultCode,
        'signature' => webhookSignature($merchantCode, $amount, $merchantOrderId),
        'reference' => 'DK-TESTREF123',
    ];
}

describe('Duitku Webhook', function () {
    it('is reachable without a CSRF token', function () {
        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'pending',
            'amount_cents' => 50000,
        ]);

        $response = $this->post(route('webhooks.duitku'), webhookPayload($registration, '00'));

        expect($response->status())->not->toBe(419);
        $response->assertOk();
    });

    it('marks registration as paid on success callback', function () {
        Event::fake([PaymentCompleted::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'pending',
            'amount_cents' => 50000,
        ]);

        $this->postJson(route('webhooks.duitku'), webhookPayload($registration, '00'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $registration->refresh();
        expect($registration->payment_status)->toBe('paid');
        expect($registration->payment_reference)->toBe('DK-TESTREF123');

        Event::assertDispatched(PaymentCompleted::class);

        $this->assertDatabaseHas('payment_events', [
            'tournament_registration_id' => $registration->id,
            'source' => PaymentEventSource::Webhook->value,
            'signature_valid' => true,
            'signature_scheme' => 'hmac_sha256',
            'result_code' => '00',
            'status_before' => 'pending',
            'status_after' => 'paid',
        ]);
    });

    it('accepts the legacy md5 signature used by our merchant account', function () {
        Event::fake([PaymentCompleted::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'processing',
            'amount_cents' => 50000,
        ]);

        $merchantCode = 'TESTMERCHANT';
        $amount = webhookAmount($registration);

        $this->postJson(route('webhooks.duitku'), [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $registration->confirmation_code,
            'resultCode' => '00',
            'reference' => 'DK-LEGACY-REF',
            'signature' => legacyWebhookSignature($merchantCode, $amount, $registration->confirmation_code),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $registration->refresh();
        expect($registration->payment_status)->toBe('paid');
        expect($registration->payment_reference)->toBe('DK-LEGACY-REF');

        $this->assertDatabaseHas('payment_events', [
            'tournament_registration_id' => $registration->id,
            'source' => PaymentEventSource::Webhook->value,
            'signature_valid' => true,
            'signature_scheme' => 'legacy_md5',
            'result_code' => '00',
            'status_after' => 'paid',
        ]);
    });

    it('marks registration as failed on failure callback', function () {
        Event::fake([PaymentFailed::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'pending',
            'amount_cents' => 50000,
        ]);

        $this->postJson(route('webhooks.duitku'), webhookPayload($registration, '01'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $registration->refresh();
        expect($registration->payment_status)->toBe('failed');

        Event::assertDispatched(PaymentFailed::class);
    });

    it('rejects when neither signature scheme matches', function () {
        $registration = TournamentRegistration::factory()->create(['amount_cents' => 50000]);

        $payload = webhookPayload($registration, '00');
        $payload['signature'] = str_repeat('a', 64);

        $this->postJson(route('webhooks.duitku'), $payload)
            ->assertStatus(403)
            ->assertJson(['status' => 'invalid signature']);

        expect($registration->refresh()->payment_status)->not->toBe('paid');
    });

    it('rejects callback with invalid signature', function () {
        $registration = TournamentRegistration::factory()->create(['amount_cents' => 50000]);

        $payload = webhookPayload($registration, '00');
        $payload['signature'] = 'invalidsignature';

        $this->postJson(route('webhooks.duitku'), $payload)
            ->assertStatus(403)
            ->assertJson(['status' => 'invalid signature']);
    });

    it('does not settle when the callback amount does not match the registration', function () {
        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'processing',
            'amount_cents' => 50000,
        ]);

        $merchantCode = 'TESTMERCHANT';
        $tamperedAmount = '1';
        $signature = webhookSignature($merchantCode, $tamperedAmount, $registration->confirmation_code);

        $this->postJson(route('webhooks.duitku'), [
            'merchantCode' => $merchantCode,
            'amount' => $tamperedAmount,
            'merchantOrderId' => $registration->confirmation_code,
            'resultCode' => '00',
            'signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'amount mismatch']);

        expect($registration->refresh()->payment_status)->toBe('processing');

        $this->assertDatabaseHas('payment_events', [
            'tournament_registration_id' => $registration->id,
            'source' => PaymentEventSource::Webhook->value,
            'result_code' => '00',
        ]);
    });

    it('is idempotent when the success callback is replayed', function () {
        Event::fake([PaymentCompleted::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'pending',
            'amount_cents' => 50000,
        ]);

        $payload = webhookPayload($registration, '00');

        $this->postJson(route('webhooks.duitku'), $payload)->assertOk();
        $this->postJson(route('webhooks.duitku'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'already processed']);

        expect($registration->refresh()->payment_status)->toBe('paid');
        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
        expect($registration->paymentEvents()->count())->toBe(1);
    });

    it('settles exactly once across repeated settlements from different sources', function () {
        Event::fake([PaymentCompleted::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'processing',
            'amount_cents' => 50000,
        ]);

        $service = app(TournamentPaymentService::class);

        $service->markPaid($registration, 'REF-A', PaymentEventSource::Webhook);
        $service->markPaid($registration, 'REF-A', PaymentEventSource::Admin);
        $service->markPaid($registration, 'REF-A', PaymentEventSource::System);

        $registration->refresh();

        expect($registration->payment_status)->toBe('paid');
        expect($registration->paymentEvents()->count())->toBe(1);
        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
    });

    it('returns 404 for unknown merchantOrderId', function () {
        $merchantCode = 'TESTMERCHANT';
        $amount = '500';
        $merchantOrderId = 'UNKNOWN-ORDER-ID';

        $this->postJson(route('webhooks.duitku'), [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $merchantOrderId,
            'resultCode' => '00',
            'signature' => webhookSignature($merchantCode, $amount, $merchantOrderId),
        ])->assertStatus(404)
            ->assertJson(['status' => 'registration not found']);
    });

    it('preserves existing payment_reference when webhook omits reference', function () {
        Event::fake([PaymentCompleted::class]);

        $registration = TournamentRegistration::factory()->create([
            'payment_status' => 'processing',
            'amount_cents' => 50000,
            'payment_reference' => 'DS18388265SG3TA627PUJKQ7',
        ]);

        $payload = webhookPayload($registration, '00');
        unset($payload['reference']);

        $this->postJson(route('webhooks.duitku'), $payload)
            ->assertOk();

        $registration->refresh();
        expect($registration->payment_status)->toBe('paid');
        expect($registration->payment_reference)->toBe('DS18388265SG3TA627PUJKQ7');

        Event::assertDispatched(PaymentCompleted::class);
    });
});
