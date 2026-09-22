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
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool}  $format
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

            $state = match ($type) {
                TennisMatchEventType::Server => $this->setServer($state, $side),
                TennisMatchEventType::Point => $this->scorePoint($state, $format, $side),
            };
        }

        return TennisScoreState::fromArray($state);
    }

    /**
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool}  $format
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

        $nextA = $who === 1 ? $state['game_a'] + 1 : $state['game_a'];
        $nextB = $who === 2 ? $state['game_b'] + 1 : $state['game_b'];

        $game = $this->gameOver($nextA, $nextB, false);

        if ($game !== null) {
            return $this->finishGame($state, $format, $game);
        }

        if ($nextA === 4 && $nextB === 4) {
            return [...$state, 'game_a' => 3, 'game_b' => 3];
        }

        return [...$state, 'game_a' => $nextA, 'game_b' => $nextB];
    }

    public function gameOver(int $a, int $b, bool $inTiebreak): ?int
    {
        if ($inTiebreak) {
            if ($a >= 7 && $a - $b >= 2) {
                return 1;
            }

            if ($b >= 7 && $b - $a >= 2) {
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

    public function setOver(int $a, int $b, int $gamesToWin, bool $extendAtTie): ?int
    {
        if ($extendAtTie) {
            if ($a >= $gamesToWin + 1 && $a - $b >= 2) {
                return 1;
            }

            if ($b >= $gamesToWin + 1 && $b - $a >= 2) {
                return 2;
            }

            return null;
        }

        if ($a >= $gamesToWin && $a - $b >= 2) {
            return 1;
        }

        if ($b >= $gamesToWin && $b - $a >= 2) {
            return 2;
        }

        return null;
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
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool}  $format
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

        $set = $this->setOver($next['set_a'], $next['set_b'], $format['games_to_win'], $format['extend_at_tie']);

        if ($set !== null) {
            return $this->finishSet($next, $format);
        }

        if (
            ! $format['extend_at_tie']
            && $next['set_a'] === $format['games_to_win']
            && $next['set_b'] === $format['games_to_win']
        ) {
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
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool}  $format
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
     * @param  array{sets_to_win: int, games_to_win: int, extend_at_tie: bool}  $format
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

        $game = $this->gameOver($tbA, $tbB, true);

        if ($game !== null) {
            $next = [
                ...$next,
                'set_a' => $game === 1 ? $format['games_to_win'] + 1 : $next['set_a'],
                'set_b' => $game === 2 ? $format['games_to_win'] + 1 : $next['set_b'],
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
