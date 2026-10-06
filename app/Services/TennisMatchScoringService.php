<?php

namespace App\Services;

use App\DTOs\TennisScoreState;
use App\Enums\MatchStatus;
use App\Enums\TennisMatchEventType;
use App\Models\TennisMatch;
use Illuminate\Support\Facades\DB;

/**
 * Owns the authoritative match score: appends scoring events, replays them via
 * {@see TennisScoringEngine}, and persists the resulting projection + status.
 */
class TennisMatchScoringService
{
    public function __construct(
        private TennisScoringEngine $engine,
        private BracketService $brackets,
    ) {}

    public function appendEvent(
        TennisMatch $match,
        TennisMatchEventType $type,
        ?int $side,
        ?string $shot = null,
    ): TennisScoreState {
        return DB::transaction(function () use ($match, $type, $side, $shot) {
            $match = $this->lockMatch($match);

            if ($match->loadMissing('state')->state?->win !== null) {
                return $this->project($match);
            }

            $seq = (int) $match->events()->max('seq') + 1;

            $match->events()->create([
                'seq' => $seq,
                'type' => $type,
                'side' => $side,
                'shot' => $shot,
                'created_by' => auth()->id(),
            ]);

            $state = $this->project($match->refresh());

            $this->syncBracket($match);

            return $state;
        });
    }

    public function undoLast(TennisMatch $match): TennisScoreState
    {
        return DB::transaction(function () use ($match) {
            $match = $this->lockMatch($match);

            $match->events()->reorder()->orderByDesc('seq')->first()?->delete();

            $state = $this->project($match->refresh());

            $this->syncBracket($match);

            return $state;
        });
    }

    /**
     * Serialize writes to a single match so concurrent point requests cannot
     * allocate the same sequence number (the events table is unique on it).
     */
    private function lockMatch(TennisMatch $match): TennisMatch
    {
        return TennisMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
    }

    private function project(TennisMatch $match): TennisScoreState
    {
        $events = $match->events()->get();
        $state = $this->engine->replay($this->formatFor($match), $events);

        $match->state()->updateOrCreate([], [
            'server' => $state->server,
            'first_server' => $state->firstServer,
            'set_a' => $state->setA,
            'set_b' => $state->setB,
            'game_a' => $state->gameA,
            'game_b' => $state->gameB,
            'tiebreak' => $state->tiebreak,
            'tb_a' => $state->tbA,
            'tb_b' => $state->tbB,
            'tb_first' => $state->tbFirst,
            'sets' => $state->sets,
            'win' => $state->win,
        ]);

        $this->syncStatus($match, $events->isNotEmpty());

        return $state;
    }

    /**
     * Scoring only ever moves a match between pending and running. Completion is
     * an explicit admin action, so a decided-but-unconfirmed match stays running
     * and the score can still be corrected.
     */
    private function syncStatus(TennisMatch $match, bool $hasEvents): void
    {
        if (in_array($match->status, [MatchStatus::Canceled, MatchStatus::Completed], true)) {
            return;
        }

        if ($hasEvents) {
            $match->status = MatchStatus::Running;
            $match->started_at ??= now();
            $match->ended_at = null;
        } else {
            $match->status = MatchStatus::Pending;
            $match->started_at = null;
            $match->ended_at = null;
        }

        $match->save();
    }

    /**
     * Advancement is explicit (the bracket confirm action). Here we only make
     * sure a slot no longer carries a winner once its match is not completed.
     */
    private function syncBracket(TennisMatch $match): void
    {
        if ($match->bracket_slot_id === null || $match->status === MatchStatus::Completed) {
            return;
        }

        $slot = $match->bracketSlot()->first();

        if ($slot !== null && $slot->winner_entrant_id !== null) {
            $this->brackets->setWinner($slot, null);
        }
    }

    /**
     * @return array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, tiebreak_to: int}
     */
    private function formatFor(TennisMatch $match): array
    {
        return [
            'sets_to_win' => $match->sets_to_win,
            'games_to_win' => $match->games_to_win,
            'extend_at_tie' => (bool) $match->extend_at_tie,
            'tiebreak_to' => $match->tiebreak_to,
        ];
    }
}
