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
 * @property bool $manual_assignment
 * @property bool $draw_locked
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
        'manual_assignment',
        'draw_locked',
    ];

    protected $casts = [
        'size' => 'integer',
        'manual_assignment' => 'boolean',
        'draw_locked' => 'boolean',
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

    /**
     * A manual-assignment draw is still being arranged: entrants may be moved
     * between first-round slots and byes are not yet resolved.
     */
    public function isEditing(): bool
    {
        return $this->manual_assignment
            && $this->status === BracketStatus::Draft
            && ! $this->draw_locked;
    }

    public function finalSlot(): ?BracketSlot
    {
        return $this->slots()->reorder()->orderByDesc('round')->orderBy('position')->first();
    }
}
