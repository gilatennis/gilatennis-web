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

        // Duitku redirects with resultCode=00 on successful payment.
        // Handle immediately so user isn't stuck waiting for the async webhook.
        if ($registration->payment_status === 'processing' && $request->resultCode === '00') {
            $registration->markAsPaid($request->payment_reference ?? '');
            $registration->division?->decrement('spots_left');
        }

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
