<?php

namespace Database\Seeders;

use App\Models\Court;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $courts = [
            [
                'slug' => 'memorial-park-tennis-center',
                'name' => 'Memorial Park Tennis Center',
                'city' => 'Houston, TX',
                'surface' => 'hard',
                'rating' => 4.8,
                'review_count' => 312,
                'content_type' => 'video',
                'duration' => '6:42',
                'image_alt' => 'court · houston · twilight',
                'excerpt' => '24 lit hard courts, walk-on friendly, the gold standard for public play in Texas.',
                'is_featured' => true,
                'amenities' => ['Lights', 'Pro Shop', 'Stringing'],
            ],
            [
                'slug' => 'riverside-clay-club',
                'name' => 'Riverside Clay Club',
                'city' => 'Asheville, NC',
                'surface' => 'clay',
                'rating' => 4.9,
                'review_count' => 188,
                'content_type' => 'article',
                'duration' => '5 min',
                'image_alt' => 'court · red clay · mountain backdrop',
                'excerpt' => 'Hand-rolled red clay courts maintained daily — a rarity in the Southeast.',
                'is_featured' => true,
                'amenities' => ['Lights', 'Locker Room', 'Cafe'],
            ],
            [
                'slug' => 'coastline-grass-pavilion',
                'name' => 'Coastline Grass Pavilion',
                'city' => 'Newport, RI',
                'surface' => 'grass',
                'rating' => 4.7,
                'review_count' => 94,
                'content_type' => 'video',
                'duration' => '8:10',
                'image_alt' => 'court · grass · seaside',
                'excerpt' => 'Six championship-grade grass courts. We were skeptical of public access — pleasantly surprised.',
                'is_featured' => true,
                'amenities' => ['Pro Shop', 'Coaching', 'Member Only'],
            ],
            [
                'slug' => 'sunset-indoor-athletics',
                'name' => 'Sunset Indoor Athletics',
                'city' => 'Portland, OR',
                'surface' => 'indoor_hard',
                'rating' => 4.6,
                'review_count' => 256,
                'content_type' => 'article',
                'duration' => '7 min',
                'image_alt' => 'court · indoor · daylight roof',
                'excerpt' => 'Year-round indoor play under translucent roof panels. Best winter option on the West Coast.',
                'is_featured' => true,
                'amenities' => ['Indoor', 'Lights', 'Cafe', 'Stringing'],
            ],
        ];

        foreach ($courts as $data) {
            $amenities = $data['amenities'];
            unset($data['amenities']);

            $court = Court::factory()->create($data);

            foreach ($amenities as $name) {
                $court->amenities()->create(['name' => $name]);
            }
        }
    }
}
