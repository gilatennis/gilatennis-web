<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Settings::query()->updateOrCreate(
            ['id' => 1],
            ['contact_wa_number' => '+6287887240444']
        );
    }
}
