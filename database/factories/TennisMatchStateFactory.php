<?php

namespace Database\Factories;

use App\Models\TennisMatch;
use App\Models\TennisMatchState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TennisMatchState>
 */
class TennisMatchStateFactory extends Factory
{
    protected $model = TennisMatchState::class;

    public function definition(): array
    {
        return TennisMatchState::defaults();
    }

    public function forMatch(TennisMatch $match): static
    {
        return $this->state(fn () => ['tennis_match_id' => $match->id]);
    }
}
