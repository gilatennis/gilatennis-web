<?php

namespace Database\Factories;

use App\Models\TennisMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TennisMatch>
 */
class TennisMatchFactory extends Factory
{
    protected $model = TennisMatch::class;

    public function definition(): array
    {
        $players = [
            'Novak Djokovic', 'Carlos Alcaraz', 'Jannik Sinner', 'Daniil Medvedev',
            'Roger Federer', 'Rafael Nadal', 'Andy Murray', 'Stan Wawrinka',
            'Pete Sampras', 'Andre Agassi', 'Lleyton Hewitt', 'Marat Safin',
        ];

        $a = fake()->randomElement($players);
        $b = fake()->randomElement(array_diff($players, [$a]));

        return [
            'side_a_name' => $a,
            'side_b_name' => $b,
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
            'status' => 'pending',
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => 'canceled',
            'ended_at' => now(),
        ]);
    }

    public function fast4(): static
    {
        return $this->state(fn () => [
            'sets_to_win' => 2,
            'games_to_win' => 4,
            'extend_at_tie' => false,
            'tiebreak_to' => 5,
        ]);
    }

    public function shortSet5(): static
    {
        return $this->state(fn () => [
            'sets_to_win' => 2,
            'games_to_win' => 5,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
        ]);
    }
}
