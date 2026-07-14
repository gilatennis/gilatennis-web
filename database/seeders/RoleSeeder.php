<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['slug' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Admin'],
        );

        Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Admin'],
        );
    }
}
