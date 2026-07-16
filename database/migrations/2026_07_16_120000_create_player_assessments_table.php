<?php

use App\Enums\PlayerAssessmentStatus;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')
                ->unique()
                ->constrained((new TournamentRegistrationPlayer)->getTable())
                ->cascadeOnDelete();
            $table->string('status')->default(PlayerAssessmentStatus::Unreviewed->value);
            $table->text('reason')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')
                ->nullable()
                ->constrained((new User)->getTable())
                ->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_assessments');
    }
};
