<?php

namespace App\Models;

use Database\Factories\TennisMatchStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tennis_match_id
 * @property int|null $server
 * @property int|null $first_server
 * @property int $set_a
 * @property int $set_b
 * @property int $game_a
 * @property int $game_b
 * @property bool $tiebreak
 * @property int $tb_a
 * @property int $tb_b
 * @property int|null $tb_first
 * @property array<int, array{a: int, b: int}>|null $sets
 * @property int|null $win
 * @property-read TennisMatch $tennisMatch
 */
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

    /**
     * @return BelongsTo<TennisMatch, $this>
     */
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
