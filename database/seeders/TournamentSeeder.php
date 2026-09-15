<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    private const TEAM_COUNT = 12;

    private const PLAYERS_PER_TEAM = 10;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $gilaopen = Tournament::firstOrCreate(
            ['slug' => 'turnamen-gilatennis-2026'],
            [
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
            ],
        );

        $division = $gilaopen->divisions()->firstOrCreate(
            ['slug' => 'team-competition'],
            [
                'name' => 'Team Competition',
                'participation_category' => 'team',
                'draw_size' => self::TEAM_COUNT,
                'fee' => 2500000,
                'format' => 'Group Stage + Knockout',
                'min_team_size' => 6,
                'max_team_size' => self::PLAYERS_PER_TEAM,
                'sort_order' => 0,
            ],
        );

        $this->seedTeams($gilaopen, $division);
    }

    private function seedTeams(Tournament $tournament, Division $division): void
    {
        if ($division->paidRegistrations()->exists()) {
            return;
        }

        foreach (range(1, self::TEAM_COUNT) as $index) {
            $team = Team::factory()->create([
                'name' => sprintf('Team %02d', $index),
            ]);

            $registration = TournamentRegistration::factory()
                ->forTournament($tournament)
                ->paid()
                ->create([
                    'division_id' => $division->id,
                    'team_id' => $team->id,
                    'name' => $team->name,
                    'confirmation_code' => sprintf('GO-2026-T%02d', $index),
                    'amount_cents' => (int) ($division->fee * 100),
                    'currency' => 'IDR',
                ]);

            foreach (range(0, self::PLAYERS_PER_TEAM - 1) as $order) {
                TournamentRegistrationPlayer::factory()
                    ->forTeam($team)
                    ->withSortOrder($order)
                    ->create([
                        'tournament_registration_id' => $registration->id,
                        'tournament_id' => $tournament->id,
                        'gender' => $order % 2 === 0 ? 'male' : 'female',
                    ]);
            }
        }
    }
}
