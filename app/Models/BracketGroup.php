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
     * The membership rows for this group, ordered by assignment position.
     */
    public function entrants(): HasMany
    {
        return $this->hasMany(BracketGroupEntrant::class)->orderBy('position');
    }

    /**
     * The entrant ids currently placed in this group, in assignment order.
     *
     * @return Collection<int, int>
     */
    public function entrantIds(): Collection
    {
        return $this->entrants()->pluck('tournament_registration_id');
    }
}
