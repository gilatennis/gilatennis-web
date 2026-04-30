<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentRegistrationRequest;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\DuitkuService;
use Inertia\Inertia;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private DuitkuService $duitku,
    ) {}

    public function create(Tournament $tournament)
    {
        abort_if($tournament->spots_left <= 0, 403, 'Tournament is sold out.');

        return Inertia::render('Tournaments/Register', [
            'tournament' => $tournament,
        ]);
    }

    public function store(TournamentRegistrationRequest $request, Tournament $tournament)
    {
        abort_if($tournament->spots_left <= 0, 403, 'Tournament is sold out.');

        // 1. Find or create guest user
        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'is_guest' => true,
                'password' => bcrypt('guest-'.$request->email),
            ],
        );

        // Upgrade existing guest or set is_guest for existing registered users
        if ($user->wasRecentlyCreated === false) {
            $user->update(['is_guest' => true]);
        }

        // 2. Check for existing paid registration (prevent duplicates)
        $existing = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if ($existing) {
            return redirect()->route('tournaments.register.success', $existing->confirmation_code);
        }

        // 3. Calculate amount
        $amountCents = $this->calculateAmount($request, $tournament);

        // 4. Create registration
        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'confirmation_code' => TournamentRegistration::generateConfirmationCode(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'rating_type' => $request->rating_type,
            'rating_value' => $request->rating_value,
            'event_type' => $request->event_type,
            'find_partner' => $request->boolean('find_partner'),
            'partner_name' => $request->partner_name,
            'partner_email' => $request->partner_email,
            'tshirt_size' => $request->tshirt_size,
            'dietary' => $request->dietary,
            'amount_cents' => $amountCents,
            'currency' => 'IDR',
            'payment_status' => 'pending',
        ]);

        // 5. Create Duitku invoice
        try {
            $invoice = $this->duitku->createInvoice($registration);

            $registration->update([
                'payment_reference' => $invoice->reference,
                'payment_url' => $invoice->paymentUrl,
                'payment_status' => 'processing',
            ]);

            return Inertia::location($invoice->paymentUrl);
        } catch (\Throwable $e) {
            $registration->markAsFailed();

            return redirect()->back()
                ->with('error', 'Payment service unavailable. Please try again.');
        }
    }

    public function success(TournamentRegistration $registration)
    {
        if ($registration->payment_status !== 'paid') {
            return redirect()->route('tournaments.register.failed', $registration->confirmation_code);
        }

        return Inertia::render('Tournaments/RegistrationSuccess', [
            'registration' => $registration->load('tournament'),
        ]);
    }

    public function failed(TournamentRegistration $registration)
    {
        return Inertia::render('Tournaments/RegistrationFailed', [
            'registration' => $registration->load('tournament'),
        ]);
    }

    private function calculateAmount(TournamentRegistrationRequest $request, Tournament $tournament): int
    {
        $base = (int) $tournament->fee;

        // Doubles add-on: IDR 25,000
        if (in_array($request->event_type, ['doubles', 'both'])) {
            $base += 25000;
        }

        // Processing fee: IDR 5,000
        $base += 5000;

        return $base;
    }
}
