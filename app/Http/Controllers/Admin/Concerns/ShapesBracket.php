<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;
use App\Services\BracketService;

trait ShapesBracket
{
    private function shapeBracket(?Bracket $bracket, BracketService $standings): ?array
    {
        if ($bracket === null) {
            return null;
        }

        return $bracket->isGroupStage()
            ? $this->shapeGroupStage($bracket, $standings)
            : $this->shapeKnockout($bracket, $standings);
    }

    /**
     * Shape a single-elimination bracket into rounds of slots.
     *
     * @return array<string, mixed>
     */
    private function shapeKnockout(Bracket $bracket, BracketService $standings): array
    {
        $bracket->loadMissing([
            'slots.entrantA.team',
            'slots.entrantA.players',
            'slots.entrantB.team',
            'slots.entrantB.players',
            'slots.winnerEntrant.team',
            'slots.winnerEntrant.players',
            'slots.matches.state',
        ]);

        $rounds = $bracket->slots
            ->groupBy('round')
            ->sortKeys()
            ->map(fn ($slots, $round) => [
                'round' => (int) $round,
                'label' => $slots->first()->label,
                'slots' => $slots
                    ->sortBy('position')
                    ->values()
                    ->map(fn (BracketSlot $slot) => self::shapeSlot($slot))
                    ->values(),
            ])
            ->values();

        return [
            'id' => $bracket->id,
            'type' => $bracket->type,
            'status' => $bracket->status,
            'size' => $bracket->size,
            'manual_assignment' => $bracket->manual_assignment,
            'draw_locked' => $bracket->draw_locked,
            'eligible_entrants' => $bracket->manual_assignment
                ? $standings->eligibleEntrants($bracket)
                    ->map(fn (TournamentRegistration $entrant) => [
                        'id' => $entrant->id,
                        'name' => $entrant->display_name,
                    ])
                    ->values()
                : [],
            'rounds' => $rounds,
        ];
    }

    /**
     * Shape a round-robin group stage into groups, each carrying its matchdays
     * and live standings.
     *
     * @return array<string, mixed>
     */
    private function shapeGroupStage(Bracket $bracket, BracketService $standings): array
    {
        $bracket->loadMissing([
            'groups.entrants.registration.team',
            'groups.entrants.registration.players',
            'groups.slots.entrantA.team',
            'groups.slots.entrantA.players',
            'groups.slots.entrantB.team',
            'groups.slots.entrantB.players',
            'groups.slots.winnerEntrant.team',
            'groups.slots.winnerEntrant.players',
            'groups.slots.matches.state',
        ]);

        $groups = $bracket->groups
            ->map(fn (BracketGroup $group) => $this->shapeGroup($group, $standings))
            ->values();

        return [
            'id' => $bracket->id,
            'type' => $bracket->type,
            'status' => $bracket->status,
            'size' => $bracket->size,
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeGroup(BracketGroup $group, BracketService $standings): array
    {
        $matchdays = $group->slots
            ->groupBy('round')
            ->sortKeys()
            ->map(fn ($slots, $round) => [
                'round' => (int) $round,
                'slots' => $slots
                    ->sortBy('position')
                    ->values()
                    ->map(fn (BracketSlot $slot) => self::shapeSlot($slot))
                    ->values(),
            ])
            ->values();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'position' => $group->position,
            'advance_count' => $group->advance_count,
            'double_round_robin' => $group->double_round_robin,
            'winner_entrant_ids' => $group->winnerIds(),
            'entrants' => $group->entrants
                ->map(fn ($membership) => self::shapeEntrant($membership->registration))
                ->filter()
                ->values(),
            'matchdays' => $matchdays,
            'standings' => $standings->standings($group)->values(),
        ];
    }

    private static function shapeSlot(BracketSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'round' => $slot->round,
            'position' => $slot->position,
            'seed' => $slot->seed,
            'bye' => $slot->bye,
            'label' => $slot->label,
            'entrant_a' => self::shapeEntrant($slot->entrantA),
            'entrant_b' => self::shapeEntrant($slot->entrantB),
            'winner_entrant' => self::shapeEntrant($slot->winnerEntrant),
            'advance_reason' => $slot->advance_reason,
            'is_ready' => $slot->isReady(),
            'is_complete' => $slot->isComplete(),
            'matches' => $slot->matches->map(fn (TennisMatch $match) => [
                'id' => $match->id,
                'side_a_name' => $match->side_a_name,
                'side_b_name' => $match->side_b_name,
                'sets_to_win' => $match->sets_to_win,
                'games_to_win' => $match->games_to_win,
                'extend_at_tie' => $match->extend_at_tie,
                'extend_tiebreak' => $match->extend_tiebreak,
                'sudden_death' => $match->sudden_death,
                'tiebreak_to' => $match->tiebreak_to,
                'status' => $match->status,
                'format_label' => $match->format_label,
                'winner_side' => $match->state?->win,
                'share_url' => $match->publicScoreUrl(),
            ])->values(),
        ];
    }

    private static function shapeEntrant(?TournamentRegistration $entrant): ?array
    {
        if ($entrant === null) {
            return null;
        }

        return [
            'id' => $entrant->id,
            'name' => $entrant->display_name,
            'team' => $entrant->team ? [
                'id' => $entrant->team->id,
                'name' => $entrant->team->name,
            ] : null,
            'players' => $entrant->players
                ->map(fn ($player) => [
                    'id' => $player->id,
                    'name' => $player->name,
                ])
                ->values(),
        ];
    }
}
