<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('division_player_field_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 32);
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->unique(['division_id', 'field_key']);
        });

        $defaults = [
            ['name', true],
            ['gender', true],
            ['email', true],
            ['phone', true],
            ['level', true],
            ['dob', false],
        ];

        $now = now();

        DB::table('divisions')->orderBy('id')->each(function ($division) use ($defaults, $now) {
            foreach ($defaults as [$key, $required]) {
                DB::table('division_player_field_requirements')->insert([
                    'division_id' => $division->id,
                    'field_key' => $key,
                    'required' => $required,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_player_field_requirements');
    }
};
