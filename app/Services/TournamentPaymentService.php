<?php

namespace App\Services;

use App\Enums\PaymentEventSource;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\PaymentEvent;
use App\Models\TournamentRegistration;
use Illuminate\Support\Facades\DB;

/**
 * Single, idempotent entry point for recording payment activity and settling a
 * tournament registration. Every settlement is appended to the payment_events
 * audit trail so stuck or disputed payments can be reconstructed.
 */
class TournamentPaymentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(TournamentRegistration $registration, PaymentEventSource $source, array $attributes = []): PaymentEvent
    {
        return $registration->paymentEvents()->create([
            'source' => $source,
            'raw_payload' => $attributes['raw_payload'] ?? null,
            'signature_valid' => $attributes['signature_valid'] ?? false,
            'signature_scheme' => $attributes['signature_scheme'] ?? null,
            'result_code' => $attributes['result_code'] ?? null,
            'status_before' => $attributes['status_before'] ?? null,
            'status_after' => $attributes['status_after'] ?? null,
            'ip' => $attributes['ip'] ?? null,
            'user_agent' => $attributes['user_agent'] ?? null,
        ]);
    }

    /**
     * Atomically settle a registration as paid. The row is locked for the
     * duration of the write so concurrent Duitku retries settle exactly once
     * (one status transition, one audit row, one PaymentCompleted dispatch).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function markPaid(TournamentRegistration $registration, ?string $reference, PaymentEventSource $source, array $attributes = []): void
    {
        $settled = DB::transaction(function () use ($registration, $reference, $source, $attributes): bool {
            $locked = TournamentRegistration::whereKey($registration->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->payment_status === 'paid') {
                return false;
            }

            $statusBefore = $locked->payment_status;

            $locked->markAsPaid($reference);

            $this->record($locked, $source, array_merge($attributes, [
                'status_before' => $statusBefore,
                'status_after' => 'paid',
            ]));

            return true;
        });

        if (! $settled) {
            return;
        }

        $registration->refresh();

        PaymentCompleted::dispatch($registration);
    }

    /**
     * Atomically settle a registration as failed, guarded the same way as
     * {@see markPaid()} so concurrent callbacks cannot double-record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function markFailed(TournamentRegistration $registration, PaymentEventSource $source, array $attributes = []): void
    {
        $settled = DB::transaction(function () use ($registration, $source, $attributes): bool {
            $locked = TournamentRegistration::whereKey($registration->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->payment_status === 'failed') {
                return false;
            }

            $statusBefore = $locked->payment_status;

            $locked->markAsFailed();

            $this->record($locked, $source, array_merge($attributes, [
                'status_before' => $statusBefore,
                'status_after' => 'failed',
            ]));

            return true;
        });

        if (! $settled) {
            return;
        }

        $registration->refresh();

        PaymentFailed::dispatch($registration);
    }
}
