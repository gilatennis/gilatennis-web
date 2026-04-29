<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'subscribed_at' => now()->subDays(fake()->numberBetween(1, 365)),
            'unsubscribed_at' => null,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'unsubscribed_at' => now()->subDays(fake()->numberBetween(1, 30))->toDateTimeString(),
        ]);
    }
}
