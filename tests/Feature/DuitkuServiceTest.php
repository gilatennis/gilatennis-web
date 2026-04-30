<?php

use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use Illuminate\Support\Facades\Http;

describe('DuitkuService', function () {
    it('posts to the correct endpoint with MD5 signature in body', function () {
        Http::fake([
            'sandbox.duitku.com/*' => Http::response([
                'merchantCode' => 'TESTMERCHANT',
                'reference' => 'DK-TESTREF123',
                'paymentUrl' => 'https://sandbox.duitku.com/pay/DEMO123',
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
            apiUrl: 'https://sandbox.duitku.com',
        );

        $result = $service->createInvoice($registration);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $expectedSignature = md5('TESTMERCHANT'.'ORDER-001'.'50000'.'testapikey');

            return str_contains($request->url(), 'sandbox.duitku.com/webapi/api/merchant/v2/inquiry')
                && $body['merchantCode'] === 'TESTMERCHANT'
                && $body['signature'] === $expectedSignature
                && ! isset($body['timestamp'])
                && ! $request->hasHeader('x-duitku-merchantcode');
        });

        expect($result->statusCode)->toBe('00');
        expect($result->paymentUrl)->toBe('https://sandbox.duitku.com/pay/DEMO123');
    });
});
