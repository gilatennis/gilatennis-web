<?php

namespace App\Services;

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\TournamentRegistration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DuitkuService
{
    public function __construct(
        private string $merchantCode,
        private string $apiKey,
        private string $apiUrl,
    ) {}

    public function createInvoice(TournamentRegistration $registration): DuitkuInvoiceResponse
    {
        $merchantOrderId = $registration->confirmation_code;
        $paymentAmount = (int) ($registration->amount_cents / 100);

        $timestamp = (string) (int) (microtime(true) * 1000);
        $signature = hash('sha256', $this->merchantCode.$timestamp.$this->apiKey);

        $nameParts = explode(' ', $registration->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $payload = [
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => "Tournament Registration: {$registration->tournament->name}",
            'additionalParam' => '',
            'merchantUserInfo' => '',
            'customerVaName' => $registration->name,
            'phoneNumber' => $registration->phone,
            'itemDetails' => [
                [
                    'name' => $registration->tournament->name.' - '.($registration->relationLoaded('division') ? $registration->division->name : 'Registration'),
                    'price' => $paymentAmount,
                    'quantity' => 1,
                ],
            ],
            'customerDetail' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $registration->email,
                'phoneNumber' => $registration->phone,
                'billingAddress' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'address' => '',
                    'city' => '',
                    'postalCode' => '',
                    'phone' => $registration->phone,
                    'countryCode' => 'ID',
                ],
                'shippingAddress' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'address' => '',
                    'city' => '',
                    'postalCode' => '',
                    'phone' => $registration->phone,
                    'countryCode' => 'ID',
                ],
            ],
            'callbackUrl' => route('webhooks.duitku'),
            'returnUrl' => route('payments.duitku.return'),
            'expiryPeriod' => 60,
            'paymentMethod' => '',
        ];

        $response = $this->http()->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-duitku-signature' => $signature,
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-merchantcode' => $this->merchantCode,
        ])->post($this->apiUrl.'/api/merchant/createInvoice', $payload);

        $response->throw();

        $data = $response->json();

        $statusCode = $data['statusCode'] ?? '';
        $paymentUrl = $data['paymentUrl'] ?? '';

        if ($statusCode !== '00' || empty($paymentUrl)) {
            \Log::error('Duitku invoice creation failed', [
                'http_status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                'Duitku invoice creation failed: '.($data['statusMessage'] ?? "status={$statusCode}, no paymentUrl")
            );
        }

        return new DuitkuInvoiceResponse(
            merchantCode: $data['merchantCode'] ?? '',
            reference: $data['reference'] ?? '',
            paymentUrl: $paymentUrl,
            statusCode: $statusCode,
            statusMessage: $data['statusMessage'] ?? '',
        );
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15);
    }
}
