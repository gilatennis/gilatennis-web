<?php

namespace App\Models;

use App\Enums\BracketType;
use App\Enums\TennisLevel;
use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'name', 'slug',
        'participation_category', 'draw_size', 'fee', 'format',
        'gender', 'rating_type', 'rating_min', 'rating_max',
        'min_age', 'max_age', 'min_team_size', 'max_team_size',
        'min_female_players', 'min_male_players',
        'level_requirements', 'sort_order',
    ];

    protected $casts = [
        'fee' => 'float',
        'rating_min' => 'decimal:1',
        'rating_max' => 'decimal:1',
        'min_team_size' => 'integer',
        'max_team_size' => 'integer',
        'min_female_players' => 'integer',
        'min_male_players' => 'integer',
        'level_requirements' => 'array',
    ];

    protected $appends = ['spots_available', 'is_sold_out', 'category_label', 'gender_label'];

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return HasMany<TournamentPrize, $this>
     */
    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentPrize::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<TournamentRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    /**
     * @return HasMany<Bracket, $this>
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(Bracket::class);
    }

    /**
     * @return HasOne<Bracket, $this>
     */
    public function bracket(): HasOne
    {
        return $this->hasOne(Bracket::class)
            ->where('type', BracketType::SingleElimination);
    }

    /**
     * @return HasOne<Bracket, $this>
     */
    public function groupStage(): HasOne
    {
        return $this->hasOne(Bracket::class)
            ->where('type', BracketType::GroupStage);
    }

    /**
     * @return HasMany<TournamentRegistration, $this>
     */
    public function paidRegistrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class)
            ->where('payment_status', 'paid');
    }

    /**
     * @return HasMany<TournamentRegistration, $this>
     */
    public function orderedPaidRegistrations(): HasMany
    {
        return $this->paidRegistrations()
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<DivisionPlayerFieldRequirement, $this>
     */
    public function playerFieldRequirements(): HasMany
    {
        return $this->hasMany(DivisionPlayerFieldRequirement::class);
    }

    /**
     * @return array<string, bool>
     */
    public function playerFieldConfig(): array
    {
        $rows = $this->relationLoaded('playerFieldRequirements')
            ? $this->playerFieldRequirements
            : $this->playerFieldRequirements()->get();

        $byKey = $rows->keyBy('field_key');

        $config = [];

        foreach (DivisionPlayerFieldRequirement::FIELDS as $key => $meta) {
            $config[$key] = $key === 'name'
                ? true
                : (bool) ($byKey[$key]->required ?? $meta['default']);
        }

        return $config;
    }

    public function isPlayerFieldRequired(string $field): bool
    {
        return $this->playerFieldConfig()[$field] ?? true;
    }

    public function getSpotsAvailableAttribute(): int
    {
        return max(0, $this->draw_size - $this->paid_registrations_count);
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->spots_available <= 0;
    }

    public function getPaidRegistrationsCountAttribute(): int
    {
        if (array_key_exists('paid_registrations_count', $this->attributes)) {
            return (int) $this->attributes['paid_registrations_count'];
        }

        return $this->registrations()->where('payment_status', 'paid')->count();
    }

    public function getCategoryLabelAttribute(): string
    {
        return ucfirst($this->participation_category);
    }

    public function getGenderLabelAttribute(): ?string
    {
        return match ($this->gender) {
            'mens' => 'Men',
            'womens' => 'Women',
            'mixed' => 'Mixed',
            'coed' => 'Coed',
            default => null,
        };
    }

    public function getEntryCountAttribute(): int
    {
        return $this->paid_registrations_count;
    }

    public function hasRatingConstraints(): bool
    {
        return $this->rating_type !== null && $this->rating_min !== null && $this->rating_max !== null;
    }

    public function hasLevelRequirements(): bool
    {
        $reqs = $this->level_requirements ?? [];

        return collect($reqs)->filter(fn ($v) => (int) $v > 0)->isNotEmpty();
    }

    public function getLevelRequirement(TennisLevel $level): int
    {
        return (int) ($this->level_requirements[$level->value] ?? 0);
    }

    public function scopeHasAvailableSpots($query)
    {
        return $query->whereColumn('draw_size', '>', DB::raw('(SELECT COUNT(*) FROM tournament_registrations WHERE tournament_registrations.division_id = divisions.id AND tournament_registrations.payment_status = \'paid\')'));
    }

    public function scopeOpen($query)
    {
        return $query->hasAvailableSpots()
            ->whereHas('tournament', fn ($q) => $q->where('status', TournamentStatus::Open));
    }
}
