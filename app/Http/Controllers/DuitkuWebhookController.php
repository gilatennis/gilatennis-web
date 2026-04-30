<?php

namespace App\Http\Controllers;

use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\TournamentRegistration;
use Illuminate\Http\Request;

class DuitkuWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'merchantCode' => ['required', 'string'],
            'amount' => ['required'],
            'merchantOrderId' => ['required', 'string'],
            'resultCode' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $calcSignature = md5(
            $request->merchantCode.$request->amount.$request->merchantOrderId.config('services.duitku.api_key')
        );

        if ($request->signature !== $calcSignature) {
            return response()->json(['status' => 'invalid signature'], 403);
        }

        $registration = TournamentRegistration::where('confirmation_code', $request->merchantOrderId)->first();

        if (! $registration) {
            return response()->json(['status' => 'registration not found'], 404);
        }

        if ($request->resultCode === '00') {
            $registration->markAsPaid($request->reference ?? '');
            $registration->tournament->decrement('spots_left');
            PaymentCompleted::dispatch($registration);
        } else {
            $registration->markAsFailed();
            PaymentFailed::dispatch($registration);
        }

        return response()->json(['status' => 'ok']);
    }
}
