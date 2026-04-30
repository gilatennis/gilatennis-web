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
        $paymentAmount = $registration->amount_cents;
        $signature = md5($this->merchantCode.$merchantOrderId.$paymentAmount.$this->apiKey);

        $nameParts = explode(' ', $registration->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $payload = [
            'merchantCode' => $this->merchantCode,
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => "Tournament Registration: {$registration->tournament->name}",
            'additionalParam' => '',
            'merchantUserInfo' => '',
            'customerVaName' => $registration->name,
            'phoneNumber' => $registration->phone,
            'itemDetails' => [
                [
                    'name' => $registration->tournament->name.' - '.ucfirst($registration->event_type),
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
            'signature' => $signature,
        ];

        $response = $this->http()->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl.'/webapi/api/merchant/v2/inquiry', $payload);

        $data = $response->json();

        return new DuitkuInvoiceResponse(
            merchantCode: $data['merchantCode'] ?? '',
            reference: $data['reference'] ?? '',
            paymentUrl: $data['paymentUrl'] ?? '',
            statusCode: $data['statusCode'] ?? '',
            statusMessage: $data['statusMessage'] ?? '',
        );
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15);
    }
}
