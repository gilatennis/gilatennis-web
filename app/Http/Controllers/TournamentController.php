<?php

namespace App\Http\Controllers;

use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Services\LiveScoreService;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function __construct(private LiveScoreService $liveScores) {}

    public function show(Tournament $tournament): Response
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
            'liveMatches' => $this->liveScores->liveMatches($tournament),
        ]);
    }

    public function live(Tournament $tournament): Response
    {
        abort_if($this->isHidden($tournament), 404);

        return Inertia::render('Tournaments/Live', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            'divisions' => $tournament->divisions()->get(['id', 'name']),
            'liveMatches' => $this->liveScores->liveMatches($tournament),
            'recentResults' => $this->liveScores->recentResults($tournament),
        ]);
    }

    /**
     * A draft was never announced and a canceled-and-over meet is gone, so
     * neither should expose a live board.
     */
    private function isHidden(Tournament $tournament): bool
    {
        return $tournament->status === TournamentStatus::Draft
            || $tournament->isCanceledAndOver();
    }
}
