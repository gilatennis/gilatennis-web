<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserImportSeeder::class,
            TournamentSeeder::class,
            ArticleSeeder::class,
            CourtSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin'],
        );

        if (! $user->roles()->where('role_id', $adminRole->id)->exists()) {
            $user->roles()->attach($adminRole);
        }
    }
}
