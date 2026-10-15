<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shapes a tournament's matches for the umpire console: the matches currently
 * on court (running), plus every other match grouped by division so the umpire
 * can see what is coming up.
 *
 * Reuses {@see PublicMatchPresenter} so the umpire rows carry exactly the score
 * fields the shared scoreboard already consumes.
 */
class UmpireMatchListService
{
    public function __construct(private PublicMatchPresenter $presenter) {}

    /**
     * @return array{
     *     onCourt: list<array<string, mixed>>,
     *     divisions: list<array{id: int, name: string, matches: list<array<string, mixed>>}>
     * }
     */
    public function present(Tournament $tournament): array
    {
        $matches = $this->baseQuery($tournament)
            ->where('tennis_matches.status', '!=', MatchStatus::Canceled->value)
            ->orderBy('divisions.sort_order')
            ->orderBy('tennis_matches.id')
            ->get();

        $onCourt = $matches
            ->filter(fn (TennisMatch $match): bool => $match->status === MatchStatus::Running)
            ->values();

        // Matches on court are listed once, in the "on court now" section; the
        // division lists hold everything else (upcoming / finished).
        $offCourt = $matches
            ->reject(fn (TennisMatch $match): bool => $match->status === MatchStatus::Running)
            ->values();

        return [
            'onCourt' => $this->presenter->presentMany($onCourt),
            'divisions' => $this->groupByDivision($offCourt),
        ];
    }

    /**
     * @param  Collection<int, TennisMatch>  $matches
     * @return list<array{id: int, name: string, matches: list<array<string, mixed>>}>
     */
    private function groupByDivision(Collection $matches): array
    {
        $divisions = [];

        foreach ($matches as $match) {
            $divisions[$match->division_id] ??= [
                'id' => $match->division_id,
                'name' => $match->division->name,
                'matches' => [],
            ];

            $divisions[$match->division_id]['matches'][] = $this->presenter->present($match);
        }

        return array_values($divisions);
    }

    /**
     * @return Builder<TennisMatch>
     */
    private function baseQuery(Tournament $tournament): Builder
    {
        return TennisMatch::query()
            ->select('tennis_matches.*')
            ->join('divisions', 'divisions.id', '=', 'tennis_matches.division_id')
            ->where('divisions.tournament_id', $tournament->id)
            ->with([
                'state',
                'division:id,name,sort_order',
                'bracketSlot.bracket:id,type',
                'bracketSlot.group:id,name,position',
                'bracketSlot.entrantA.team',
                'bracketSlot.entrantB.team',
            ]);
    }
}
