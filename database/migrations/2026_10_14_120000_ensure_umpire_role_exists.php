<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure the `umpire` role exists in every environment. Seeders are not
     * run automatically on deploy, so the role is upserted here to keep the
     * gate (`access-umpire`) and the umpire login flow working out of the box.
     */
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'slug' => User::ROLE_UMPIRE,
            'name' => User::ROLE_LABELS[User::ROLE_UMPIRE],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', User::ROLE_UMPIRE)->delete();
    }
};
