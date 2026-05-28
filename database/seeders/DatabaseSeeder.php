<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SuperAdminSeeder::class]);

        if (app()->environment('production')) {
            return;
        }

        // INFO: only run on development
        $this->call([
            UserImportSeeder::class,
            TournamentSeeder::class,
            ArticleSeeder::class,
            CourtSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
