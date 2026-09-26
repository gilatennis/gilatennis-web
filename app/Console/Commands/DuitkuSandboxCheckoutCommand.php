<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Settings;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('payments:sandbox-checkout
    {--amount=10000 : Division fee in whole rupiah}
    {--wait=10 : Minutes to wait for the webhook settlement}
    {--force : Run even when the configured Duitku URL is not sandbox}')]
#[Description('Create a throwaway tournament + registration, generate a real Duitku sandbox invoice, then wait for you to pay it and verify the webhook settles the registration.')]
class DuitkuSandboxCheckoutCommand extends Command
{
    public function handle(DuitkuService $duitku): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $apiUrl = (string) config('services.duitku.api_url');

        if (! $this->option('force') && ! str_contains($apiUrl, 'sandbox')) {
            $this->error("Duitku API URL [{$apiUrl}] is not a sandbox endpoint. Use --force to override.");

            return self::FAILURE;
        }

        $fee = (int) $this->option('amount');
        $feeCents = $fee * 100;
        $transactionFeeCents = Settings::getTransactionFeeCents();
        $amountCents = $feeCents + $transactionFeeCents;

        $suffix = Str::upper(Str::random(5));

        $tournament = Tournament::factory()->create([
            'name' => "Sandbox Checkout {$suffix}",
            'slug' => 'sandbox-checkout-'.Str::lower($suffix),
            'status' => 'open',
        ]);

        $division = Division::factory()->forTournament($tournament)->singles()->create([
            'draw_size' => 32,
            'fee' => $fee,
            'sort_order' => 0,
            'gender' => null,
        ]);

        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'confirmation_code' => TournamentRegistration::generateConfirmationCode(),
            'name' => 'Sandbox Tester',
            'email' => 'sandbox-'.Str::lower($suffix).'@example.test',
            'phone' => '08120000000',
            'date_of_birth' => '2000-01-01',
            'payment_status' => 'pending',
            'amount_cents' => $amountCents,
            'transaction_fee_cents' => $transactionFeeCents,
            'currency' => 'IDR',
        ]);

        $registration->setRelation('tournament', $tournament);
        $registration->setRelation('division', $division);

        $this->info('Creating Duitku sandbox invoice…');

        try {
            $invoice = $duitku->createInvoice($registration);
        } catch (\Throwable $e) {
            $this->error('Invoice creation failed: '.$e->getMessage());
            $this->line('Check storage/logs/payments.log for details.');

            return self::FAILURE;
        }

        $registration->update([
            'payment_reference' => $invoice->reference,
            'payment_url' => $invoice->paymentUrl,
            'payment_status' => 'processing',
        ]);

        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Confirmation code', $registration->confirmation_code],
            ['Amount (IDR)', number_format($fee)],
            ['Duitku reference', $invoice->reference],
            ['Status', $registration->payment_status],
        ]);

        $this->newLine();
        $this->line('Open this URL and complete the payment in the Duitku sandbox UI:');
        $this->line('  '.$invoice->paymentUrl);
        $this->newLine();

        $paymentUrl = route('payments.duitku.return');
        $this->line("Return URL: {$paymentUrl}?merchantOrderId={$registration->confirmation_code}&resultCode=00&reference=<ref>");
        $this->line('Watch logs: tail -f storage/logs/payments.log   (or: make logs)');
        $this->newLine();

        $reference = $registration->payment_reference;
        $waitMinutes = max(1, (int) $this->option('wait'));
        $deadline = now()->addMinutes($waitMinutes);

        $this->line("Waiting up to {$waitMinutes} minute(s) for the webhook settlement…");

        while (now()->lessThan($deadline)) {
            $registration->refresh();

            if ($registration->payment_status === 'paid') {
                $this->newLine();
                $this->info('SUCCESS — registration settled as PAID.');
                $this->printAuditTrail($registration);

                return self::SUCCESS;
            }

            if ($registration->payment_status === 'failed') {
                $this->newLine();
                $this->error('Registration settled as FAILED (resultCode was not 00).');
                $this->printAuditTrail($registration);

                return self::FAILURE;
            }

            sleep(3);
        }

        $this->newLine();
        $this->error('Timed out — no settlement received.');
        $this->line('If you paid but the status is still "processing", the webhook did not settle it.');
        $this->line('Re-run the signature diagnostics in storage/logs/payments.log, then retry.');
        $this->printAuditTrail($registration);

        return self::FAILURE;
    }

    private function printAuditTrail(TournamentRegistration $registration): void
    {
        $events = $registration->paymentEvents()->get();

        if ($events->isEmpty()) {
            $this->line('No payment_events recorded.');

            return;
        }

        $this->newLine();
        $this->line('payment_events:');
        $this->table(
            ['Source', 'Signature valid', 'Result', 'Before', 'After', 'At'],
            $events->map(fn ($e) => [
                $e->source->value,
                $e->signature_valid ? 'yes' : 'no',
                $e->result_code ?? '—',
                $e->status_before ?? '—',
                $e->status_after ?? '—',
                $e->created_at?->toDateTimeString(),
            ])->all(),
        );
    }
}
