<?php

namespace Database\Factories;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        $surfaces = ['hard', 'clay', 'grass', 'indoor_hard'];
        $surface = fake()->randomElement($surfaces);
        $fees = [35000, 45000, 55000, 65000, 75000, 95000];
        $drawSizes = [16, 24, 32, 48];

        return [
            'slug' => fn () => Str::slug(fake()->unique()->words(3, true)),
            'name' => fn () => fake()->randomElement([
                'NorCal Spring Open',
                'Sunset Doubles Classic',
                'Red Clay Challenge',
                'Junior Champions Cup',
                'Gila Open: Summer Smash',
            ]).' '.fake()->year(),
            'tagline' => fake()->optional()->sentence(),
            'dates' => fn () => fake()->dateTimeBetween('+2 weeks', '+3 months')->format('M j').' – '.fake()->dateTimeBetween('+3 weeks', '+3 months')->format('M j, Y'),
            'starts_label' => 'Sat 8:00 AM PT',
            'city' => fake()->city(),
            'venue' => fake()->optional()->company(),
            'surface' => $surface,
            'draw_size' => fake()->randomElement($drawSizes),
            'spots_left' => fn ($attrs) => min(fake()->numberBetween(0, $attrs['draw_size'] ?? 24), $attrs['draw_size'] ?? 24),
            'fee' => fake()->randomElement($fees),
            'utr_min' => fake()->optional()->randomFloat(1, 1, 5),
            'utr_max' => fake()->optional()->randomFloat(1, 5, 15),
            'format' => 'Single Elimination, best-of-3',
            'ball_type' => 'Penn Championship',
            'registration_closes' => fn ($attrs) => fake()->dateTimeBetween('now', '+3 months'),
            'event_types' => ['singles', 'doubles', 'both'],
            'perks' => null,
            'is_featured' => false,
            'status' => 'open',
        ];
    }

    public function featured(): static
    {
        return $this->state(fn ($attrs) => ['is_featured' => true]);
    }

    public function clay(): static
    {
        return $this->state(fn ($attrs) => ['surface' => 'clay']);
    }

    public function hard(): static
    {
        return $this->state(fn ($attrs) => ['surface' => 'hard']);
    }

    public function closed(): static
    {
        return $this->state(fn ($attrs) => ['status' => 'closed']);
    }
}
