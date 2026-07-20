<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = ['contact_wa_number', 'transaction_fee'];

    protected $casts = [
        'transaction_fee' => 'integer',
    ];

    public static function getWaNumber(): ?string
    {
        return static::first()?->contact_wa_number;
    }

    public static function getTransactionFee(): int
    {
        return (int) (static::first()?->transaction_fee ?? 0);
    }

    public static function getTransactionFeeCents(): int
    {
        return static::getTransactionFee() * 100;
    }
}
