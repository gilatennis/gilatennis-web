<?php

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\BracketGroup;
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
            'bracket_group_id' => null,
            'round' => 1,
            'position' => 0,
            'leg' => 1,
            'seed' => null,
            'bye' => false,
            'label' => null,
        ];
    }

    public function forBracket(Bracket $bracket): static
    {
        return $this->state(fn () => ['bracket_id' => $bracket->id]);
    }

    public function inGroup(BracketGroup $group): static
    {
        return $this->state(fn () => [
            'bracket_id' => $group->bracket_id,
            'bracket_group_id' => $group->id,
        ]);
    }

    public function round(int $round, int $position): static
    {
        return $this->state(fn () => [
            'round' => $round,
            'position' => $position,
        ]);
    }
}
