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
            $table->date('start')->nullable()->change();
            $table->date('end')->nullable()->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tournaments MODIFY COLUMN status ENUM('draft','open','closed','completed','canceled') NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('tournaments')->where('status', 'canceled')->update(['status' => 'closed']);
            DB::statement("ALTER TABLE tournaments MODIFY COLUMN status ENUM('draft','open','closed','completed') NOT NULL DEFAULT 'open'");
        }

        DB::table('tournaments')->whereNull('start')->update(['start' => '1970-01-01']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('start')->nullable()->change();
            $table->string('end')->nullable()->change();
        });
    }
};
