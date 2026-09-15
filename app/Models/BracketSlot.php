<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BracketSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_id',
        'round',
        'position',
        'entrant_a_id',
        'entrant_b_id',
        'winner_entrant_id',
        'prev_slot_a_id',
        'prev_slot_b_id',
        'seed',
        'bye',
        'label',
    ];

    protected $casts = [
        'round' => 'integer',
        'position' => 'integer',
        'seed' => 'integer',
        'bye' => 'boolean',
    ];

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    public function entrantA(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_a_id');
    }

    public function entrantB(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_b_id');
    }

    public function winnerEntrant(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'winner_entrant_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TennisMatch::class);
    }

    public function isComplete(): bool
    {
        return $this->winner_entrant_id !== null;
    }

    /**
     * A slot is ready to play when it has two entrants and no winner yet.
     */
    public function isReady(): bool
    {
        return ! $this->isComplete()
            && $this->entrant_a_id !== null
            && $this->entrant_b_id !== null;
    }
}
