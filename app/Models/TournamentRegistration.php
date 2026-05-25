<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'user_id', 'division_id', 'team_id',
        'confirmation_code',
        'name', 'email', 'phone', 'date_of_birth',
        'tshirt_size', 'dietary',
        'payment_status', 'payment_reference', 'payment_url',
        'amount_cents', 'currency',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'date_of_birth' => 'date',
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentRegistrationPlayer::class, 'tournament_registration_id')->orderBy('sort_order');
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
