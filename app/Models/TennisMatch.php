<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

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
 * @property bool $extend_tiebreak
 * @property bool $sudden_death
 * @property int $tiebreak_to
 * @property MatchStatus $status
 * @property string|null $share_token
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
        'extend_tiebreak',
        'sudden_death',
        'tiebreak_to',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $appends = ['format_label'];

    /**
     * The share token is a bearer credential for the public umpire scoreboard;
     * it must never leak into serialized payloads.
     *
     * @var list<string>
     */
    protected $hidden = ['share_token'];

    protected $casts = [
        'extend_at_tie' => 'boolean',
        'extend_tiebreak' => 'boolean',
        'sudden_death' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'status' => MatchStatus::class,
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), MatchStatus::Pending->value);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), MatchStatus::Running->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), MatchStatus::Completed->value);
    }

    public function getFormatLabelAttribute(): string
    {
        $bestOf = ($this->sets_to_win * 2) - 1;

        return "Best of {$bestOf} set(s), {$this->games_to_win} games, TB to {$this->tiebreak_to}";
    }

    /**
     * Public, login-free link an umpire can use to score this match.
     */
    public function publicScoreUrl(): ?string
    {
        if ($this->share_token === null) {
            return null;
        }

        return route('umpire.score', ['match' => $this->share_token]);
    }

    /**
     * The score/format fields shared by the admin and public payloads.
     *
     * Requires the `state` relation to be eager loaded: this is called per match
     * when listing, so a lazy load here would be an N+1 (and a hard error under
     * `Model::preventLazyLoading` outside production). Callers that forget to
     * load it get a `LogicException` rather than a silently wrong payload.
     *
     * @return array<string, mixed>
     */
    public function scoreFields(): array
    {
        if (! $this->relationLoaded('state')) {
            throw new LogicException('TennisMatch::scoreFields() requires the state relation to be eager loaded.');
        }

        $state = $this->state;

        return [
            'id' => $this->id,
            'side_a_name' => $this->side_a_name,
            'side_b_name' => $this->side_b_name,
            'status' => $this->status,
            'format_label' => $this->format_label,
            'sets_to_win' => $this->sets_to_win,
            'games_to_win' => $this->games_to_win,
            'extend_at_tie' => (bool) $this->extend_at_tie,
            'extend_tiebreak' => (bool) $this->extend_tiebreak,
            'sudden_death' => (bool) $this->sudden_death,
            'tiebreak_to' => $this->tiebreak_to,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'server' => $state?->server,
            'set_a' => $state->set_a ?? 0,
            'set_b' => $state->set_b ?? 0,
            'game_a' => $state->game_a ?? 0,
            'game_b' => $state->game_b ?? 0,
            'tiebreak' => (bool) ($state->tiebreak ?? false),
            'tb_a' => $state->tb_a ?? 0,
            'tb_b' => $state->tb_b ?? 0,
            'sets' => $state->sets ?? [],
            'win' => $state?->win,
        ];
    }

    /**
     * Payload consumed by the admin scoreboard (Inertia prop and JSON responses).
     * Adds the admin-only fields on top of the shared score fields.
     *
     * @return array<string, mixed>
     */
    public function toScorePayload(): array
    {
        $state = $this->state;

        return [
            ...$this->scoreFields(),
            'first_server' => $state?->first_server,
            'tb_first' => $state?->tb_first,
            'created_at' => $this->created_at?->toIso8601String(),
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

        static::creating(function (TennisMatch $match) {
            $match->share_token ??= Str::random(48);
        });

        static::created(function (TennisMatch $match) {
            $match->state()->create(TennisMatchState::defaults());
        });
    }
}
