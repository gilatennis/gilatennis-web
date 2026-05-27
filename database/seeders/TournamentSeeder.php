<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        $gilaopen = Tournament::factory()->create([
            'slug' => 'turnamen-gilatennis-2026',
            'name' => 'TURNAMEN GILATENNIS',
            'tagline' => 'Untuk yang gila-gila aja',
            'dates' => 'June 27-28, 2026',
            'start' => '2026-06-27',
            'end' => '2026-06-28',
            'city' => 'Bekasi',
            'venue' => 'Crown Racquet Club Bekasi, Summarecon Crown Gading, Jl. Bulevar Timur, Setia Asih Kec. Tarumajaya, Kab. Bekasi',
            'surface' => 'hard',
            'ball_type' => null,
            'venue_type' => 'both',
            'registration_closes' => '2026-06-20 23:59:00',
            'perks' => [],
            'is_featured' => true,
            'status' => 'open',
        ]);

        $gilaopen->divisions()->create([
            'name' => 'Team Competition',
            'slug' => 'team-competition',
            'participation_category' => 'team',
            'draw_size' => 12,
            'fee' => 2500000,
            'format' => 'Group Stage + Knockout',
            'min_team_size' => 6,
            'max_team_size' => 8,
            'sort_order' => 0,
        ]);
    }
}
