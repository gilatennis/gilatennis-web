<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        $gilaopen = Tournament::factory()->create([
            'slug' => 'gilatennis-beginner-invitational-2026',
            'name' => 'Gilatennis Beginner Invitational 2026',
            'tagline' => null,
            'dates' => 'June 27-28, 2026',
            'starts_label' => 'June 28',
            'city' => 'Bekasi',
            'venue' => 'Crown Racquet Club Bekasi',
            'surface' => 'indoor_hard',
            'draw_size' => 32,
            'spots_left' => 32,
            'fee' => 600000.00,
            'level' => 'Beginner',
            'perks' => ['Prize money', 'Crown Racquet Club facilities', 'T-Shirt + Wilson Vouchers'],
            'format' => 'Round Robin + Knockout',
            'ball_type' => 'Nassau Smash',
            'registration_closes' => '2026-06-20 23:59:00',
            'is_featured' => true,
            'status' => 'open',
        ]);

        $gilaopen->divisions()->createMany([
            [
                'name' => 'Singles',
                'slug' => 'singles',
                'participation_category' => 'singles',
                'draw_size' => 32,
                'spots_left' => 32,
                'fee' => 600000.00,
                'rating_type' => 'UTR',
                'rating_min' => 1.0,
                'rating_max' => 16.5,
                'sort_order' => 0,
            ],
            [
                'name' => 'Doubles',
                'slug' => 'doubles',
                'participation_category' => 'doubles',
                'draw_size' => 16,
                'spots_left' => 16,
                'fee' => 800000.00,
                'rating_type' => 'UTR',
                'rating_min' => 1.0,
                'rating_max' => 16.5,
                'sort_order' => 1,
            ],
            [
                'name' => 'Team Competition',
                'slug' => 'team',
                'participation_category' => 'team',
                'draw_size' => 8,
                'spots_left' => 8,
                'fee' => 1200000.00,
                'rating_type' => null,
                'rating_min' => null,
                'rating_max' => null,
                'min_team_size' => 2,
                'max_team_size' => 4,
                'sort_order' => 2,
            ],
        ]);
    }
}
