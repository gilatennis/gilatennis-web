<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Builds round-robin fixtures using the circle (Berger) method.
 *
 * A single round-robin has every entrant play every other entrant once. A
 * double round-robin replays the same schedule with the sides swapped, so each
 * pairing meets twice with balanced home/away. When the group has an odd number
 * of entrants, one entrant sits out each matchday (a bye).
 *
 * The returned structure is matchday-first: the outer index is the matchday
 * (1-based round) and each inner array is the fixtures for that matchday. Each
 * fixture is `[sideAIndex, sideBIndex, leg]` where the indexes are 0-based
 * positions within the ordered entrant list and `leg` is 1 for the first
 * meeting and 2 for the return.
 */
class RoundRobinScheduler
{
    /**
     * @return array<int, array<int, array{0: int, 1: int, 2: int}>>
     */
    public function schedule(int $entrantCount, bool $double = false): array
    {
        if ($entrantCount < 2) {
            throw new InvalidArgumentException('A round robin needs at least two entrants.');
        }

        $firstLeg = $this->singleSchedule($entrantCount);

        if (! $double) {
            return $this->tagLegs($firstLeg, 1);
        }

        $secondLeg = array_map(
            fn (array $fixtures) => array_map(
                fn (array $fixture) => [$fixture[1], $fixture[0]],
                $fixtures,
            ),
            $firstLeg,
        );

        return array_merge(
            $this->tagLegs($firstLeg, 1),
            $this->tagLegs($secondLeg, 2),
        );
    }

    /**
     * The classic circle method: fix the first entrant and rotate the rest,
     * pairing across the middle each round.
     *
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function singleSchedule(int $entrantCount): array
    {
        $seats = range(0, $entrantCount - 1);

        if ($entrantCount % 2 === 1) {
            $seats[] = null;
        }

        $seatCount = count($seats);
        $matchdays = [];

        for ($round = 0; $round < $seatCount - 1; $round++) {
            $fixtures = [];

            for ($i = 0; $i < $seatCount / 2; $i++) {
                $a = $seats[$i];
                $b = $seats[$seatCount - 1 - $i];

                if ($a === null || $b === null) {
                    continue;
                }

                $fixtures[] = [$a, $b];
            }

            $matchdays[] = $fixtures;

            $seats = $this->rotate($seats);
        }

        return $matchdays;
    }

    /**
     * Keep the first seat fixed and rotate the remainder clockwise.
     *
     * @param  array<int, int|null>  $seats
     * @return array<int, int|null>
     */
    private function rotate(array $seats): array
    {
        $fixed = array_shift($seats);
        $last = array_pop($seats);

        return array_merge([$fixed, $last], $seats);
    }

    /**
     * @param  array<int, array<int, array{0: int, 1: int}>>  $matchdays
     * @return array<int, array<int, array{0: int, 1: int, 2: int}>>
     */
    private function tagLegs(array $matchdays, int $leg): array
    {
        return array_map(
            fn (array $fixtures) => array_map(
                fn (array $fixture) => [$fixture[0], $fixture[1], $leg],
                $fixtures,
            ),
            $matchdays,
        );
    }
}
