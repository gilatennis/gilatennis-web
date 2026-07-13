<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TournamentRegistration>
 */
class TournamentRegistrationFactory extends Factory
{
    protected $model = TournamentRegistration::class;

    public function definition(): array
    {
        $feeCents = fake()->randomElement([35000, 45000, 55000, 65000]);

        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => null,
            'confirmation_code' => 'GO-'.date('Y').'-'.strtoupper(Str::random(4)),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-12 years')->format('Y-m-d'),
            'payment_status' => 'pending',
            'payment_reference' => null,
            'payment_url' => null,
            'amount_cents' => $feeCents,
            'currency' => 'IDR',
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'paid',
            'payment_reference' => 'DK-'.strtoupper(Str::random(8)),
            'paid_at' => now()->toDateTimeString(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'failed',
        ]);
    }

    public function forTournament(Tournament $tournament): static
    {
        return $this->state(fn ($attrs) => [
            'tournament_id' => $tournament->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn ($attrs) => [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
