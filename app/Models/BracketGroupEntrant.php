<?php

namespace App\Models;

use Database\Factories\BracketGroupEntrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of a paid entrant in a round-robin group. This is the source of
 * truth for who is in a group; the group's fixtures are derived from it.
 */
class BracketGroupEntrant extends Model
{
    /** @use HasFactory<BracketGroupEntrantFactory> */
    use HasFactory;

    protected $fillable = [
        'bracket_group_id',
        'tournament_registration_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(BracketGroup::class, 'bracket_group_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }
}
