<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\TennisMatchScoringController;
use App\Models\TennisMatch;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, login-free match scoring for an umpire holding a match's share link.
 * The scoring actions (point/server/undo/status) are inherited verbatim from
 * the admin controller — only the rendered page differs (no admin chrome) and
 * access is granted by the share token bound in the route.
 */
class UmpireScoringController extends TennisMatchScoringController
{
    public function show(TennisMatch $match): Response
    {
        $match->loadMissing('state');

        $parameters = ['match' => $match->share_token];

        return Inertia::render('Umpire/Score', [
            'match' => $match->toScorePayload(),
            'endpoints' => [
                'point' => route('umpire.score.points', $parameters),
                'server' => route('umpire.score.server', $parameters),
                'undo' => route('umpire.score.undo', $parameters),
                'status' => route('umpire.score.status', $parameters),
            ],
        ]);
    }
}
