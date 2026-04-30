<?php

use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\TournamentRegistration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('services.duitku.api_key', 'testapikey');
});

function webhookPayload(TournamentRegistration $registration, string $resultCode = '00'): array
{
    $merchantCode = 'TESTMERCHANT';
    $amount = (string) $registration->amount_cents;
    $merchantOrderId = $registration->confirmation_code;
    $signature = md5($merchantCode.$amount.$merchantOrderId.'testapikey');

    return [
        'merchantCode' => $merchantCode,
        'amount' => $amount,
        'merchantOrderId' => $merchantOrderId,
        'resultCode' => $resultCode,
        'signature' => $signature,
        'reference' => 'DK-TESTREF123',
    ];
}

describe('Duitku Webhook', function () {
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

    it('rejects callback with invalid signature', function () {
        $registration = TournamentRegistration::factory()->create(['amount_cents' => 50000]);

        $payload = webhookPayload($registration, '00');
        $payload['signature'] = 'invalidsignature';

        $this->postJson(route('webhooks.duitku'), $payload)
            ->assertStatus(403)
            ->assertJson(['status' => 'invalid signature']);
    });

    it('returns 404 for unknown merchantOrderId', function () {
        $merchantCode = 'TESTMERCHANT';
        $amount = '50000';
        $merchantOrderId = 'UNKNOWN-ORDER-ID';
        $signature = md5($merchantCode.$amount.$merchantOrderId.'testapikey');

        $this->postJson(route('webhooks.duitku'), [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $merchantOrderId,
            'resultCode' => '00',
            'signature' => $signature,
        ])->assertStatus(404)
            ->assertJson(['status' => 'registration not found']);
    });
});
