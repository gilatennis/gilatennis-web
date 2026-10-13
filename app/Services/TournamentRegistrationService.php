<?php

namespace App\Services;

use App\Enums\BracketStatus;
use App\Enums\PaymentEventSource;
use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Settings;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;
use App\Services\Bracket\BracketStructure;
use App\Services\Bracket\GroupStageService;
use App\Services\Bracket\KnockoutBracketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for creating, editing and removing tournament
 * registrations. Shared by the public self-service flow and the admin panel so
 * both produce identical rows (guest user, team, players, amounts).
 */
class TournamentRegistrationService
{
    public function __construct(
        private TournamentPaymentService $payments,
        private BracketStructure $structure,
        private KnockoutBracketService $knockout,
        private GroupStageService $groups,
    ) {}

    /**
     * Create a registration with its team (for team divisions) and players.
     *
     * @param  array<string, mixed>  $data  captain + players + team_name
     * @param  array{mark_paid?: bool, payment_reference?: ?string, enforce_duplicate?: bool, context?: array<string, mixed>}  $options
     */
    public function create(Tournament $tournament, Division $division, array $data, array $options = []): TournamentRegistration
    {
        $markPaid = (bool) ($options['mark_paid'] ?? false);
        $enforceDuplicate = (bool) ($options['enforce_duplicate'] ?? true);
        $reference = $options['payment_reference'] ?? null;
        $context = $options['context'] ?? [];

        $registration = DB::transaction(function () use ($tournament, $division, $data, $enforceDuplicate) {
            $players = $data['players'];
            $isTeam = $division->participation_category === 'team';

            $user = $this->resolveGuestUser($data);

            $this->guardAgainstDuplicate($tournament, $division, $user, $enforceDuplicate);

            $team = $isTeam && ! empty($data['team_name'])
                ? Team::create(['name' => $data['team_name']])
                : null;

            $feeCents = (int) ($division->fee * 100);
            $transactionFeeCents = Settings::getTransactionFeeCents();

            $registration = TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'division_id' => $division->id,
                'team_id' => $team?->id,
                'confirmation_code' => TournamentRegistration::generateConfirmationCode(),
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'amount_cents' => $feeCents + $transactionFeeCents,
                'transaction_fee_cents' => $transactionFeeCents,
                'currency' => 'IDR',
                'payment_status' => 'pending',
            ]);

            $this->createPlayers($registration, $team, $players);

            return $registration;
        });

        if ($markPaid) {
            $this->payments->markPaid(
                $registration,
                $reference ?? $registration->payment_reference,
                PaymentEventSource::Admin,
                $context,
            );
        }

        return $registration->fresh(['players', 'team']);
    }

    /**
     * Update an existing registration's contact details, team name and roster.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TournamentRegistration $registration, array $data): void
    {
        $this->ensureMutable($registration);

        DB::transaction(function () use ($registration, $data) {
            $registration->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
            ]);

            if ($registration->team && ! empty($data['team_name'])) {
                $registration->team->update(['name' => $data['team_name']]);
            }

            $this->syncPlayers($registration, $data['players']);
        });
    }

    /**
     * Reconcile the roster of an existing registration: update rows that carry
     * an id, create rows that do not, delete those no longer present, and
     * re-index `sort_order` to match the submitted order.
     *
     * @param  array<int, array<string, mixed>>  $players
     */
    public function syncPlayers(TournamentRegistration $registration, array $players): void
    {
        DB::transaction(function () use ($registration, $players) {
            $existing = $registration->players()->get()->keyBy('id');
            $incomingIds = array_filter(array_map(fn ($p) => $p['id'] ?? null, $players));

            $registration->players()
                ->whereNotIn('id', array_filter($incomingIds))
                ->delete();

            foreach ($players as $index => $player) {
                $attributes = [
                    'name' => $player['name'],
                    'email' => $player['email'] ?? null,
                    'gender' => $player['gender'] ?? null,
                    'phone' => $player['phone'] ?? null,
                    'date_of_birth' => $player['date_of_birth'] ?? null,
                    'rating_type' => $player['rating_type'] ?? null,
                    'rating_value' => $player['rating_value'] ?? null,
                    'level' => $player['level'] ?? null,
                    'sort_order' => $index,
                ];

                $id = $player['id'] ?? null;

                if ($id && $existing->has($id)) {
                    $existing->get($id)->update($attributes);

                    continue;
                }

                $registration->players()->create(array_merge($attributes, [
                    'tournament_id' => $registration->tournament_id,
                    'team_id' => $registration->team_id,
                ]));
            }
        });
    }

    /**
     * Delete a registration and its team (team_id is nullOnDelete, so removing
     * the registration alone would orphan the team).
     */
    public function delete(TournamentRegistration $registration): void
    {
        $this->ensureMutable($registration);

        DB::transaction(function () use ($registration) {
            $team = $registration->team;

            $registration->delete();

            if ($team && $team->registrations()->count() === 0) {
                $team->delete();
            }
        });
    }

    /**
     * Withdraw a registration from a draw that has not started: unassign it
     * from the division's bracket and/or group stage, which frees its capacity
     * spot so a replacement can register, and optionally refund the entry fee.
     * Once play has started the draw may no longer be reshaped, so the team
     * must instead have its result recorded as a loss.
     *
     * @param  array<string, mixed>  $context
     */
    public function withdraw(TournamentRegistration $registration, ?string $reason = null, bool $refund = false, array $context = []): void
    {
        $division = $registration->division()->first();

        if ($division === null) {
            throw ValidationException::withMessages([
                'registration' => 'This registration has no division to withdraw from.',
            ]);
        }

        if ($this->structure->stageStarted($division)) {
            throw ValidationException::withMessages([
                'registration' => 'The draw has already started. Record the team\'s loss instead of withdrawing.',
            ]);
        }

        DB::transaction(function () use ($registration, $division, $reason, $refund, $context) {
            $this->knockout->removeEntrant($division, $registration);
            $this->groups->removeEntrant($division, $registration);

            $registration->update([
                'withdrawn_at' => now(),
                'withdrawal_reason' => $reason,
            ]);

            if ($refund) {
                $this->refund($registration, $context + [
                    'raw_payload' => ['status' => 'refunded', 'reason' => $reason],
                ]);
            }
        });
    }

    /**
     * Mark a registration refunded and append the admin audit event. Shared by
     * the withdraw flow (optional refund) and the manual payment override.
     *
     * @param  array<string, mixed>  $context
     */
    public function refund(TournamentRegistration $registration, array $context = []): void
    {
        $statusBefore = $registration->payment_status;

        $registration->markAsRefunded();

        $this->payments->record($registration, PaymentEventSource::Admin, $context + [
            'status_before' => $statusBefore,
            'status_after' => 'refunded',
        ]);
    }

    /**
     * A registration placed in a live (non-draft) bracket must be unassigned
     * before it can be edited or deleted, otherwise the draw is silently
     * broken (entrant columns null out on delete).
     */
    private function ensureMutable(TournamentRegistration $registration): void
    {
        $liveBracketIds = $registration->division()
            ->first()
            ?->brackets()
            ->where('status', '!=', BracketStatus::Draft)
            ->pluck('id') ?? collect();

        if ($liveBracketIds->isEmpty()) {
            return;
        }

        $inSlot = BracketSlot::whereIn('bracket_id', $liveBracketIds)
            ->where(fn ($q) => $q->where('entrant_a_id', $registration->id)
                ->orWhere('entrant_b_id', $registration->id))
            ->exists();

        $inGroup = BracketGroupEntrant::where('tournament_registration_id', $registration->id)
            ->whereHas('group.bracket', fn ($q) => $q->whereIn('id', $liveBracketIds))
            ->exists();

        if ($inSlot || $inGroup) {
            throw ValidationException::withMessages([
                'registration' => 'This registration is placed in a live bracket. Unassign it from the draw before editing or deleting.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $players
     */
    private function createPlayers(TournamentRegistration $registration, ?Team $team, array $players): void
    {
        foreach ($players as $index => $player) {
            TournamentRegistrationPlayer::create([
                'tournament_registration_id' => $registration->id,
                'tournament_id' => $registration->tournament_id,
                'team_id' => $team?->id,
                'name' => $player['name'],
                'email' => $player['email'] ?? null,
                'gender' => $player['gender'] ?? null,
                'phone' => $player['phone'] ?? null,
                'date_of_birth' => $player['date_of_birth'] ?? null,
                'rating_type' => $player['rating_type'] ?? null,
                'rating_value' => $player['rating_value'] ?? null,
                'level' => $player['level'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveGuestUser(array $data): User
    {
        return User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'is_guest' => true,
                'password' => Hash::make('guest-'.$data['email']),
            ],
        );
    }

    private function guardAgainstDuplicate(Tournament $tournament, Division $division, User $user, bool $enforce): void
    {
        if (! $enforce) {
            return;
        }

        $existing = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('division_id', $division->id)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'duplicate_registration' => "This email already has a paid entry for this division (confirmation #{$existing->confirmation_code}). Use a different email or allow duplicates.",
            ]);
        }
    }
}
