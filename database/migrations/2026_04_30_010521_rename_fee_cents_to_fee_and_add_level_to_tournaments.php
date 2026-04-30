<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->after('fee_cents')->default(0);
            $table->string('level')->nullable()->after('ball_type');
        });

        DB::statement('UPDATE tournaments SET fee = fee_cents');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedInteger('fee_cents')->after('spots_left')->default(0);
            $table->dropColumn('level');
        });

        DB::statement('UPDATE tournaments SET fee_cents = CAST(fee AS UNSIGNED)');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
};
