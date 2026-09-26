<?php

namespace App\Http\Controllers;

use App\Enums\PaymentEventSource;
use App\Models\TournamentRegistration;
use App\Services\TournamentPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DuitkuReturnController extends Controller
{
    public function __construct(
        private TournamentPaymentService $payments,
    ) {}

    public function handle(Request $request): RedirectResponse|Response
    {
        $registration = TournamentRegistration::where('confirmation_code', $request->merchantOrderId)->firstOrFail();

        $resultCode = (string) $request->resultCode;
        $logger = Log::channel('payments');

        $context = [
            'merchant_order_id' => (string) $request->merchantOrderId,
            'result_code' => $resultCode,
            'reference' => $request->reference,
            'payment_status' => $registration->payment_status,
            'ip' => $request->ip(),
        ];

        // Duitku docs: the redirect URL carries no signature and can be edited by
        // the customer, so it must never mutate payment status. Settlement is
        // owned exclusively by the signed webhook.
        $this->payments->record($registration, PaymentEventSource::Return, [
            'raw_payload' => $request->query(),
            'signature_valid' => false,
            'result_code' => $resultCode,
            'status_before' => $registration->payment_status,
            'status_after' => $registration->payment_status,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($resultCode === '00' && $registration->payment_status !== 'paid') {
            $logger->warning('Duitku return reported success but status is not settled — awaiting verified webhook', $context);
        } else {
            $logger->info('Duitku return handled', $context);
        }

        if ($registration->payment_status === 'paid') {
            return redirect()->route('tournaments.register.success', $registration->confirmation_code);
        }

        if ($registration->payment_status === 'failed') {
            return redirect()->route('tournaments.register.failed', $registration->confirmation_code);
        }

        return Inertia::render('Tournaments/PaymentPending', [
            'registration' => $registration->load('tournament'),
        ]);
    }
}
