<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\TennisMatch;
use App\Models\TournamentRegistration;

trait ShapesBracket
{
    private static function shapeBracket(?Bracket $bracket): ?array
    {
        if ($bracket === null) {
            return null;
        }

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
            'rounds' => $rounds,
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
                'tiebreak_to' => $match->tiebreak_to,
                'status' => $match->status,
                'format_label' => $match->format_label,
                'winner_side' => $match->state?->win,
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
            'name' => $entrant->team?->name ?? $entrant->name,
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
