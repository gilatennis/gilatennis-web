<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'slug' => 'pro-staff-97-v14-review',
                'type' => 'review',
                'kicker' => 'Gear Review',
                'title' => "Wilson's new Pro Staff is a love letter to feel.",
                'excerpt' => '97 sq in, 315g, and a 16x19 pattern that punches above its weight on fast courts. We spent a month with the v14.',
                'image_alt' => 'racket · pro staff studio shot',
                'author' => 'M. Alvarez',
                'reading_minutes' => 7,
                'published_at' => now()->subDays(3),
                'products' => [
                    ['name' => 'Pro Staff 97 v14', 'price_label' => '$259', 'original_price' => '$289'],
                ],
            ],
            [
                'slug' => 'split-step-timing-pro-tip',
                'type' => 'tip',
                'kicker' => 'Tennis Tip',
                'title' => 'The split-step timing trick that pros never miss.',
                'excerpt' => 'Land milliseconds after your opponent\'s contact — here\'s the drill we teach to fix it.',
                'image_alt' => 'split step · slow-mo · court',
                'author' => 'Coach Reyes',
                'reading_minutes' => 5,
                'published_at' => now()->subDays(7),
                'products' => [],
            ],
            [
                'slug' => 'mirage-clay-pro-review',
                'type' => 'review',
                'kicker' => 'Gear Review',
                'title' => 'Are these the most comfortable clay shoes ever?',
                'excerpt' => 'We put 200km on the new Mirage Clay Pro across red and green clay. Here\'s the verdict.',
                'image_alt' => 'shoe · sole detail · clay dust',
                'author' => 'L. Singh',
                'reading_minutes' => 9,
                'published_at' => now()->subDays(11),
                'products' => [
                    ['name' => 'Mirage Clay Pro', 'price_label' => '$139', 'original_price' => null],
                ],
            ],
            [
                'slug' => 'come-to-net-vs-stay-back',
                'type' => 'strategy',
                'kicker' => 'Pro Watch',
                'title' => 'When to come to net vs. stay back: a decision tree.',
                'excerpt' => 'Court position is a math problem. Here\'s the framework that wins more points.',
                'image_alt' => 'court overhead · tactics diagram',
                'author' => 'J. Park',
                'reading_minutes' => 6,
                'published_at' => now()->subDays(15),
                'products' => [],
            ],
            [
                'slug' => 'polyester-strings-ranked',
                'type' => 'review',
                'kicker' => 'Gear Review',
                'title' => 'Polyester strings, ranked: who actually feels different?',
                'excerpt' => 'We strung 14 reels at identical tension and let club players blind-test. The results surprised us.',
                'image_alt' => 'string reel · color array',
                'author' => 'Gear desk',
                'reading_minutes' => 11,
                'published_at' => now()->subDays(20),
                'products' => [
                    ['name' => 'Luxilon ALU Power', 'price_label' => '$22', 'original_price' => '$26'],
                ],
            ],
            [
                'slug' => 'tennis-mobility-12-minutes',
                'type' => 'fitness',
                'kicker' => "Buyer's Guide",
                'title' => 'Tennis-specific mobility in 12 minutes a day.',
                'excerpt' => 'The shoulder, hip and ankle routine our touring physios actually use. No equipment needed.',
                'image_alt' => 'athlete · mobility drill',
                'author' => 'Dr. K. Tan',
                'reading_minutes' => 8,
                'published_at' => now()->subDays(25),
                'products' => [],
            ],
        ];

        foreach ($articles as $data) {
            $products = $data['products'];
            unset($data['products']);

            $article = Article::factory()->create(array_merge($data, ['is_published' => true]));

            foreach ($products as $product) {
                $article->products()->create($product);
            }
        }
    }
}
