<?php

namespace Database\Factories;

use App\Models\ArticleProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleProduct>
 */
class ArticleProductFactory extends Factory
{
    protected $model = ArticleProduct::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(99, 399);

        return [
            'name' => fn () => fake()->randomElement([
                'Pro Staff 97 v14',
                'Aero Pro X 100 · 305g',
                'EZONE 100',
                'Blade v8 98',
                'Pure Strike 100',
                'Speed Pro 2025',
            ]),
            'price_label' => '$'.$price,
            'original_price' => fake()->boolean(40) ? '$'.($price + fake()->numberBetween(20, 50)) : null,
        ];
    }
}
