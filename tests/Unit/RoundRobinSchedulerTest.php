<?php

use App\Services\RoundRobinScheduler;

/**
 * @param  array<int, array<int, array{0: int, 1: int, 2: int}>>  $matchdays
 * @return array<int, string>
 */
function schedulePairs(array $matchdays): array
{
    $pairs = [];

    foreach ($matchdays as $fixtures) {
        foreach ($fixtures as [$a, $b]) {
            $pair = [$a, $b];
            sort($pair);
            $pairs[] = "{$pair[0]}-{$pair[1]}";
        }
    }

    return $pairs;
}

function assertNoEntrantPlaysTwicePerMatchday(array $matchdays): void
{
    foreach ($matchdays as $fixtures) {
        $seen = [];

        foreach ($fixtures as [$a, $b]) {
            $seen[] = $a;
            $seen[] = $b;
        }

        expect(array_unique($seen))->toHaveCount(count($seen));
    }
}

describe('RoundRobinScheduler', function () {
    it('requires at least two entrants', function () {
        expect(fn () => (new RoundRobinScheduler)->schedule(1))
            ->toThrow(InvalidArgumentException::class);
    });

    it('has every pair meet exactly once in a single round robin', function () {
        foreach ([3, 4, 5, 6, 7, 8] as $n) {
            $pairs = schedulePairs((new RoundRobinScheduler)->schedule($n));

            $expected = ($n * ($n - 1)) / 2;

            expect($pairs)->toHaveCount($expected)
                ->and(array_unique($pairs))->toHaveCount($expected);
        }
    });

    it('never lets an entrant play twice in the same matchday', function () {
        foreach ([4, 5, 6, 7, 8] as $n) {
            assertNoEntrantPlaysTwicePerMatchday((new RoundRobinScheduler)->schedule($n));
        }
    });

    it('builds the expected matchday count for even and odd groups', function () {
        $scheduler = new RoundRobinScheduler;

        expect($scheduler->schedule(4))->toHaveCount(3)
            ->and($scheduler->schedule(5))->toHaveCount(5)
            ->and($scheduler->schedule(6))->toHaveCount(5)
            ->and($scheduler->schedule(7))->toHaveCount(7)
            ->and($scheduler->schedule(4, true))->toHaveCount(6)
            ->and($scheduler->schedule(5, true))->toHaveCount(10);
    });

    it('gives exactly one bye each matchday when the group is odd', function () {
        foreach ([3, 5, 7] as $n) {
            $schedule = (new RoundRobinScheduler)->schedule($n);

            foreach ($schedule as $fixtures) {
                expect($fixtures)->toHaveCount(intdiv($n, 2));

                $seen = [];
                foreach ($fixtures as [$a, $b]) {
                    $seen[] = $a;
                    $seen[] = $b;
                }

                expect(array_unique($seen))->toHaveCount($n - 1);
            }
        }
    });

    it('marks every first-leg fixture as leg one', function () {
        foreach ((new RoundRobinScheduler)->schedule(6) as $fixtures) {
            foreach ($fixtures as [$a, $b, $leg]) {
                expect($leg)->toBe(1);
            }
        }
    });

    it('doubles the schedule with swapped sides on a distinct return leg', function () {
        $single = (new RoundRobinScheduler)->schedule(4);
        $double = (new RoundRobinScheduler)->schedule(4, true);

        expect($double)->toHaveCount(6);

        $firstLeg = array_slice($double, 0, 3);
        $secondLeg = array_slice($double, 3);

        expect(schedulePairs($firstLeg))->toBe(schedulePairs($single));

        foreach ($secondLeg as $fixtures) {
            foreach ($fixtures as [$a, $b, $leg]) {
                expect($leg)->toBe(2);
            }
        }

        $singleOrdered = [];
        foreach ($single as $fixtures) {
            foreach ($fixtures as [$a, $b]) {
                $singleOrdered[] = "{$a}-{$b}";
            }
        }

        $returnOrdered = [];
        foreach ($secondLeg as $fixtures) {
            foreach ($fixtures as [$a, $b]) {
                $returnOrdered[] = "{$a}-{$b}";
            }
        }

        expect($returnOrdered)->toBe(array_map(
            fn (string $fixture) => implode('-', array_reverse(explode('-', $fixture))),
            $singleOrdered,
        ));
    });

    it('produces a valid schedule for every supported group size', function () {
        foreach ([3, 4, 5, 6, 7, 8] as $n) {
            $scheduler = new RoundRobinScheduler;
            $single = $scheduler->schedule($n);
            $double = $scheduler->schedule($n, true);

            $expected = ($n * ($n - 1)) / 2;

            expect(schedulePairs($single))->toHaveCount($expected)
                ->and(array_unique(schedulePairs($single)))->toHaveCount($expected)
                ->and(schedulePairs($double))->toHaveCount($expected * 2)
                ->and($double)->toHaveCount(count($single) * 2);
        }
    });
});
