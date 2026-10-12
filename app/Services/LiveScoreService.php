<?php

namespace App\Services;

use App\Enums\BracketType;
use App\Models\TennisMatch;
use App\Models\TennisMatchState;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the public live board for a tournament off the denormalized
 * {@see TennisMatchState} projection (one query per section).
 *
 * Results are capped per "bucket" — a division's group, knockout round, or the
 * ad-hoc "Matches" list — so one busy stage cannot crowd out the rest.
 *
 * Recent results read most-recent-first: divisions keep their sort order, but a
 * division's stages and buckets come out ordered by their newest match, and the
 * matches within a bucket are newest-first.
 */
class LiveScoreService
{
    /** How many finished matches to keep per division/group/round bucket. */
    public const RESULTS_PER_BUCKET = 3;

    public function __construct(private PublicMatchPresenter $presenter) {}

    /**
     * Matches currently in progress, oldest first so the board is stable
     * between polls.
     *
     * @return list<array<string, mixed>>
     */
    public function liveMatches(Tournament $tournament): array
    {
        return $this->presenter->presentMany(
            $this->baseQuery($tournament)
                ->running()
                ->orderBy('tennis_matches.started_at')
                ->orderBy('tennis_matches.id')
                ->get(),
        );
    }

    /**
     * Newest finished matches, limited to `$perBucket` per division/group/round
     * so every stage of every division still gets a look-in.
     *
     * The query returns matches newest-first, so the buckets are first seen in
     * recency order and the board reads most-recent-first without an explicit
     * sort: a division's stages and buckets follow their newest match, and the
     * matches inside a bucket are newest-first.
     *
     * @return list<array<string, mixed>>
     */
    public function recentResults(Tournament $tournament, int $perBucket = self::RESULTS_PER_BUCKET): array
    {
        $matches = $this->baseQuery($tournament)
            ->completed()
            ->orderByDesc('tennis_matches.ended_at')
            ->orderByDesc('tennis_matches.id')
            ->get();

        $buckets = [];

        foreach ($matches as $match) {
            $key = $this->bucketKey($match);
            $buckets[$key] ??= ['match' => $match, 'items' => []];

            if (count($buckets[$key]['items']) < $perBucket) {
                $buckets[$key]['items'][] = $match;
            }
        }

        $presented = [];

        foreach ($buckets as $bucket) {
            foreach ($bucket['items'] as $match) {
                $presented[] = $this->presenter->present($match);
            }
        }

        return $presented;
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
            ])
            ->orderBy('divisions.sort_order');
    }

    /**
     * Bucket a match by where it sits: its division's group, knockout round, or
     * the slot-less "Matches" list.
     */
    private function bucketKey(TennisMatch $match): string
    {
        $slot = $match->bracketSlot;

        if ($slot === null || $slot->bracket === null) {
            return 'm:'.$match->division_id;
        }

        if ($slot->bracket->type === BracketType::GroupStage) {
            return 'g:'.$match->division_id.':'.($slot->bracket_group_id ?? 0);
        }

        return 'k:'.$match->division_id.':'.$slot->round;
    }
}
