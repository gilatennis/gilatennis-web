<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->string('format')->nullable()->after('fee');
            $table->dropColumn('spots_left');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['draw_size', 'spots_left', 'fee', 'format', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn('format');
            $table->integer('spots_left')->unsigned()->default(0)->after('draw_size');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('draw_size')->unsigned()->after('surface');
            $table->integer('spots_left')->unsigned()->after('draw_size');
            $table->decimal('fee', 10, 2)->default(0.00)->after('spots_left');
            $table->string('format')->after('fee');
            $table->string('level')->nullable()->after('format');
        });
    }
};
