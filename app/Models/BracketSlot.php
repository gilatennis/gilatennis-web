<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $bracket_id
 * @property int|null $bracket_group_id
 * @property int $bracket_scope
 * @property int $round
 * @property int $position
 * @property int $leg
 * @property int|null $entrant_a_id
 * @property int|null $entrant_b_id
 * @property int|null $winner_entrant_id
 * @property string|null $advance_reason
 * @property int|null $prev_slot_a_id
 * @property int|null $prev_slot_b_id
 * @property int|null $seed
 * @property bool $bye
 * @property string|null $label
 * @property-read Bracket $bracket
 * @property-read BracketGroup|null $group
 * @property-read TournamentRegistration|null $entrantA
 * @property-read TournamentRegistration|null $entrantB
 * @property-read TournamentRegistration|null $winnerEntrant
 * @property-read Collection<int, TennisMatch> $matches
 */
class BracketSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_id',
        'bracket_group_id',
        'bracket_scope',
        'round',
        'position',
        'leg',
        'entrant_a_id',
        'entrant_b_id',
        'winner_entrant_id',
        'advance_reason',
        'prev_slot_a_id',
        'prev_slot_b_id',
        'seed',
        'bye',
        'label',
    ];

    protected $casts = [
        'round' => 'integer',
        'position' => 'integer',
        'leg' => 'integer',
        'bracket_scope' => 'integer',
        'seed' => 'integer',
        'bye' => 'boolean',
    ];

    /**
     * @return BelongsTo<Bracket, $this>
     */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * @return BelongsTo<BracketGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(BracketGroup::class, 'bracket_group_id');
    }

    /**
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function entrantA(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_a_id');
    }

    /**
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function entrantB(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'entrant_b_id');
    }

    /**
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function winnerEntrant(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'winner_entrant_id');
    }

    /**
     * @return HasMany<TennisMatch, $this>
     */
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
