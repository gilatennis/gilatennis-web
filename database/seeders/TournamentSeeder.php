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
            'dates' => 'June 27–28, 2026',
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
    }
}
