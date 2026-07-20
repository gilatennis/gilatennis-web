<?php

use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use Illuminate\Support\Facades\Http;

describe('DuitkuService', function () {
    it('creates invoice with header-based authentication', function () {
        Http::fake([
            'api-sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'reference' => 'DK-TESTREF123',
                'paymentUrl' => 'https://api-sandbox.duitku.com/pay/DEMO123',
                'statusCode' => '00',
                'statusMessage' => 'Invoice created',
            ], 200),
        ]);

        $registration = TournamentRegistration::factory()->create([
            'confirmation_code' => 'ORDER-001',
            'amount_cents' => 50000,
        ]);

        $service = new DuitkuService(
            merchantCode: 'TESTMERCHANT',
            apiKey: 'testapikey',
            apiUrl: 'https://api-sandbox.duitku.com',
        );

        $result = $service->createInvoice($registration);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $timestamp = $request->header('x-duitku-timestamp')[0];
            $expectedSignature = hash('sha256', 'TESTMERCHANT'.$timestamp.'testapikey');

            return str_contains($request->url(), 'api-sandbox.duitku.com/api/merchant/createInvoice')
                && $request->hasHeader('x-duitku-signature')
                && $request->hasHeader('x-duitku-timestamp')
                && $request->hasHeader('x-duitku-merchantcode')
                && $request->header('x-duitku-signature')[0] === $expectedSignature
                && ! isset($body['merchantCode'])
                && ! isset($body['signature']);
        });

        expect($result->statusCode)->toBe('00');
        expect($result->paymentUrl)->toBe('https://api-sandbox.duitku.com/pay/DEMO123');
    });

    it('falls back to reference in paymentUrl when response omits top-level reference', function () {
        Http::fake([
            'api-sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'paymentUrl' => 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS18388265SG3TA627PUJKQ7',
                'statusCode' => '00',
                'statusMessage' => 'SUCCESS',
            ], 200),
        ]);

        $registration = TournamentRegistration::factory()->create([
            'confirmation_code' => 'ORDER-002',
            'amount_cents' => 50000,
        ]);

        $service = new DuitkuService(
            merchantCode: 'TESTMERCHANT',
            apiKey: 'testapikey',
            apiUrl: 'https://api-sandbox.duitku.com',
        );

        $result = $service->createInvoice($registration);

        expect($result->reference)->toBe('DS18388265SG3TA627PUJKQ7');
        expect($result->paymentUrl)->toBe('https://app-sandbox.duitku.com/redirect_checkout?reference=DS18388265SG3TA627PUJKQ7');
    });

    it('throws when reference is missing and not embedded in paymentUrl', function () {
        Http::fake([
            'api-sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'paymentUrl' => 'https://app-sandbox.duitku.com/redirect_checkout',
                'statusCode' => '00',
                'statusMessage' => 'SUCCESS',
            ], 200),
        ]);

        $registration = TournamentRegistration::factory()->create([
            'confirmation_code' => 'ORDER-003',
            'amount_cents' => 50000,
        ]);

        $service = new DuitkuService(
            merchantCode: 'TESTMERCHANT',
            apiKey: 'testapikey',
            apiUrl: 'https://api-sandbox.duitku.com',
        );

        $service->createInvoice($registration);
    })->throws(RuntimeException::class, 'Duitku invoice response missing reference');

    it('emits two itemDetails lines when transaction fee is set', function () {
        Http::fake([
            'api-sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'reference' => 'DK-TX-FEE-REF',
                'paymentUrl' => 'https://api-sandbox.duitku.com/pay/DEMOTX',
                'statusCode' => '00',
                'statusMessage' => 'SUCCESS',
            ], 200),
        ]);

        $registration = TournamentRegistration::factory()->create([
            'confirmation_code' => 'ORDER-TX-001',
            'amount_cents' => 5500000,
            'transaction_fee_cents' => 500000,
        ]);
        $registration->load('tournament', 'division');

        $service = new DuitkuService(
            merchantCode: 'TESTMERCHANT',
            apiKey: 'testapikey',
            apiUrl: 'https://api-sandbox.duitku.com',
        );

        $result = $service->createInvoice($registration);

        $captured = null;
        Http::assertSent(function ($request) use (&$captured) {
            $captured = $request->data();

            return true;
        });

        expect($captured)->not->toBeNull();
        expect($captured['paymentAmount'])->toBe(55000);
        expect($captured['itemDetails'])->toBeArray()->toHaveCount(2);
        expect($captured['itemDetails'][0]['price'])->toBe(50000);
        expect($captured['itemDetails'][0]['quantity'])->toBe(1);
        expect($captured['itemDetails'][1]['name'])->toBe('Transaction Fee');
        expect($captured['itemDetails'][1]['price'])->toBe(5000);
        expect($captured['itemDetails'][1]['quantity'])->toBe(1);
        expect($result->statusCode)->toBe('00');
    });

    it('emits a single itemDetails line when no transaction fee', function () {
        Http::fake([
            'api-sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'reference' => 'DK-NO-TX-REF',
                'paymentUrl' => 'https://api-sandbox.duitku.com/pay/DEMONO',
                'statusCode' => '00',
                'statusMessage' => 'SUCCESS',
            ], 200),
        ]);

        $registration = TournamentRegistration::factory()->create([
            'confirmation_code' => 'ORDER-NO-TX',
            'amount_cents' => 5000000,
            'transaction_fee_cents' => 0,
        ]);
        $registration->load('tournament', 'division');

        $service = new DuitkuService(
            merchantCode: 'TESTMERCHANT',
            apiKey: 'testapikey',
            apiUrl: 'https://api-sandbox.duitku.com',
        );

        $service->createInvoice($registration);

        $captured = null;
        Http::assertSent(function ($request) use (&$captured) {
            $captured = $request->data();

            return true;
        });

        expect($captured)->not->toBeNull();
        expect($captured['paymentAmount'])->toBe(50000);
        expect($captured['itemDetails'])->toBeArray()->toHaveCount(1);
        expect($captured['itemDetails'][0]['price'])->toBe(50000);
    });
});

describe('DuitkuService::parseReferenceFromUrl', function () {
    it('extracts reference from a query string', function () {
        expect(DuitkuService::parseReferenceFromUrl(
            'https://app-sandbox.duitku.com/redirect_checkout?reference=ABC123&other=foo'
        ))->toBe('ABC123');
    });

    it('returns null when no reference param is present', function () {
        expect(DuitkuService::parseReferenceFromUrl(
            'https://app-sandbox.duitku.com/redirect_checkout?other=foo'
        ))->toBeNull();
    });

    it('returns null for urls without a query string', function () {
        expect(DuitkuService::parseReferenceFromUrl(
            'https://app-sandbox.duitku.com/redirect_checkout'
        ))->toBeNull();
    });
});
