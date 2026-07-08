<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('contact_person_name')->nullable()->after('cover_image');
            $table->string('contact_person_phone')->nullable()->after('contact_person_name');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['contact_person_name', 'contact_person_phone']);
        });
    }
};
