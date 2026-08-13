<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Inertia\Inertia;

class TournamentController extends Controller
{
    public function show(Tournament $tournament)
    {
        abort_if($tournament->isCanceledAndOver(), 404);

        $tournament->load([
            'scheduleDays.items',
            'rules',
            'divisions' => fn ($q) => $q->with('prizes')->withCount('paidRegistrations'),
        ]);

        $registrationCount = $tournament->registrations()
            ->where('payment_status', 'paid')
            ->count();

        return Inertia::render('Tournaments/Show', [
            'tournament' => $tournament,
            'registrationCount' => $registrationCount,
        ]);
    }
}
