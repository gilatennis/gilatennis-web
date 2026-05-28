<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = ['contact_wa_number'];

    public static function getWaNumber(): ?string
    {
        return static::first()?->contact_wa_number;
    }
}
