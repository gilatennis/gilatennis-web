<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentScheduleItem extends Model
{
    protected $fillable = ['schedule_day_id', 'label', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scheduleDay(): BelongsTo
    {
        return $this->belongsTo(TournamentScheduleDay::class, 'schedule_day_id');
    }
}
