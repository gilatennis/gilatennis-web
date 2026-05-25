<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'name', 'slug',
        'participation_category', 'draw_size', 'spots_left', 'fee',
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

    protected $appends = ['spots_available', 'is_sold_out', 'category_label'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function getSpotsAvailableAttribute(): int
    {
        return max(0, $this->spots_left);
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->spots_left <= 0;
    }

    public function getCategoryLabelAttribute(): string
    {
        return ucfirst($this->participation_category);
    }

    public function getEntryCountAttribute(): int
    {
        return max(0, $this->draw_size - $this->spots_left);
    }

    public function getMaxEntriesAttribute(): int
    {
        return (int) $this->draw_size;
    }

    public function hasRatingConstraints(): bool
    {
        return $this->rating_type !== null && $this->rating_min !== null && $this->rating_max !== null;
    }

    public function scopeOpen($query)
    {
        return $query->where('spots_left', '>', 0)
            ->whereHas('tournament', fn ($q) => $q->where('status', 'open'));
    }
}
