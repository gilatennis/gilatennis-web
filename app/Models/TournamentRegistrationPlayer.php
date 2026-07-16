<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TournamentRegistrationPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_registration_id', 'tournament_id', 'team_id',
        'name', 'email', 'gender', 'phone', 'date_of_birth',
        'rating_type', 'rating_value', 'level',
        'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rating_value' => 'decimal:1',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(PlayerAssessment::class, 'player_id');
    }
}
