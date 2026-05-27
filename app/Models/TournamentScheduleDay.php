<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentScheduleDay extends Model
{
    protected $fillable = ['tournament_id', 'day_label', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TournamentScheduleItem::class, 'schedule_day_id')->orderBy('sort_order');
    }
}
