<?php

namespace Database\Factories;

use App\Models\TournamentScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentScheduleDay>
 */
class TournamentScheduleDayFactory extends Factory
{
    protected $model = TournamentScheduleDay::class;

    public function definition(): array
    {
        return [
            'day_label' => fake()->dayOfWeekThatIsBetween('Monday', 'Saturday'),
            'sort_order' => fake()->numberBetween(0, 4),
        ];
    }
}
