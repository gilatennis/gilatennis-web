<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $types = ['review', 'tip', 'strategy', 'fitness'];
        $type = fake()->randomElement($types);
        $kickers = [
            'review' => 'Gear Review',
            'tip' => 'Tennis Tip',
            'strategy' => 'Pro Watch',
            'fitness' => "Buyer's Guide",
        ];

        return [
            'slug' => fn () => Str::slug(fake()->unique()->words(4, true)),
            'type' => $type,
            'kicker' => $kickers[$type],
            'title' => fn () => fake()->sentence(8),
            'excerpt' => fn () => fake()->paragraph(2),
            'body' => fake()->optional()->paragraphs(4, true),
            'image_alt' => fn () => fake()->words(3, true),
            'author' => fn () => fake()->randomElement(['M. Alvarez', 'Coach Reyes', 'The Editors', 'Gear desk', 'T. Walsh']),
            'reading_minutes' => fake()->numberBetween(4, 12),
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }

    public function review(): static
    {
        return $this->state(fn ($attrs) => [
            'type' => 'review',
            'kicker' => 'Gear Review',
        ]);
    }

    public function tip(): static
    {
        return $this->state(fn ($attrs) => [
            'type' => 'tip',
            'kicker' => 'Tennis Tip',
        ]);
    }
}
