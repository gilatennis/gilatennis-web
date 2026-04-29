<?php

namespace Database\Factories;

use App\Models\CourtAmenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtAmenity>
 */
class CourtAmenityFactory extends Factory
{
    protected $model = CourtAmenity::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Lights', 'Pro Shop', 'Stringing', 'Cafe', 'Parking', 'Restrooms', 'Water', 'Seating']),
        ];
    }
}
