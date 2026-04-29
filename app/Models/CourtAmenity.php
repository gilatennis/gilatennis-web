<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtAmenity extends Model
{
    protected $fillable = ['name'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
