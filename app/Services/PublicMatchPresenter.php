<?php

namespace App\Services;

use App\Enums\BracketType;
use App\Models\BracketSlot;
use App\Models\TennisMatch;

/**
 * Shapes a {@see TennisMatch} for the public (unauthenticated) scoreboard.
 *
 * Deliberately narrower than {@see TennisMatch::toScorePayload()}: it exposes
 * only what a spectator needs and never touches the event log, so listing many
 * matches stays a couple of queries (no per-row `events()->count()` N+1).
 */
class PublicMatchPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(TennisMatch $match): array
    {
        return [
            ...$match->scoreFields(),
            'division' => $this->presentDivision($match),
            'stage' => $this->presentStage($match),
            'group' => $this->presentGroup($match),
            'round' => $this->presentRound($match),
            'tie' => $this->presentTie($match),
        ];
    }

    /**
     * @param  iterable<int, TennisMatch>  $matches
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $matches): array
    {
        $presented = [];

        foreach ($matches as $match) {
            $presented[] = $this->present($match);
        }

        return $presented;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function presentDivision(TennisMatch $match): ?array
    {
        if (! $match->relationLoaded('division') || $match->division === null) {
            return null;
        }

        return [
            'id' => $match->division->id,
            'name' => $match->division->name,
        ];
    }

    /**
     * Which stage the match belongs to, if it sits in a bracket: `group_stage`,
     * `knockout`, or null for an ad-hoc (slot-less) match.
     */
    private function presentStage(TennisMatch $match): ?string
    {
        $slot = $this->slot($match);

        if ($slot === null || ! $slot->relationLoaded('bracket') || $slot->bracket === null) {
            return null;
        }

        return match ($slot->bracket->type) {
            BracketType::GroupStage => 'group_stage',
            BracketType::SingleElimination => 'knockout',
        };
    }

    /**
     * @return array{id: int, name: string, position: int}|null
     */
    private function presentGroup(TennisMatch $match): ?array
    {
        $slot = $this->slot($match);

        if ($slot === null || ! $slot->relationLoaded('group') || $slot->group === null) {
            return null;
        }

        return [
            'id' => $slot->group->id,
            'name' => $slot->group->name,
            'position' => $slot->group->position,
        ];
    }

    /**
     * The match's place within its stage: a knockout round (`label` = Final,
     * Semifinal, …) or a group matchday (`label` null, `number` = matchday).
     *
     * @return array{number: int, label: string|null}|null
     */
    private function presentRound(TennisMatch $match): ?array
    {
        $slot = $this->slot($match);

        if ($slot === null) {
            return null;
        }

        return [
            'number' => $slot->round,
            'label' => $slot->label,
        ];
    }

    /**
     * Team ties field several rubber matches under one bracket slot; surface the
     * tie's two sides so the public list can group them.
     *
     * @return array{slot_id: int, round: int, position: int, label: string|null, team_a_name: string|null, team_b_name: string|null}|null
     */
    private function presentTie(TennisMatch $match): ?array
    {
        $slot = $this->slot($match);

        if ($slot === null) {
            return null;
        }

        return [
            'slot_id' => $slot->id,
            'round' => $slot->round,
            'position' => $slot->position,
            'label' => $slot->label,
            'team_a_name' => $slot->entrantA?->display_name,
            'team_b_name' => $slot->entrantB?->display_name,
        ];
    }

    private function slot(TennisMatch $match): ?BracketSlot
    {
        if (! $match->relationLoaded('bracketSlot') || $match->bracketSlot === null) {
            return null;
        }

        return $match->bracketSlot;
    }
}
