<?php

namespace App\Services;

use App\DTOs\TennisScoreState;
use App\Enums\TennisMatchEventType;

/**
 * Pure tennis scoring rules, ported from resources/js/lib/tennis-engine.ts.
 *
 * The engine is the server-side source of truth: given an ordered event log it
 * replays points and server selections into a deterministic score projection.
 */
class TennisScoringEngine
{
    /**
     * Replay an ordered event log into a score projection.
     *
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @param  iterable<int, object>  $events  objects exposing ->type (TennisMatchEventType|string) and ->side (?int)
     */
    public function replay(array $format, iterable $events): TennisScoreState
    {
        $state = TennisScoreState::initial()->toArray();

        foreach ($events as $event) {
            $type = $event->type instanceof TennisMatchEventType
                ? $event->type
                : TennisMatchEventType::from((string) $event->type);

            $side = $event->side === null ? null : (int) $event->side;

            if ($side === null) {
                continue;
            }

            $state = $this->applyEvent($state, $format, $type, $side);
        }

        return TennisScoreState::fromArray($state);
    }

    /**
     * Apply one scoring event to a raw state array. This is the primitive behind
     * {@see replay()}, and mirrors the JS composition in
     * resources/js/lib/tennis-engine.ts (setServer / scorePoint) so the two
     * engines can be driven from the same shared test vectors.
     *
     * @param  array<string, mixed>  $state
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @return array<string, mixed>
     */
    public function applyEvent(array $state, array $format, TennisMatchEventType $type, int $side): array
    {
        return match ($type) {
            TennisMatchEventType::Server => $this->setServer($state, $side),
            TennisMatchEventType::Point => $this->scorePoint($state, $format, $side),
        };
    }

    /**
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function scorePoint(array $state, array $format, int $who): array
    {
        if ($state['win'] !== null) {
            return $state;
        }

        if ($state['tiebreak']) {
            return $this->finishTiebreakPoint($state, $format, $who);
        }

        $suddenDeath = (bool) ($format['sudden_death'] ?? false);

        $nextA = $who === 1 ? $state['game_a'] + 1 : $state['game_a'];
        $nextB = $who === 2 ? $state['game_b'] + 1 : $state['game_b'];

        $game = $this->gameOver($nextA, $nextB, false, 7, true, $suddenDeath);

        if ($game !== null) {
            return $this->finishGame($state, $format, $game);
        }

        if (! $suddenDeath && $nextA === 4 && $nextB === 4) {
            return [...$state, 'game_a' => 3, 'game_b' => 3];
        }

        return [...$state, 'game_a' => $nextA, 'game_b' => $nextB];
    }

    public function gameOver(int $a, int $b, bool $inTiebreak, int $tiebreakTo = 7, bool $extendTiebreak = true, bool $suddenDeath = false): ?int
    {
        if ($inTiebreak) {
            if (! $extendTiebreak) {
                if ($a >= $tiebreakTo) {
                    return 1;
                }

                if ($b >= $tiebreakTo) {
                    return 2;
                }

                return null;
            }

            if ($a >= $tiebreakTo && $a - $b >= 2) {
                return 1;
            }

            if ($b >= $tiebreakTo && $b - $a >= 2) {
                return 2;
            }

            return null;
        }

        if ($suddenDeath) {
            if ($a >= 4) {
                return 1;
            }

            if ($b >= 4) {
                return 2;
            }

            return null;
        }

        if ($a >= 4 && $a - $b >= 2) {
            return 1;
        }

        if ($b >= 4 && $b - $a >= 2) {
            return 2;
        }

        return null;
    }

    public function setOver(int $a, int $b, int $gamesToWin): ?int
    {
        if ($a >= $gamesToWin && $a - $b >= 2) {
            return 1;
        }

        if ($b >= $gamesToWin && $b - $a >= 2) {
            return 2;
        }

        return null;
    }

    /**
     * Games-each score at which the set tiebreak starts. Without extending, the
     * tiebreak comes one game before the set target (e.g. 5-5 for a 6-game set);
     * extending plays that game out first, so the tiebreak lands on the target
     * (6-6), or the set is won 7-5.
     *
     * A single-game set yields 0 here; {@see finishGame()} guards it so no
     * tiebreak is opened and the set is decided by a two-game margin instead.
     */
    public function tiebreakAt(int $gamesToWin, bool $extendAtTie): int
    {
        return $extendAtTie ? $gamesToWin : $gamesToWin - 1;
    }

    public function tiebreakServer(int $total, int $firstServer): int
    {
        if ($total <= 1) {
            return $firstServer;
        }

        $alternate = $firstServer === 1 ? 2 : 1;
        $t = $total - 1;
        $group = intdiv($t - 1, 2);

        return $group % 2 === 0 ? $alternate : $firstServer;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function setServer(array $state, int $side): array
    {
        if ($state['first_server'] !== null) {
            return $state;
        }

        return [...$state, 'server' => $side, 'first_server' => $side];
    }

    /**
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finishGame(array $state, array $format, int $game): array
    {
        $next = [
            ...$state,
            'game_a' => 0,
            'game_b' => 0,
            'set_a' => $game === 1 ? $state['set_a'] + 1 : $state['set_a'],
            'set_b' => $game === 2 ? $state['set_b'] + 1 : $state['set_b'],
        ];

        $set = $this->setOver($next['set_a'], $next['set_b'], $format['games_to_win']);

        if ($set !== null) {
            return $this->finishSet($next, $format);
        }

        $tieAt = $this->tiebreakAt($format['games_to_win'], $format['extend_at_tie']);

        if ($tieAt >= 1 && $next['set_a'] === $tieAt && $next['set_b'] === $tieAt) {
            $first = $state['server'] === 1 ? 2 : 1;

            return [
                ...$next,
                'tiebreak' => true,
                'tb_a' => 0,
                'tb_b' => 0,
                'tb_first' => $first,
                'server' => $first,
            ];
        }

        return [...$next, 'server' => $state['server'] === 1 ? 2 : 1];
    }

    /**
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finishSet(array $state, array $format): array
    {
        $sets = [...$state['sets'], ['a' => $state['set_a'], 'b' => $state['set_b']]];

        $next = [
            ...$state,
            'sets' => $sets,
            'set_a' => 0,
            'set_b' => 0,
            'tiebreak' => false,
            'tb_a' => 0,
            'tb_b' => 0,
        ];

        if ($this->countSets($sets, 1) >= $format['sets_to_win']) {
            return [...$next, 'win' => 1];
        }

        if ($this->countSets($sets, 2) >= $format['sets_to_win']) {
            return [...$next, 'win' => 2];
        }

        return [...$next, 'server' => $state['server'] === 1 ? 2 : 1];
    }

    /**
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool, extend_tiebreak: bool, sudden_death: bool, tiebreak_to?: int}  $format
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finishTiebreakPoint(array $state, array $format, int $who): array
    {
        $tbA = $who === 1 ? $state['tb_a'] + 1 : $state['tb_a'];
        $tbB = $who === 2 ? $state['tb_b'] + 1 : $state['tb_b'];

        $first = $state['tb_first'] ?? $state['server'] ?? 1;

        $next = [
            ...$state,
            'tb_a' => $tbA,
            'tb_b' => $tbB,
            'server' => $this->tiebreakServer($tbA + $tbB, $first),
        ];

        $game = $this->gameOver($tbA, $tbB, true, $format['tiebreak_to'] ?? 7, (bool) ($format['extend_tiebreak'] ?? false));

        if ($game !== null) {
            $tieAt = $this->tiebreakAt($format['games_to_win'], $format['extend_at_tie']);

            $next = [
                ...$next,
                'set_a' => $game === 1 ? $tieAt + 1 : $next['set_a'],
                'set_b' => $game === 2 ? $tieAt + 1 : $next['set_b'],
            ];

            return $this->finishSet($next, $format);
        }

        return $next;
    }

    /**
     * @param  list<array{a: int, b: int}>  $sets
     */
    private function countSets(array $sets, int $side): int
    {
        return count(array_filter(
            $sets,
            fn (array $set): bool => $side === 1 ? $set['a'] > $set['b'] : $set['b'] > $set['a'],
        ));
    }
}
