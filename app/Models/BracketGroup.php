<?php

namespace App\Models;

use Database\Factories\BracketGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BracketGroup extends Model
{
    /** @use HasFactory<BracketGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'bracket_id',
        'name',
        'position',
        'advance_count',
        'double_round_robin',
    ];

    protected $casts = [
        'position' => 'integer',
        'advance_count' => 'integer',
        'double_round_robin' => 'boolean',
    ];

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * Every tie played inside this group, ordered by matchday then fixture.
     */
    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class)
            ->orderBy('round')
            ->orderBy('position');
    }

    /**
     * The entrants currently placed in this group, derived from its ties and
     * returned in first-seen order.
     *
     * @return Collection<int, int>
     */
    public function entrantIds(): Collection
    {
        return $this->slots
            ->flatMap(fn (BracketSlot $slot) => [$slot->entrant_a_id, $slot->entrant_b_id])
            ->filter(fn (?int $id) => $id !== null)
            ->unique()
            ->values();
    }
}
