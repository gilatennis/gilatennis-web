<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'user_id', 'confirmation_code',
        'name', 'email', 'phone', 'date_of_birth',
        'rating_type', 'rating_value',
        'event_type', 'find_partner', 'partner_name', 'partner_email',
        'tshirt_size', 'dietary',
        'payment_status', 'payment_reference', 'payment_url',
        'amount_cents', 'currency',
        'paid_at',
    ];

    protected $casts = [
        'find_partner' => 'boolean',
        'amount_cents' => 'integer',
        'date_of_birth' => 'date',
        'rating_value' => 'decimal:1',
        'paid_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'confirmation_code';
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsPaid(string $reference): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_reference' => $reference,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['payment_status' => 'failed']);
    }

    public static function generateConfirmationCode(): string
    {
        return 'GO-'.date('Y').'-'.Str::upper(Str::random(4));
    }

    public function getFeeAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}
