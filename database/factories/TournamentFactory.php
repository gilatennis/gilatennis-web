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
            'dates' => fn () => fake()->dateTimeBetween('+2 weeks', '+3 months')->format('M j').' - '.fake()->dateTimeBetween('+3 weeks', '+3 months')->format('M j, Y'),
            'start' => '2026-06-27',
            'end' => '2026-06-28',
            'city' => fake()->city(),
            'venue' => fake()->optional()->company(),
            'surface' => fake()->randomElement($surfaces),
            'ball_type' => 'Penn Championship',
            'venue_type' => 'indoor',
            'registration_closes' => fn ($attrs) => fake()->dateTimeBetween('now', '+3 months'),
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
