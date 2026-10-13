<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int|null $user_id
 * @property int $division_id
 * @property int|null $team_id
 * @property string $confirmation_code
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $date_of_birth
 * @property string $payment_status
 * @property string|null $payment_reference
 * @property string|null $payment_url
 * @property int $amount_cents
 * @property int|null $transaction_fee_cents
 * @property string|null $currency
 * @property Carbon|null $paid_at
 * @property Carbon|null $withdrawn_at
 * @property string|null $withdrawal_reason
 * @property-read Tournament $tournament
 * @property-read User|null $user
 * @property-read Division $division
 * @property-read Team|null $team
 * @property-read Collection<int, TournamentRegistrationPlayer> $players
 * @property-read Collection<int, PaymentEvent> $paymentEvents
 * @property-read string $display_name
 * @property-read float $fee
 */
class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'user_id', 'division_id', 'team_id',
        'confirmation_code',
        'name', 'email', 'phone', 'date_of_birth',
        'payment_status', 'payment_reference', 'payment_url',
        'amount_cents', 'transaction_fee_cents', 'currency',
        'paid_at', 'withdrawn_at', 'withdrawal_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'transaction_fee_cents' => 'integer',
            'date_of_birth' => 'date',
            'paid_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'confirmation_code';
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<TournamentRegistrationPlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(TournamentRegistrationPlayer::class, 'tournament_registration_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class, 'tournament_registration_id')->latest('id');
    }

    /**
     * The name to show in brackets and standings: the team name for team
     * entrants, otherwise the individual registrant's name.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->team->name ?? $this->name);
    }

    public function markAsPaid(?string $reference = null): void
    {
        $attributes = [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ];

        if ($reference !== null && $reference !== '') {
            $attributes['payment_reference'] = $reference;
        }

        $this->update($attributes);
    }

    public function markAsFailed(): void
    {
        $this->update(['payment_status' => 'failed']);
    }

    public function markAsRefunded(): void
    {
        $this->update(['payment_status' => 'refunded']);
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * @param  Builder<TournamentRegistration>  $query
     */
    public function scopeWithdrawn(Builder $query): void
    {
        $query->whereNotNull('withdrawn_at');
    }

    public static function generateConfirmationCode(): string
    {
        $year = now()->year;

        do {
            $code = 'GO-'.$year.'-'.Str::upper(Str::random(4));
        } while (self::where('confirmation_code', $code)->exists());

        return $code;
    }

    protected function fee(): Attribute
    {
        return Attribute::get(fn (): float => $this->amount_cents / 100);
    }
}
