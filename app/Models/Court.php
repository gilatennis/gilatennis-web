<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Court extends Model
{
    use HasFactory, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city ?? '',
            'surface' => $this->surface ?? '',
            'excerpt' => $this->excerpt ?? '',
        ];
    }

    public function searchableAs(): string
    {
        return 'courts';
    }

    protected $fillable = [
        'slug',
        'name',
        'city',
        'surface',
        'content_type',
        'image_alt',
        'rating',
        'review_count',
        'is_featured',
        'duration',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'review_count' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function amenities(): HasMany
    {
        return $this->hasMany(CourtAmenity::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getIsVideoAttribute(): bool
    {
        return $this->content_type === 'video';
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

    public function getTypeLabelAttribute(): string
    {
        return $this->is_video ? 'Video' : 'Read';
    }

    public function getImageLabelAttribute(): ?string
    {
        return $this->image_alt;
    }
}
