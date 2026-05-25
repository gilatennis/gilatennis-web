<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Tournament extends Model
{
    use HasFactory, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tagline' => $this->tagline ?? '',
            'city' => $this->city ?? '',
            'venue' => $this->venue ?? '',
            'surface' => $this->surface ?? '',
            'dates' => $this->dates ?? '',
            'status' => $this->status ?? '',
        ];
    }

    public function searchableAs(): string
    {
        return 'tournaments';
    }

    protected $guarded = [];

    protected $appends = [
        'spots_available',
        'max_players',
        'player_count',
        'surface_label',
        'is_sold_out',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'fee' => 'float',
        'perks' => 'array',
    ];

    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentPrize::class)->orderBy('sort_order');
    }

    public function scheduleDays(): HasMany
    {
        return $this->hasMany(TournamentScheduleDay::class)->orderBy('sort_order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TournamentRule::class)->orderBy('sort_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class)->orderBy('sort_order');
    }

    public function registrationPlayers(): HasMany
    {
        return $this->hasMany(TournamentRegistrationPlayer::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open')->where('spots_left', '>', 0);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'open');
    }

    public function getSpotsAvailableAttribute(): int
    {
        return max(0, $this->spots_left);
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->spots_left === 0;
    }

    public function getSurfaceLabelAttribute(): string
    {
        return match ($this->surface) {
            'hard' => 'Hard',
            'clay' => 'Clay',
            'grass' => 'Grass',
            'indoor_hard' => 'Indoor',
        };
    }

    public function getMaxPlayersAttribute(): int
    {
        return (int) $this->draw_size;
    }

    public function getPlayerCountAttribute(): int
    {
        return max(0, $this->draw_size - $this->spots_left);
    }
}
