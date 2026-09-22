<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Enums\TennisMatchEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TennisMatchPointRequest;
use App\Http\Requests\Admin\TennisMatchServerRequest;
use App\Models\TennisMatch;
use App\Services\TennisMatchScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TennisMatchScoringController extends Controller
{
    public function __construct(private TennisMatchScoringService $scoring) {}

    public function point(TennisMatchPointRequest $request, TennisMatch $match): JsonResponse
    {
        $this->ensureScorable($match);

        $this->scoring->appendEvent(
            $match,
            TennisMatchEventType::Point,
            (int) $request->validated('side'),
            $request->validated('shot'),
        );

        return $this->payload($match);
    }

    public function server(TennisMatchServerRequest $request, TennisMatch $match): JsonResponse
    {
        $this->ensureScorable($match);

        $this->scoring->appendEvent(
            $match,
            TennisMatchEventType::Server,
            (int) $request->validated('side'),
        );

        return $this->payload($match);
    }

    public function undo(TennisMatch $match): JsonResponse
    {
        $this->ensureReopenable($match);

        $this->scoring->undoLast($match);

        return $this->payload($match);
    }

    /**
     * A completed match is final until an admin reopens it; the score is frozen
     * so a stale form submission cannot silently change a published result.
     */
    private function ensureReopenable(TennisMatch $match): void
    {
        if ($match->status === MatchStatus::Completed) {
            throw ValidationException::withMessages([
                'score' => 'The match is completed. Reopen it before changing the score.',
            ]);
        }
    }

    /**
     * Scoring is also frozen once a side has won: the admin must undo the
     * deciding point (or complete the match) rather than pile on extra events.
     */
    private function ensureScorable(TennisMatch $match): void
    {
        $this->ensureReopenable($match);

        if ($match->state()->value('win') !== null) {
            throw ValidationException::withMessages([
                'score' => 'The match has already been decided. Undo the last point to change the score.',
            ]);
        }
    }

    private function payload(TennisMatch $match): JsonResponse
    {
        $match->refresh()->load('state');

        return response()->json([
            'match' => $match->toScorePayload(),
        ]);
    }
}
