<?php

namespace App\Http\Controllers;

use App\Enums\PaymentEventSource;
use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use App\Services\TournamentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DuitkuWebhookController extends Controller
{
    public function __construct(
        private DuitkuService $duitku,
        private TournamentPaymentService $payments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchantCode' => ['required', 'string'],
            'amount' => ['required', 'integer'],
            'merchantOrderId' => ['required', 'string'],
            'resultCode' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $orderId = (string) $validated['merchantOrderId'];
        $resultCode = (string) $validated['resultCode'];
        $logger = Log::channel('payments');

        $context = [
            'merchant_order_id' => $orderId,
            'result_code' => $resultCode,
            'amount' => (string) $validated['amount'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Optional defence-in-depth: whitelist Duitku's outgoing callback IPs.
        $allowedIps = config('services.duitku.callback_ips', []);

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            $logger->warning('Duitku callback rejected: ip not allowed', $context);

            return response()->json(['status' => 'forbidden'], 403);
        }

        // Duitku callback signature — accept either scheme:
        //  - HMAC_SHA256(merchantCode + amount + merchantOrderId, merchantKey) per the docs
        //  - md5(merchantCode + amount + merchantOrderId + merchantKey) — legacy v1,
        //    confirmed as what our merchant account actually sends.
        $hmacSignature = $this->duitku->callbackSignature(
            (string) $validated['merchantCode'],
            (string) $validated['amount'],
            $orderId,
        );
        $legacySignature = $this->duitku->legacyCallbackSignature(
            (string) $validated['merchantCode'],
            (string) $validated['amount'],
            $orderId,
        );

        $receivedSignature = (string) $validated['signature'];

        $signatureScheme = match (true) {
            hash_equals($hmacSignature, $receivedSignature) => 'hmac_sha256',
            hash_equals($legacySignature, $receivedSignature) => 'legacy_md5',
            default => null,
        };

        if ($signatureScheme === null) {
            $logger->warning('Duitku callback rejected: invalid signature', $context + [
                'received_signature' => $receivedSignature,
                'hmac_signature' => $hmacSignature,
                'legacy_signature' => $legacySignature,
            ]);

            return response()->json(['status' => 'invalid signature'], 403);
        }

        $context['signature_scheme'] = $signatureScheme;

        $registration = TournamentRegistration::with('division')
            ->where('confirmation_code', $orderId)
            ->first();

        if (! $registration) {
            $logger->warning('Duitku callback rejected: registration not found', $context);

            return response()->json(['status' => 'registration not found'], 404);
        }

        $expectedAmount = (int) round($registration->amount_cents / 100);

        if ((int) $validated['amount'] !== $expectedAmount) {
            $this->payments->record($registration, PaymentEventSource::Webhook, [
                'raw_payload' => $request->all(),
                'signature_valid' => true,
                'signature_scheme' => $signatureScheme,
                'result_code' => $resultCode,
                'status_before' => $registration->payment_status,
                'status_after' => $registration->payment_status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $logger->error('Duitku callback rejected: amount mismatch', $context + [
                'callback_amount' => $validated['amount'],
                'expected_amount' => $expectedAmount,
            ]);

            return response()->json(['status' => 'amount mismatch']);
        }

        if ($registration->payment_status === 'paid') {
            $logger->info('Duitku callback ignored: already paid', $context);

            return response()->json(['status' => 'already processed']);
        }

        $reference = $request->input('reference');

        $audit = [
            'raw_payload' => $request->all(),
            'signature_valid' => true,
            'signature_scheme' => $signatureScheme,
            'result_code' => $resultCode,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if ($resultCode === '00') {
            $this->payments->markPaid($registration, $reference, PaymentEventSource::Webhook, $audit);
            $logger->info('Duitku callback settled as paid', $context);
        } else {
            $this->payments->markFailed($registration, PaymentEventSource::Webhook, $audit);
            $logger->info('Duitku callback settled as failed', $context);
        }

        return response()->json(['status' => 'ok']);
    }
}
