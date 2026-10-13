<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentRegistrationRequest;
use App\Models\Division;
use App\Models\Settings;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\DuitkuService;
use App\Services\TournamentRegistrationService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private DuitkuService $duitku,
        private TournamentRegistrationService $registrations,
    ) {}

    public function create(Tournament $tournament)
    {
        abort_if($tournament->isCanceled(), 403, 'Tournament is canceled.');

        $tournament->load('divisions.playerFieldRequirements');

        $hasOpenDivisions = $tournament->divisions->contains(fn (Division $d) => $d->spots_available > 0);

        abort_if(! $hasOpenDivisions, 403, 'Tournament is sold out.');

        $preselectedDivisionId = null;
        $requested = request('division');

        if ($requested !== null) {
            $div = $tournament->divisions->firstWhere('id', (int) $requested);

            if ($div && $div->spots_available > 0) {
                $preselectedDivisionId = $div->id;
            }
        }

        return Inertia::render('Tournaments/Register', [
            'tournament' => $tournament,
            'divisions' => $tournament->divisions,
            'preselectedDivisionId' => $preselectedDivisionId,
            'transactionFee' => Settings::getTransactionFee(),
        ]);
    }

    public function store(TournamentRegistrationRequest $request, Tournament $tournament)
    {
        abort_if($tournament->isCanceled(), 403, 'Tournament is canceled.');

        $division = $tournament->divisions()->where('id', $request->division_id)->firstOrFail();

        abort_if($division->spots_available <= 0, 403, 'Division is sold out.');

        $registration = $this->registrations->create($tournament, $division, [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'team_name' => $request->team_name,
            'players' => $request->players,
        ], ['mark_paid' => false]);

        // Create Duitku invoice
        $registration->load('tournament');
        $registration->setRelation('division', $division);

        try {
            $invoice = $this->duitku->createInvoice($registration);

            $registration->update([
                'payment_reference' => $invoice->reference,
                'payment_url' => $invoice->paymentUrl,
                'payment_status' => 'processing',
            ]);

            return Inertia::location($invoice->paymentUrl);
        } catch (\Throwable $e) {
            Log::error('duitku->createInvoice', ['error' => $e]);

            $registration->markAsFailed();
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Payment service unavailable. Please try again.']);

            return redirect()->back();
        }
    }

    public function success(TournamentRegistration $registration)
    {
        if ($registration->payment_status !== 'paid') {
            return redirect()->route('tournaments.register.failed', $registration->confirmation_code);
        }

        return Inertia::render('Tournaments/RegistrationSuccess', [
            'registration' => $registration->load(['tournament', 'division', 'players', 'team']),
        ]);
    }

    public function failed(TournamentRegistration $registration)
    {
        return Inertia::render('Tournaments/RegistrationFailed', [
            'registration' => $registration->load(['tournament', 'division', 'players']),
        ]);
    }
}
