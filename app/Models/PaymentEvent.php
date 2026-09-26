<?php

namespace App\Models;

use App\Enums\PaymentEventSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'tournament_registration_id',
        'source',
        'raw_payload',
        'signature_valid',
        'signature_scheme',
        'result_code',
        'status_before',
        'status_after',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'source' => PaymentEventSource::class,
        'raw_payload' => 'array',
        'signature_valid' => 'boolean',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }
}
