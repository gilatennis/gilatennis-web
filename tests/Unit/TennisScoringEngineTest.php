<?php

use App\DTOs\TennisScoreState;
use App\Enums\TennisMatchEventType;
use App\Services\TennisScoringEngine;

function engine(): TennisScoringEngine
{
    return new TennisScoringEngine;
}

/**
 * @param  list<array{0: TennisMatchEventType, 1: int|null}>  $specs
 * @return list<object>
 */
function scoreEvents(array $specs): array
{
    return array_map(
        fn (array $spec) => (object) ['type' => $spec[0], 'side' => $spec[1]],
        $specs,
    );
}

describe('TennisScoringEngine gameOver', function () {
    it('requires a two point margin in a normal game', function () {
        $e = engine();

        expect($e->gameOver(3, 3, false))->toBeNull()
            ->and($e->gameOver(4, 3, false))->toBeNull()
            ->and($e->gameOver(4, 4, false))->toBeNull()
            ->and($e->gameOver(5, 3, false))->toBe(1)
            ->and($e->gameOver(3, 5, false))->toBe(2);
    });

    it('requires seven points and a two point margin in a tiebreak', function () {
        $e = engine();

        expect($e->gameOver(6, 5, true))->toBeNull()
            ->and($e->gameOver(7, 5, true))->toBe(1)
            ->and($e->gameOver(8, 6, true))->toBe(1)
            ->and($e->gameOver(5, 7, true))->toBe(2);
    });
});

describe('TennisScoringEngine setOver', function () {
    it('uses the games-to-win threshold with a two game margin', function () {
        $e = engine();

        expect($e->setOver(6, 4, 6, false))->toBe(1)
            ->and($e->setOver(6, 5, 6, false))->toBeNull()
            ->and($e->setOver(7, 5, 6, false))->toBe(1)
            ->and($e->setOver(7, 6, 6, false))->toBeNull();
    });

    it('extends past the threshold when extend_at_tie is enabled', function () {
        $e = engine();

        expect($e->setOver(6, 5, 6, true))->toBeNull()
            ->and($e->setOver(7, 5, 6, true))->toBe(1)
            ->and($e->setOver(10, 8, 6, true))->toBe(1);
    });
});

describe('TennisScoringEngine tiebreakServer', function () {
    it('alternates the server every two points after the first', function () {
        $e = engine();

        expect($e->tiebreakServer(1, 1))->toBe(1)
            ->and($e->tiebreakServer(2, 1))->toBe(2)
            ->and($e->tiebreakServer(3, 1))->toBe(2)
            ->and($e->tiebreakServer(4, 1))->toBe(1)
            ->and($e->tiebreakServer(5, 1))->toBe(1)
            ->and($e->tiebreakServer(6, 1))->toBe(2);
    });
});

describe('TennisScoringEngine scorePoint', function () {
    it('wins a game after four straight points', function () {
        $e = engine();
        $format = ['sets_to_win' => 2, 'games_to_win' => 6, 'extend_at_tie' => false];
        $state = TennisScoreState::initial()->toArray();

        for ($i = 0; $i < 4; $i++) {
            $state = $e->scorePoint($state, $format, 1);
        }

        expect($state['set_a'])->toBe(1)
            ->and($state['set_b'])->toBe(0)
            ->and($state['game_a'])->toBe(0)
            ->and($state['game_b'])->toBe(0);
    });

    it('resets to deuce at 4-4 and awards the game at 5-3', function () {
        $e = engine();
        $format = ['sets_to_win' => 2, 'games_to_win' => 6, 'extend_at_tie' => false];
        $state = TennisScoreState::initial()->toArray();

        // 3-3, then advantage A, then deuce again.
        for ($i = 0; $i < 3; $i++) {
            $state = $e->scorePoint($state, $format, 1);
        }
        for ($i = 0; $i < 3; $i++) {
            $state = $e->scorePoint($state, $format, 2);
        }
        expect($state['game_a'])->toBe(3)->and($state['game_b'])->toBe(3);

        $state = $e->scorePoint($state, $format, 1);
        expect($state['game_a'])->toBe(4)->and($state['game_b'])->toBe(3);

        $state = $e->scorePoint($state, $format, 2);
        expect($state['game_a'])->toBe(3)->and($state['game_b'])->toBe(3);

        $state = $e->scorePoint($state, $format, 1);
        $state = $e->scorePoint($state, $format, 1);
        expect($state['set_a'])->toBe(1)->and($state['game_a'])->toBe(0);
    });

    it('awards a tiebreak set at 7-5 in the tiebreak', function () {
        $e = engine();
        $format = ['sets_to_win' => 1, 'games_to_win' => 6, 'extend_at_tie' => false];

        $state = TennisScoreState::initial()->toArray();
        $state = [
            ...$state,
            'server' => 1,
            'first_server' => 1,
            'set_a' => 6,
            'set_b' => 6,
            'tiebreak' => true,
            'tb_first' => 1,
        ];

        for ($i = 0; $i < 6; $i++) {
            $state = $e->scorePoint($state, $format, 1);
        }
        expect($state['tb_a'])->toBe(6)->and($state['win'])->toBeNull();

        $state = $e->scorePoint($state, $format, 1);

        expect($state['win'])->toBe(1)
            ->and($state['sets'])->toBe([['a' => 7, 'b' => 6]]);
    });
});

describe('TennisScoringEngine replay', function () {
    it('sets the first server from a server event', function () {
        $state = engine()->replay(
            ['sets_to_win' => 2, 'games_to_win' => 6, 'extend_at_tie' => false],
            scoreEvents([[TennisMatchEventType::Server, 2]]),
        );

        expect($state->server)->toBe(2)
            ->and($state->firstServer)->toBe(2);
    });

    it('completes a fast4 match after four games', function () {
        $events = [[TennisMatchEventType::Server, 1]];

        for ($game = 0; $game < 4; $game++) {
            for ($point = 0; $point < 4; $point++) {
                $events[] = [TennisMatchEventType::Point, 1];
            }
        }

        $state = engine()->replay(
            ['sets_to_win' => 1, 'games_to_win' => 4, 'extend_at_tie' => false],
            scoreEvents($events),
        );

        expect($state->win)->toBe(1)
            ->and($state->sets)->toBe([['a' => 4, 'b' => 0]]);
    });

    it('ignores points after the match is won', function () {
        $events = [[TennisMatchEventType::Server, 1]];

        for ($game = 0; $game < 4; $game++) {
            for ($point = 0; $point < 4; $point++) {
                $events[] = [TennisMatchEventType::Point, 1];
            }
        }

        $events[] = [TennisMatchEventType::Point, 2];

        $state = engine()->replay(
            ['sets_to_win' => 1, 'games_to_win' => 4, 'extend_at_tie' => false],
            scoreEvents($events),
        );

        expect($state->win)->toBe(1);
    });
});
