<?php

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\BracketGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BracketGroup>
 */
class BracketGroupFactory extends Factory
{
    protected $model = BracketGroup::class;

    public function definition(): array
    {
        return [
            'bracket_id' => Bracket::factory(),
            'name' => 'Group A',
            'position' => 0,
            'advance_count' => 1,
            'double_round_robin' => false,
        ];
    }

    public function forBracket(Bracket $bracket): static
    {
        return $this->state(fn () => ['bracket_id' => $bracket->id]);
    }

    public function named(string $name, int $position): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'position' => $position,
        ]);
    }
}
