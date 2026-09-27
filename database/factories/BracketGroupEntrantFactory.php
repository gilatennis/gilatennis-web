<?php

namespace Database\Factories;

use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BracketGroupEntrant>
 */
class BracketGroupEntrantFactory extends Factory
{
    protected $model = BracketGroupEntrant::class;

    public function definition(): array
    {
        return [
            'bracket_group_id' => BracketGroup::factory(),
            'tournament_registration_id' => TournamentRegistration::factory(),
            'position' => 0,
        ];
    }

    public function inGroup(BracketGroup $group): static
    {
        return $this->state(fn () => ['bracket_group_id' => $group->id]);
    }

    public function forEntrant(TournamentRegistration $entrant, int $position = 0): static
    {
        return $this->state(fn () => [
            'tournament_registration_id' => $entrant->id,
            'position' => $position,
        ]);
    }
}
