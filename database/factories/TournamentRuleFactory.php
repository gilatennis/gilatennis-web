<?php

namespace Database\Factories;

use App\Models\TournamentRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRule>
 */
class TournamentRuleFactory extends Factory
{
    protected $model = TournamentRule::class;

    public function definition(): array
    {
        return [
            'body' => fn () => fake()->randomElement([
                'Players must be ready 15 minutes before their match time.',
                'USTA point penalty system applies for delays.',
                'Maximum 1 coach allowed per match, confined to designated areas.',
                'Players are responsible for calling their own lines.',
                'No electronic devices permitted on court during play.',
                'Draws will be posted by 6 PM the day before the event.',
            ]),
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
