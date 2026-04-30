<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->string('rating_type', 20)->nullable()->change();
            $table->decimal('rating_value', 4, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->string('rating_type', 20)->nullable(false)->change();
            $table->decimal('rating_value', 4, 1)->nullable(false)->change();
        });
    }
};
