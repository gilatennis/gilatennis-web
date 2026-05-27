<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_prizes', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('id')->constrained()->cascadeOnDelete();

            $table->dropForeign(['tournament_id']);
            $table->unsignedBigInteger('tournament_id')->nullable()->change();
            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();

            $table->unique(['division_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_prizes', function (Blueprint $table) {
            $table->dropUnique(['division_id', 'sort_order']);

            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');

            $table->dropForeign(['tournament_id']);
            $table->unsignedBigInteger('tournament_id')->nullable(false)->change();
            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
        });
    }
};
