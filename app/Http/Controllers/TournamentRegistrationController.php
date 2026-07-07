<?php

namespace App\Http\Controllers;

use App\Http\Requests\TournamentRegistrationRequest;
use App\Models\Division;
use App\Models\Settings;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;
use App\Services\DuitkuService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private DuitkuService $duitku,
    ) {}

    public function create(Tournament $tournament)
    {
        $tournament->load('divisions');

        $hasOpenDivisions = $tournament->divisions->contains(fn (Division $d) => $d->spots_available > 0);

        abort_if(! $hasOpenDivisions, 403, 'Tournament is sold out.');

        return Inertia::render('Tournaments/Register', [
            'tournament' => $tournament,
            'divisions' => $tournament->divisions,
            'wa_number' => Settings::getWaNumber(),
        ]);
    }

    public function store(TournamentRegistrationRequest $request, Tournament $tournament)
    {
        $division = $tournament->divisions()->where('id', $request->division_id)->firstOrFail();

        abort_if($division->spots_available <= 0, 403, 'Division is sold out.');

        // 1. Find or create guest user from first player
        $players = $request->players;
        $firstPlayer = $players[0];

        $user = User::firstOrCreate(
            ['email' => $firstPlayer['email']],
            [
                'name' => $firstPlayer['name'],
                'is_guest' => true,
                'password' => bcrypt('guest-'.$firstPlayer['email']),
            ],
        );

        if ($user->wasRecentlyCreated === false) {
            $user->update(['is_guest' => true]);
        }

        // 2. Check for existing paid registration (prevent duplicates)
        $existing = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('division_id', $division->id)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if ($existing) {
            return redirect()->route('tournaments.register.success', $existing->confirmation_code);
        }

        // 3. Create team for team divisions
        $team = null;
        if ($division->participation_category === 'team') {
            $team = Team::create(['name' => $request->team_name]);
        }

        // 4. Calculate amount from division fee
        $amountCents = (int) ($division->fee * 100);

        // 5. Create registration
        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'team_id' => $team?->id,
            'confirmation_code' => TournamentRegistration::generateConfirmationCode(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth ?? '2000-01-01',
            'tshirt_size' => $request->tshirt_size ?? 'M',
            'dietary' => $request->dietary,
            'amount_cents' => $amountCents,
            'currency' => 'IDR',
            'payment_status' => 'pending',
        ]);

        // 6. Create player records
        foreach ($players as $index => $player) {
            TournamentRegistrationPlayer::create([
                'tournament_registration_id' => $registration->id,
                'tournament_id' => $tournament->id,
                'team_id' => $team?->id,
                'name' => $player['name'],
                'email' => $player['email'],
                'gender' => $player['gender'] ?? null,
                'phone' => $player['phone'],
                'date_of_birth' => $player['date_of_birth'] ?? '2000-01-01',
                'rating_type' => $player['rating_type'] ?? null,
                'rating_value' => $player['rating_value'] ?? null,
                'level' => $player['level'] ?? null,
                'tshirt_size' => $player['tshirt_size'] ?? 'M',
                'dietary' => $player['dietary'] ?? null,
                'sort_order' => $index,
            ]);
        }

        // 7. Create Duitku invoice
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

    private function calculateAmount(Division $division): int
    {
        return (int) ($division->fee * 100);
    }
}
