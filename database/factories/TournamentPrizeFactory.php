<?php

namespace Database\Factories;

use App\Models\TournamentPrize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentPrize>
 */
class TournamentPrizeFactory extends Factory
{
    protected $model = TournamentPrize::class;

    public function definition(): array
    {
        return [
            'place' => fake()->randomElement(['Champion', 'Finalist', 'Semifinalist', 'Quarterfinalist']),
            'value' => fn () => fake()->randomElement([
                '$600 + Aero Pro X racket',
                '$400 + Wilson Bag',
                '$200',
                '$100 + string job',
                '$75 gift card',
                'Medal + trophy',
            ]),
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
