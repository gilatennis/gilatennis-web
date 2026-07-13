<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRegistrationPlayer>
 */
class TournamentRegistrationPlayerFactory extends Factory
{
    protected $model = TournamentRegistrationPlayer::class;

    public function definition(): array
    {
        $ratingType = fake()->randomElement(['UTR', 'ITN', 'USTA']);

        return [
            'tournament_registration_id' => TournamentRegistration::factory(),
            'tournament_id' => Tournament::factory(),
            'team_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-12 years')->format('Y-m-d'),
            'rating_type' => $ratingType,
            'rating_value' => fake()->randomFloat(1, 1, 15),
            'sort_order' => 0,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn () => [
            'team_id' => $team->id,
        ]);
    }

    public function withSortOrder(int $order): static
    {
        return $this->state(fn () => [
            'sort_order' => $order,
        ]);
    }
}
