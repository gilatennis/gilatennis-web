<?php

namespace App\Services\Bracket;

use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;

/**
 * Computes a group's live standings table. The table is its entrants sorted by
 * the ranking comparator — wins, then set difference, then game difference, then
 * head-to-head, then name — so the comparator *is* the tie-break chain.
 */
class GroupStandingsCalculator
{
    /**
     * Compute the live standings table for a group, ordered best-first.
     *
     * A tie counts as a win for the group once its slot carries a winner; the
     * sets and games for/against come from every completed rubber attached to
     * the slot. Ties are broken by wins, then set difference, then game
     * difference, then head-to-head, then entrant name for stability.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(BracketGroup $group): Collection
    {
        $group->loadMissing([
            'entrants.registration.team',
            'slots.entrantA.team',
            'slots.entrantB.team',
            'slots.matches.state',
        ]);

        $entrants = [];

        foreach ($group->entrants as $membership) {
            $registration = $membership->registration;

            if ($registration !== null && ! isset($entrants[$registration->id])) {
                $entrants[$registration->id] = $this->standingRow($registration);
            }
        }

        foreach ($group->slots as $slot) {
            foreach ([$slot->entrantA, $slot->entrantB] as $entrant) {
                if ($entrant !== null && ! isset($entrants[$entrant->id])) {
                    $entrants[$entrant->id] = $this->standingRow($entrant);
                }
            }
        }

        $headToHead = [];

        foreach ($group->slots as $slot) {
            $this->applySlotToStandings($slot, $entrants, $headToHead);
        }

        $rows = array_values($entrants);

        usort($rows, function (array $a, array $b) use ($headToHead): int {
            $byWins = $b['wins'] <=> $a['wins'];

            if ($byWins !== 0) {
                return $byWins;
            }

            $setDiff = ($b['sets_for'] - $b['sets_against']) <=> ($a['sets_for'] - $a['sets_against']);

            if ($setDiff !== 0) {
                return $setDiff;
            }

            $gameDiff = ($b['games_for'] - $b['games_against']) <=> ($a['games_for'] - $a['games_against']);

            if ($gameDiff !== 0) {
                return $gameDiff;
            }

            $h2h = $headToHead[$b['entrant_id']][$a['entrant_id']] ?? 0;

            if ($h2h !== 0) {
                return $h2h;
            }

            return strcmp($a['name'], $b['name']);
        });

        $rank = 1;

        $ranked = array_map(function (array $row) use (&$rank): array {
            $row['set_diff'] = $row['sets_for'] - $row['sets_against'];
            $row['game_diff'] = $row['games_for'] - $row['games_against'];
            $row['rank'] = $rank++;

            return $row;
        }, $rows);

        return collect($ranked);
    }

    /**
     * A zeroed standings row for an entrant.
     *
     * @return array<string, mixed>
     */
    private function standingRow(TournamentRegistration $entrant): array
    {
        return [
            'entrant_id' => $entrant->id,
            'name' => $entrant->display_name,
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'sets_for' => 0,
            'sets_against' => 0,
            'games_for' => 0,
            'games_against' => 0,
        ];
    }

    /**
     * Fold one tie into the running standings. Only decided ties move a win,
     * but any completed rubber contributes its sets and games.
     *
     * @param  array<int, array<string, mixed>>  $entrants
     * @param  array<int, array<int, int>>  $headToHead
     */
    private function applySlotToStandings(BracketSlot $slot, array &$entrants, array &$headToHead): void
    {
        $aId = $slot->entrant_a_id;
        $bId = $slot->entrant_b_id;

        if ($aId === null || $bId === null) {
            return;
        }

        if ($slot->winner_entrant_id !== null && isset($entrants[$aId], $entrants[$bId])) {
            $entrants[$aId]['played']++;
            $entrants[$bId]['played']++;

            if ($slot->winner_entrant_id === $aId) {
                $entrants[$aId]['wins']++;
                $entrants[$bId]['losses']++;
                $headToHead[$aId][$bId] = ($headToHead[$aId][$bId] ?? 0) + 1;
            } elseif ($slot->winner_entrant_id === $bId) {
                $entrants[$bId]['wins']++;
                $entrants[$aId]['losses']++;
                $headToHead[$bId][$aId] = ($headToHead[$bId][$aId] ?? 0) + 1;
            }
        }

        foreach ($slot->matches as $match) {
            $state = $match->state;

            if ($state === null || $state->win === null) {
                continue;
            }

            foreach ($state->sets ?? [] as $set) {
                $aGames = (int) ($set['a'] ?? 0);
                $bGames = (int) ($set['b'] ?? 0);

                if (isset($entrants[$aId])) {
                    $entrants[$aId]['games_for'] += $aGames;
                    $entrants[$aId]['games_against'] += $bGames;
                    $entrants[$aId]['sets_for'] += $aGames > $bGames ? 1 : 0;
                    $entrants[$aId]['sets_against'] += $bGames > $aGames ? 1 : 0;
                }

                if (isset($entrants[$bId])) {
                    $entrants[$bId]['games_for'] += $bGames;
                    $entrants[$bId]['games_against'] += $aGames;
                    $entrants[$bId]['sets_for'] += $bGames > $aGames ? 1 : 0;
                    $entrants[$bId]['sets_against'] += $aGames > $bGames ? 1 : 0;
                }
            }
        }
    }
}
