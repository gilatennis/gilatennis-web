<?php

namespace App\Http\Controllers;

use App\Models\TournamentRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DuitkuReturnController extends Controller
{
    public function handle(Request $request)
    {
        $registration = TournamentRegistration::where('confirmation_code', $request->merchantOrderId)->firstOrFail();

        if ($registration->payment_status === 'paid') {
            return redirect()->route('tournaments.register.success', $registration->confirmation_code);
        }

        if ($registration->payment_status === 'failed') {
            return redirect()->route('tournaments.register.failed', $registration->confirmation_code);
        }

        // Still processing — show pending page
        return Inertia::render('Tournaments/PaymentPending', [
            'registration' => $registration->load('tournament'),
        ]);
    }
}
