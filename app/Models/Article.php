<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasFactory, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt ?? '',
            'kicker' => $this->kicker ?? '',
            'author' => $this->author ?? '',
            'type' => $this->type ?? '',
            'is_published' => $this->is_published,
        ];
    }

    public function searchableAs(): string
    {
        return 'articles';
    }

    protected $fillable = [
        'slug',
        'type',
        'kicker',
        'title',
        'excerpt',
        'image_alt',
        'author',
        'reading_minutes',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'reading_minutes' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(ArticleProduct::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->whereNotNull('published_at');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeReviews($query)
    {
        return $query->ofType('review');
    }

    public function scopeTips($query)
    {
        return $query->ofType('tip');
    }

    public function getTypeBadgeVariantAttribute(): string
    {
        return match ($this->type) {
            'review' => 'coral',
            'tip' => 'default',
            'strategy' => 'secondary',
            'fitness' => 'ghost',
        };
    }

    public function getImageLabelAttribute(): ?string
    {
        return $this->image_alt;
    }

    public function getReadTimeAttribute(): string
    {
        return ($this->reading_minutes ?? 5).' min read';
    }

    public function getPublishedAtAttribute(): ?string
    {
        $publishedAt = $this->getRawOriginal('published_at');

        if ($publishedAt === null) {
            return null;
        }

        $dt = Carbon::parse($publishedAt);
        $diff = (int) now()->diffInDays($dt);

        if ($diff === 0) {
            return 'Today';
        }
        if ($diff === 1) {
            return 'Yesterday';
        }
        if ($diff < 7) {
            return $diff.'d ago';
        }

        return $dt->format('M j');
    }
}
