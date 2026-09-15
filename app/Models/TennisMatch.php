<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// INFO: have to be named `TennisMatch` because `Match` is a reserved php keyword
class TennisMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_id',
        'bracket_slot_id',
        'entrant_a_id',
        'entrant_b_id',
        'side_a_name',
        'side_b_name',
        'sets_to_win',
        'games_to_win',
        'extend_at_tie',
        'tiebreak_to',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $appends = ['format_label'];

    protected $casts = [
        'extend_at_tie' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'status' => MatchStatus::class,
    ];

    public function scopePending($query)
    {
        return $query->where('status', MatchStatus::Pending->value);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', MatchStatus::Running->value);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MatchStatus::Completed->value);
    }

    public function getFormatLabelAttribute(): string
    {
        $bestOf = ($this->sets_to_win * 2) - 1;

        return "Best of {$bestOf} set(s), {$this->games_to_win} games, TB to {$this->tiebreak_to}";
    }

    public function state(): HasOne
    {
        return $this->hasOne(TennisMatchState::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function bracketSlot(): BelongsTo
    {
        return $this->belongsTo(BracketSlot::class);
    }

    public function entrantA(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_a_id');
    }

    public function entrantB(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_b_id');
    }

    protected static function booted(): void
    {
        parent::booted();

        static::created(function (TennisMatch $match) {
            $match->state()->create(TennisMatchState::defaults());
        });
    }
}
