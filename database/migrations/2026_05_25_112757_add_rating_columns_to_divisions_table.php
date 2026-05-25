<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->string('rating_type', 20)->nullable()->after('fee');
            $table->decimal('rating_min', 4, 1)->nullable()->after('rating_type');
            $table->decimal('rating_max', 4, 1)->nullable()->after('rating_min');
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['rating_type', 'rating_min', 'rating_max']);
        });
    }
};
