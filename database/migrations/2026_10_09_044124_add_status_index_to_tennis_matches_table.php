<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public live view filters matches by status on every poll, so give
     * that column an index instead of scanning the table.
     */
    public function up(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
