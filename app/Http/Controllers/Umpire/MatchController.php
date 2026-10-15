<?php

namespace App\Http\Controllers\Umpire;

use App\Enums\MatchStatus;
use App\Http\Controllers\Admin\TennisMatchScoringController;
use App\Models\TennisMatch;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated umpire scoreboard. Scoring actions (point/server/undo/status)
 * are inherited verbatim from the admin controller — only the rendered page
 * differs (no admin chrome) and access is gated by the `umpire` middleware.
 */
class MatchController extends TennisMatchScoringController
{
    public function show(TennisMatch $match): Response
    {
        // An umpire may only open a match the referee has sent to court (running)
        // or one that is already finished (so it can be reopened/corrected).
        abort_unless(
            in_array($match->status, [MatchStatus::Running, MatchStatus::Completed], true),
            403,
        );

        $match->loadMissing(['state', 'division.tournament']);

        $parameters = ['match' => $match->id];
        $tournament = $match->division?->tournament;

        return Inertia::render('Umpire/Score', [
            'match' => $match->toScorePayload(),
            'endpoints' => [
                'point' => route('umpire.matches.points', $parameters),
                'server' => route('umpire.matches.server', $parameters),
                'undo' => route('umpire.matches.undo', $parameters),
                'status' => route('umpire.matches.status', $parameters),
            ],
            'listHref' => $tournament !== null
                ? route('umpire.tournaments.show', ['tournament' => $tournament->id])
                : null,
            'tournament' => $tournament !== null
                ? ['id' => $tournament->id, 'name' => $tournament->name]
                : null,
        ]);
    }
}
