<?php

namespace Database\Factories;

use App\Models\TournamentScheduleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentScheduleItem>
 */
class TournamentScheduleItemFactory extends Factory
{
    protected $model = TournamentScheduleItem::class;

    public function definition(): array
    {
        return [
            'label' => fn () => fake()->randomElement(['R32', 'QF', 'SF', 'Final']).' '.fake()->time('h:i A'),
            'sort_order' => fake()->numberBetween(0, 8),
        ];
    }
}
