<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

// INFO: have to be named `TennisMatch` because `Match` is a reserved php keyword

/**
 * @property int $id
 * @property int $division_id
 * @property int|null $bracket_slot_id
 * @property int|null $entrant_a_id
 * @property int|null $entrant_b_id
 * @property string|null $side_a_name
 * @property string|null $side_b_name
 * @property int $sets_to_win
 * @property int $games_to_win
 * @property bool $extend_at_tie
 * @property int $tiebreak_to
 * @property MatchStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property-read TennisMatchState|null $state
 * @property-read Collection<int, TennisMatchEvent> $events
 * @property-read Division $division
 * @property-read BracketSlot|null $bracketSlot
 * @property-read TournamentRegistration|null $entrantA
 * @property-read TournamentRegistration|null $entrantB
 */
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

    /**
     * Payload consumed by the admin scoreboard (Inertia prop and JSON responses).
     *
     * @return array<string, mixed>
     */
    public function toScorePayload(): array
    {
        $state = $this->state;

        return [
            'id' => $this->id,
            'side_a_name' => $this->side_a_name,
            'side_b_name' => $this->side_b_name,
            'sets_to_win' => $this->sets_to_win,
            'games_to_win' => $this->games_to_win,
            'extend_at_tie' => $this->extend_at_tie,
            'tiebreak_to' => $this->tiebreak_to,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'format_label' => $this->format_label,
            'server' => $state?->server,
            'first_server' => $state?->first_server,
            'set_a' => $state->set_a ?? 0,
            'set_b' => $state->set_b ?? 0,
            'game_a' => $state->game_a ?? 0,
            'game_b' => $state->game_b ?? 0,
            'tiebreak' => $state->tiebreak ?? false,
            'tb_a' => $state->tb_a ?? 0,
            'tb_b' => $state->tb_b ?? 0,
            'tb_first' => $state?->tb_first,
            'sets' => $state->sets ?? [],
            'win' => $state?->win,
            'event_count' => $this->events()->count(),
        ];
    }

    /**
     * @return HasOne<TennisMatchState, $this>
     */
    public function state(): HasOne
    {
        return $this->hasOne(TennisMatchState::class);
    }

    /**
     * @return HasMany<TennisMatchEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TennisMatchEvent::class)->orderBy('seq');
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<BracketSlot, $this>
     */
    public function bracketSlot(): BelongsTo
    {
        return $this->belongsTo(BracketSlot::class);
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

    protected static function booted(): void
    {
        parent::booted();

        static::created(function (TennisMatch $match) {
            $match->state()->create(TennisMatchState::defaults());
        });
    }
}
