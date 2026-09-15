<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Database\Seeders\TournamentSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tournaments:seed-demo {--fresh : Delete all tournaments and related data before seeding}')]
#[Description('Seed a demo team tournament with 12 paid teams for bracket testing (development only)')]
class SeedDemoTournamentCommand extends Command
{
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->wipeTournaments();
            $this->info('Removed all existing tournaments and related data.');
        }

        $this->call('db:seed', ['--class' => TournamentSeeder::class]);

        $this->info('Demo tournament seeded. Generate the bracket from the admin division page.');

        return self::SUCCESS;
    }

    private function wipeTournaments(): void
    {
        TennisMatch::query()->delete();
        Team::query()->delete();
        Tournament::all()->each->delete();
    }
}
