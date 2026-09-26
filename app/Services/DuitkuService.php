<?php

namespace App\Services;

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\TournamentRegistration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $transactionFee = (int) (($registration->transaction_fee_cents ?? 0) / 100);
        $registrationAmount = $paymentAmount - $transactionFee;
        $divisionName = ($registration->relationLoaded('division') && $registration->division) ? $registration->division->name : 'Registration';

        $timestamp = (string) (int) (microtime(true) * 1000);
        $signature = hash('sha256', $this->merchantCode.$timestamp.$this->apiKey);

        $nameParts = explode(' ', $registration->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $itemDetails = [
            [
                'name' => $registration->tournament->name.' - '.$divisionName,
                'price' => $registrationAmount,
                'quantity' => 1,
            ],
        ];

        if ($transactionFee > 0) {
            $itemDetails[] = [
                'name' => 'Transaction Fee',
                'price' => $transactionFee,
                'quantity' => 1,
            ];
        }

        $payload = [
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => "Tournament Registration: {$registration->tournament->name}",
            'additionalParam' => '',
            'merchantUserInfo' => '',
            'customerVaName' => $registration->name,
            'phoneNumber' => $registration->phone,
            'itemDetails' => $itemDetails,
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
            Log::channel('payments')->error('Duitku invoice creation failed', [
                'merchant_order_id' => $merchantOrderId,
                'http_status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException(
                'Duitku invoice creation failed: '.($data['statusMessage'] ?? "status={$statusCode}, no paymentUrl")
            );
        }

        $reference = $data['reference'] ?? self::parseReferenceFromUrl($paymentUrl) ?? '';

        if ($reference === '') {
            Log::channel('payments')->error('Duitku invoice missing reference', [
                'merchant_order_id' => $merchantOrderId,
                'http_status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Duitku invoice response missing reference');
        }

        Log::channel('payments')->info('Duitku invoice created', [
            'merchant_order_id' => $merchantOrderId,
            'reference' => $reference,
            'amount' => $paymentAmount,
            'status_code' => $statusCode,
        ]);

        return new DuitkuInvoiceResponse(
            merchantCode: $data['merchantCode'] ?? '',
            reference: $reference,
            paymentUrl: $paymentUrl,
            statusCode: $statusCode,
            statusMessage: $data['statusMessage'] ?? '',
        );
    }

    /**
     * Duitku POP callback signature per the docs:
     * HMAC_SHA256(merchantCode + amount + merchantOrderId, merchantKey).
     *
     * Note: our merchant account (sandbox, verified) actually sends the legacy
     * MD5 signature — see {@see legacyCallbackSignature()}. Both are accepted.
     */
    public function callbackSignature(string $merchantCode, string $amount, string $merchantOrderId): string
    {
        return hash_hmac('sha256', $merchantCode.$amount.$merchantOrderId, $this->apiKey);
    }

    /**
     * Legacy v1 callback signature still used by our merchant account:
     * md5(merchantCode + amount + merchantOrderId + apiKey).
     */
    public function legacyCallbackSignature(string $merchantCode, string $amount, string $merchantOrderId): string
    {
        return md5($merchantCode.$amount.$merchantOrderId.$this->apiKey);
    }

    public static function parseReferenceFromUrl(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return null;
        }

        foreach (explode('&', $query) as $pair) {
            if (str_starts_with($pair, 'reference=')) {
                $value = substr($pair, strlen('reference='));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15);
    }
}
