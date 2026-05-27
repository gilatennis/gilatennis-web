<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        $category = fake()->randomElement(['singles', 'doubles', 'team']);
        $drawSizes = [8, 16, 24, 32];
        $fees = [35000, 45000, 55000, 65000, 75000, 95000];
        $ratingTypes = ['UTR', 'ITN', 'USTA'];
        $ratingType = fake()->optional(0.6)->randomElement($ratingTypes);

        return [
            'tournament_id' => Tournament::factory(),
            'name' => fake()->unique()->words(2, true),
            'slug' => fn ($attrs) => Str::slug($attrs['name']),
            'participation_category' => $category,
            'draw_size' => fake()->randomElement($drawSizes),
            'fee' => fake()->randomElement($fees),
            'format' => null,
            'gender' => fake()->optional()->randomElement(['mens', 'womens', 'mixed']),
            'rating_type' => $ratingType,
            'rating_min' => $ratingType ? fake()->randomFloat(1, 1, 5) : null,
            'rating_max' => $ratingType ? fake()->randomFloat(1, 5, 15) : null,
            'min_age' => null,
            'max_age' => null,
            'min_team_size' => $category === 'team' ? fake()->numberBetween(2, 3) : null,
            'max_team_size' => $category === 'team' ? fake()->numberBetween(3, 5) : null,
            'sort_order' => 0,
        ];
    }

    public function singles(): static
    {
        return $this->state(fn () => [
            'participation_category' => 'singles',
            'name' => 'Singles',
            'slug' => 'singles',
            'rating_type' => 'UTR',
            'rating_min' => 1.0,
            'rating_max' => 16.5,
            'min_team_size' => null,
            'max_team_size' => null,
        ]);
    }

    public function doubles(): static
    {
        return $this->state(fn () => [
            'participation_category' => 'doubles',
            'name' => 'Doubles',
            'slug' => 'doubles',
            'rating_type' => 'UTR',
            'rating_min' => 1.0,
            'rating_max' => 16.5,
            'min_team_size' => null,
            'max_team_size' => null,
        ]);
    }

    public function team(): static
    {
        return $this->state(fn () => [
            'participation_category' => 'team',
            'name' => 'Team Competition',
            'slug' => 'team-competition',
            'rating_type' => null,
            'rating_min' => null,
            'rating_max' => null,
            'min_team_size' => fake()->numberBetween(2, 3),
            'max_team_size' => fake()->numberBetween(3, 5),
        ]);
    }

    public function forTournament(Tournament $tournament): static
    {
        return $this->state(fn () => [
            'tournament_id' => $tournament->id,
        ]);
    }
}
