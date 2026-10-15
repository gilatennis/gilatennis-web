<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('ended_at');
        });

        DB::table('tennis_matches')
            ->whereNull('share_token')
            ->pluck('id')
            ->each(function (int $id): void {
                DB::table('tennis_matches')
                    ->where('id', $id)
                    ->update(['share_token' => Str::random(48)]);
            });
    }

    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
