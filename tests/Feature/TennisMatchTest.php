<?php

use App\Enums\MatchStatus;
use App\Models\TennisMatch;

describe('TennisMatch Model', function () {
    it('can be created via factory', function () {
        $match = TennisMatch::factory()->create();
        expect($match->exists())->toBeTrue();
    });

    it('defaults to pending status with no started_at', function () {
        $match = TennisMatch::factory()->create();

        expect($match->status)->toBe(MatchStatus::Pending);
        expect($match->started_at)->toBeNull();
        expect($match->ended_at)->toBeNull();
    });

    it('factory generates different side names', function () {
        $match = TennisMatch::factory()->create();

        expect($match->side_a_name)->not->toBe($match->side_b_name);
    });
});

describe('TennisMatch format_label', function () {
    it('formats best-of-1, 6 games, TB to 7', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 1,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ]);

        expect($match->format_label)->toBe('Best of 1 set(s), 6 games, TB to 7');
    });

    it('formats best-of-3, 6 games, TB to 7 (default)', function () {
        $match = TennisMatch::factory()->create();

        expect($match->format_label)->toBe('Best of 3 set(s), 6 games, TB to 7');
    });

    it('formats best-of-5, 6 games, TB to 10', function () {
        $match = TennisMatch::factory()->create([
            'sets_to_win' => 3,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 10,
        ]);

        expect($match->format_label)->toBe('Best of 5 set(s), 6 games, TB to 10');
    });

    it('formats fast-4 with TB to 5', function () {
        $match = TennisMatch::factory()->fast4()->create();

        expect($match->format_label)->toBe('Best of 3 set(s), 4 games, TB to 5');
    });

    it('formats short-set 5', function () {
        $match = TennisMatch::factory()->shortSet5()->create();

        expect($match->format_label)->toBe('Best of 3 set(s), 5 games, TB to 7');
    });
});

describe('TennisMatch Scopes', function () {
    it('pending scope returns only pending matches', function () {
        TennisMatch::factory()->count(2)->create();
        TennisMatch::factory()->running()->create();
        TennisMatch::factory()->completed()->create();

        expect(TennisMatch::pending()->count())->toBe(2);
    });

    it('running scope returns only running matches', function () {
        TennisMatch::factory()->count(2)->create();
        TennisMatch::factory()->running()->count(3)->create();
        TennisMatch::factory()->completed()->create();

        expect(TennisMatch::running()->count())->toBe(3);
    });

    it('completed scope returns only completed matches', function () {
        TennisMatch::factory()->count(2)->create();
        TennisMatch::factory()->running()->create();
        TennisMatch::factory()->completed()->count(4)->create();

        expect(TennisMatch::completed()->count())->toBe(4);
    });
});

describe('TennisMatch Factory States', function () {
    it('running state sets status and started_at', function () {
        $match = TennisMatch::factory()->running()->create();

        expect($match->status)->toBe(MatchStatus::Running);
        expect($match->started_at)->not->toBeNull();
    });

    it('completed state sets status, started_at, and ended_at', function () {
        $match = TennisMatch::factory()->completed()->create();

        expect($match->status)->toBe(MatchStatus::Completed);
        expect($match->started_at)->not->toBeNull();
        expect($match->ended_at)->not->toBeNull();
    });

    it('canceled state sets status and ended_at', function () {
        $match = TennisMatch::factory()->canceled()->create();

        expect($match->status)->toBe(MatchStatus::Canceled);
        expect($match->ended_at)->not->toBeNull();
    });
});
