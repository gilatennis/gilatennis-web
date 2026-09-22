<?php

namespace App\Models;

use App\Enums\TennisMatchEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TennisMatchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tennis_match_id',
        'seq',
        'type',
        'side',
        'shot',
        'created_by',
    ];

    protected $casts = [
        'seq' => 'integer',
        'type' => TennisMatchEventType::class,
        'side' => 'integer',
        'created_by' => 'integer',
    ];

    public function tennisMatch(): BelongsTo
    {
        return $this->belongsTo(TennisMatch::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
