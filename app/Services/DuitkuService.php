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
        $timestamp = (int) (microtime(true) * 1000);
        $signature = hash('sha256', $this->merchantCode.$timestamp.$this->apiKey);

        $nameParts = explode(' ', $registration->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $payload = [
            'paymentAmount' => $registration->amount_cents,
            'merchantOrderId' => $registration->confirmation_code,
            'productDetails' => "Tournament Registration: {$registration->tournament->name}",
            'additionalParam' => '',
            'merchantUserInfo' => '',
            'customerVaName' => $registration->name,
            'phoneNumber' => $registration->phone,
            'itemDetails' => [
                [
                    'name' => $registration->tournament->name.' - '.ucfirst($registration->event_type),
                    'price' => $registration->amount_cents,
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
        ];

        $response = $this->http()->withHeaders([
            'Content-Type' => 'application/json',
            'x-duitku-merchantcode' => $this->merchantCode,
            'x-duitku-signature' => $signature,
            'x-duitku-timestamp' => (string) $timestamp,
        ])->post($this->apiUrl.'/api/merchant/createInvoice', $payload);

        $data = $response->json();

        return new DuitkuInvoiceResponse(
            merchantCode: $data['merchantCode'] ?? '',
            reference: $data['reference'] ?? '',
            paymentUrl: $data['paymentUrl'] ?? '',
            statusCode: $data['statusCode'] ?? '',
            statusMessage: $data['statusMessage'] ?? '',
        );
    }

    public function getSignature(string $timestamp): string
    {
        return hash('sha256', $this->merchantCode.$timestamp.$this->apiKey);
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15);
    }
}
