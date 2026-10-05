<?php

namespace App\Models;

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $division_id
 * @property BracketType $type
 * @property BracketStatus $status
 * @property int $size
 * @property-read Division $division
 * @property-read Collection<int, BracketSlot> $slots
 * @property-read Collection<int, BracketGroup> $groups
 */
class Bracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_id',
        'type',
        'status',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
        'type' => BracketType::class,
        'status' => BracketStatus::class,
    ];

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<BracketSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class)
            ->orderBy('round')
            ->orderBy('position');
    }

    /**
     * @return HasMany<BracketGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(BracketGroup::class)
            ->orderBy('position');
    }

    public function isGroupStage(): bool
    {
        return $this->type === BracketType::GroupStage;
    }

    public function finalSlot(): ?BracketSlot
    {
        return $this->slots()->reorder()->orderByDesc('round')->orderBy('position')->first();
    }
}
