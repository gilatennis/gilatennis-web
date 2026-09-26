<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bracket extends Model
{
    use HasFactory;

    public const TYPE_SINGLE_ELIMINATION = 'single_elimination';

    public const TYPE_GROUP_STAGE = 'group_stage';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'division_id',
        'type',
        'status',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class)
            ->orderBy('round')
            ->orderBy('position');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(BracketGroup::class)
            ->orderBy('position');
    }

    public function isGroupStage(): bool
    {
        return $this->type === self::TYPE_GROUP_STAGE;
    }

    public function finalSlot(): ?BracketSlot
    {
        return $this->slots()->reorder()->orderByDesc('round')->orderBy('position')->first();
    }
}
