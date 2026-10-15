<?php

namespace App\Services;

use App\DTOs\TennisScoreState;
use App\Enums\MatchStatus;
use App\Enums\TennisMatchEventType;
use App\Models\TennisMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * Move a match between statuses (start, complete, reopen, cancel), owning
     * the started/ended timestamps and clearing a stale bracket winner when a
     * completed match is reopened. Shared by the admin and public scoreboards.
     */
    public function setStatus(
        TennisMatch $match,
        MatchStatus $next,
        ?Carbon $startedAt = null,
        ?Carbon $endedAt = null,
    ): TennisMatch {
        $current = $match->status;

        if ($next !== $current && ! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$current->value} to {$next->value}.",
            ]);
        }

        if ($next === MatchStatus::Running) {
            $match->started_at ??= $startedAt ?? now();
            $match->ended_at = null;
        }

        if ($next === MatchStatus::Completed && $match->ended_at === null) {
            $match->ended_at = $endedAt ?? now();
        }

        if ($next === MatchStatus::Pending) {
            $match->started_at = null;
            $match->ended_at = null;
        }

        $match->status = $next;
        $match->save();

        if ($current === MatchStatus::Completed && $next !== MatchStatus::Completed) {
            $this->clearBracketWinner($match);
        }

        return $match;
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
     * Clearing and (re)setting the bracket winner are distinct concerns; a
     * status change only ever drops a winner a reopened match no longer owns.
     */
    private function clearBracketWinner(TennisMatch $match): void
    {
        if ($match->bracket_slot_id === null) {
            return;
        }

        $slot = $match->bracketSlot()->first();

        if ($slot !== null && $slot->winner_entrant_id !== null) {
            $this->brackets->setWinner($slot, null);
        }
    }

    /**
     * @return array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to: int}
     */
    private function formatFor(TennisMatch $match): array
    {
        return [
            'sets_to_win' => $match->sets_to_win,
            'games_to_win' => $match->games_to_win,
            'extend_at_tie' => (bool) $match->extend_at_tie,
            'extend_tiebreak' => (bool) $match->extend_tiebreak,
            'sudden_death' => (bool) $match->sudden_death,
            'tiebreak_to' => $match->tiebreak_to,
        ];
    }
}
