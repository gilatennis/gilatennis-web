<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'name', 'slug',
        'participation_category', 'draw_size', 'fee', 'format',
        'gender', 'rating_type', 'rating_min', 'rating_max',
        'min_age', 'max_age', 'min_team_size', 'max_team_size', 'sort_order',
    ];

    protected $casts = [
        'fee' => 'float',
        'rating_min' => 'decimal:1',
        'rating_max' => 'decimal:1',
        'min_team_size' => 'integer',
        'max_team_size' => 'integer',
    ];

    protected $appends = ['spots_available', 'is_sold_out', 'category_label', 'gender_label'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentPrize::class)->orderBy('sort_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function getSpotsAvailableAttribute(): int
    {
        return max(0, $this->draw_size - $this->paid_registrations_count);
    }

    public function getSpotsLeftAttribute(): int
    {
        return $this->spots_available;
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->spots_available <= 0;
    }

    public function getPaidRegistrationsCountAttribute(): int
    {
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

    public function scopeHasAvailableSpots($query)
    {
        return $query->whereColumn('draw_size', '>', DB::raw('(SELECT COUNT(*) FROM tournament_registrations WHERE tournament_registrations.division_id = divisions.id AND tournament_registrations.payment_status = \'paid\')'));
    }

    public function scopeOpen($query)
    {
        return $query->hasAvailableSpots()
            ->whereHas('tournament', fn ($q) => $q->where('status', 'open'));
    }
}
