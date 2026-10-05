<?php

namespace Database\Factories;

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Models\Bracket;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bracket>
 */
class BracketFactory extends Factory
{
    protected $model = Bracket::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Draft,
            'size' => 8,
        ];
    }

    public function forDivision(Division $division): static
    {
        return $this->state(fn () => ['division_id' => $division->id]);
    }
}
