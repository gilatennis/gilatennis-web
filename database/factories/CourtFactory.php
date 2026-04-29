<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $surfaces = ['hard', 'clay', 'grass', 'indoor_hard'];
        $surface = fake()->randomElement($surfaces);

        return [
            'slug' => fn () => Str::slug(fake()->unique()->words(2, true)),
            'name' => fn () => fake()->randomElement([
                'Boler Mountain Courts',
                'Riverside Tennis Center',
                'Sunset Park Clay',
                'Centercourt Club',
            ]).' '.fake()->numberBetween(1, 99),
            'city' => fake()->city(),
            'surface' => $surface,
            'rating' => fake()->randomFloat(1, 4.0, 5.0),
            'review_count' => fake()->numberBetween(12, 340),
            'content_type' => fake()->randomElement(['video', 'article']),
            'duration' => fn ($attrs) => $attrs['content_type'] === 'video'
                ? fake()->time('i:s')
                : fake()->numberBetween(4, 12).' min',
            'image_alt' => fn () => fake()->words(3, true),
            'excerpt' => fn () => fake()->paragraph(2),
            'is_featured' => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn ($attrs) => ['content_type' => 'video']);
    }

    public function article(): static
    {
        return $this->state(fn ($attrs) => ['content_type' => 'article']);
    }
}
