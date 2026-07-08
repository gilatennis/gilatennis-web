<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Tournament extends Model
{
    use HasFactory, Searchable;

    protected static function booted(): void
    {
        static::deleting(function (Tournament $tournament) {
            if (! $tournament->cover_image) {
                return;
            }

            try {
                Storage::disk('public')->delete($tournament->cover_image);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

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

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'dates',
        'start',
        'end',
        'city',
        'venue',
        'surface',
        'ball_type',
        'venue_type',
        'cover_image',
        'perks',
        'registration_closes',
        'is_featured',
        'status',
    ];

    protected $appends = [
        'max_players',
        'player_count',
        'spots_available',
        'surface_label',
        'status_label',
        'venue_type_label',
        'is_sold_out',
        'cover_image_url',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
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
        return $query->where('status', 'open')
            ->whereHas('divisions', fn ($q) => $q->hasAvailableSpots());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'open');
    }

    public function getSpotsAvailableAttribute(): int
    {
        if ($this->relationLoaded('divisions')) {
            return max(0, $this->max_players - $this->player_count);
        }

        $draw = (int) $this->divisions()->sum('draw_size');

        return max(0, $draw);
    }

    public function getIsSoldOutAttribute(): bool
    {
        if ($this->relationLoaded('divisions')) {
            return $this->divisions->every(fn ($d) => $d->paid_registrations_count >= $d->draw_size);
        }

        return ! $this->divisions()->hasAvailableSpots()->exists();
    }

    public function getMaxPlayersAttribute(): int
    {
        return (int) $this->divisions()->sum('draw_size');
    }

    public function getPlayerCountAttribute(): int
    {
        return $this->registrations()->where('payment_status', 'paid')->count();
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

    public function getVenueTypeLabelAttribute(): ?string
    {
        return match ($this->venue_type) {
            'indoor' => 'Indoor',
            'outdoor' => 'Outdoor',
            'both' => 'Indoor & Outdoor',
            default => null,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'open' => 'Open',
            'closed' => 'Closed',
            'completed' => 'Completed',
        };
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image);
    }
}
