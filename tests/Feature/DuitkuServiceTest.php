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
});
