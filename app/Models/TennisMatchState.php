<?php

namespace App\Models;

use Database\Factories\TennisMatchStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TennisMatchState extends Model
{
    /** @use HasFactory<TennisMatchStateFactory> */
    use HasFactory;

    protected $fillable = [
        'tennis_match_id',
        'server',
        'first_server',
        'set_a',
        'set_b',
        'game_a',
        'game_b',
        'tiebreak',
        'tb_a',
        'tb_b',
        'tb_first',
        'sets',
        'win',
    ];

    protected $casts = [
        'server' => 'integer',
        'first_server' => 'integer',
        'set_a' => 'integer',
        'set_b' => 'integer',
        'game_a' => 'integer',
        'game_b' => 'integer',
        'tiebreak' => 'boolean',
        'tb_a' => 'integer',
        'tb_b' => 'integer',
        'tb_first' => 'integer',
        'sets' => 'array',
        'win' => 'integer',
    ];

    public function tennisMatch(): BelongsTo
    {
        return $this->belongsTo(TennisMatch::class);
    }

    public static function defaults(): array
    {
        return [
            'server' => 1,
            'first_server' => null,
            'set_a' => 0,
            'set_b' => 0,
            'game_a' => 0,
            'game_b' => 0,
            'tiebreak' => false,
            'tb_a' => 0,
            'tb_b' => 0,
            'tb_first' => null,
            'sets' => [],
            'win' => null,
        ];
    }
}
