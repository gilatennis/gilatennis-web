<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// INFO: have to be named `TennisMatch` because `Match` is a reserved php keyword
class TennisMatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['format_label'];

    protected $casts = [
        'extend_at_tie' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function getFormatLabelAttribute(): string
    {
        $bestOf = ($this->sets_to_win * 2) - 1;

        return "Best of {$bestOf}, {$this->games_to_win} games, TB to {$this->tiebreak_to}";
    }
}
