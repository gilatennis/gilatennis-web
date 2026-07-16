<?php

namespace App\Models;

use App\Enums\PlayerAssessmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'status',
        'reason',
        'video_url',
        'notes',
        'assessed_by',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlayerAssessmentStatus::class,
            'assessed_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistrationPlayer::class, 'player_id');
    }
}
