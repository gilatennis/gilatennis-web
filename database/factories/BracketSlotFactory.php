<?php

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\BracketSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BracketSlot>
 */
class BracketSlotFactory extends Factory
{
    protected $model = BracketSlot::class;

    public function definition(): array
    {
        return [
            'bracket_id' => Bracket::factory(),
            'round' => 1,
            'position' => 0,
            'seed' => null,
            'bye' => false,
            'label' => null,
        ];
    }

    public function forBracket(Bracket $bracket): static
    {
        return $this->state(fn () => ['bracket_id' => $bracket->id]);
    }

    public function round(int $round, int $position): static
    {
        return $this->state(fn () => [
            'round' => $round,
            'position' => $position,
        ]);
    }
}
