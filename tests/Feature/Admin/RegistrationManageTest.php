<?php

use App\Enums\BracketStatus;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\PaymentEvent;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function adminRegPlayer(string $name, string $gender = 'male', string $level = 'Intermediate'): array
{
    return [
        'name' => $name,
        'email' => Str::slug($name).'@example.com',
        'phone' => '0812000',
        'gender' => $gender,
        'level' => $level,
        'date_of_birth' => null,
        'rating_type' => null,
        'rating_value' => null,
    ];
}

function adminRegDivision(Tournament $tournament, array $overrides = []): Division
{
    return Division::factory()->create(array_merge([
        'tournament_id' => $tournament->id,
        'participation_category' => 'team',
        'name' => 'Team Cup',
        'slug' => 'team-cup',
        'draw_size' => 16,
        'fee' => 50000,
        'min_team_size' => 2,
        'max_team_size' => 4,
        'min_female_players' => 0,
        'min_male_players' => 0,
        'level_requirements' => null,
        'gender' => null,
    ], $overrides));
}

describe('Admin Registration CRUD', function () {
    it('renders the create page with divisions', function () {
        $tournament = Tournament::factory()->create();
        adminRegDivision($tournament);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.create', $tournament))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Registrations/Create')
                ->has('divisions', 1)
            );
    });

    it('creates a team registration with members and marks it paid', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.registrations.store', $tournament), [
                'division_id' => $division->id,
                'name' => 'Captain Ace',
                'email' => 'ace@example.com',
                'phone' => '0811000',
                'team_name' => 'Aces',
                'payment_action' => 'paid',
                'payment_reference' => 'BANK-1',
                'players' => [
                    adminRegPlayer('Captain Ace'),
                    adminRegPlayer('Member Two'),
                ],
            ])
            ->assertRedirect(route('admin.tournaments.registrations.index', $tournament));

        $registration = TournamentRegistration::firstWhere('email', 'ace@example.com');

        expect($registration)->not->toBeNull()
            ->and($registration->payment_status)->toBe('paid')
            ->and($registration->team->name)->toBe('Aces');

        expect($registration->players()->count())->toBe(2);
        expect($registration->players()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1]);

        expect(PaymentEvent::where([
            'tournament_registration_id' => $registration->id,
            'source' => 'admin',
            'status_after' => 'paid',
        ])->exists())->toBeTrue();
    });

    it('creates a singles registration as pending by default', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament, [
            'participation_category' => 'singles',
            'name' => 'Singles',
            'slug' => 'singles',
            'min_team_size' => null,
            'max_team_size' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.registrations.store', $tournament), [
                'division_id' => $division->id,
                'name' => 'Solo',
                'email' => 'solo@example.com',
                'phone' => '0811',
                'payment_action' => 'pending',
                'players' => [adminRegPlayer('Solo')],
            ])
            ->assertRedirect();

        $registration = TournamentRegistration::firstWhere('email', 'solo@example.com');

        expect($registration->payment_status)->toBe('pending')
            ->and($registration->team_id)->toBeNull()
            ->and($registration->players()->count())->toBe(1);
    });

    it('rejects a team below the minimum size', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament, ['min_team_size' => 3]);

        $this->actingAs($this->admin)
            ->from(route('admin.tournaments.registrations.create', $tournament))
            ->post(route('admin.tournaments.registrations.store', $tournament), [
                'division_id' => $division->id,
                'name' => 'Cap',
                'email' => 'cap@example.com',
                'phone' => '0811',
                'team_name' => 'Two',
                'payment_action' => 'pending',
                'players' => [
                    adminRegPlayer('Cap'),
                    adminRegPlayer('Two'),
                ],
            ])
            ->assertSessionHasErrors('players');

        expect(TournamentRegistration::count())->toBe(0);
    });

    it('rejects a team that misses the female minimum', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament, ['min_female_players' => 1]);

        $this->actingAs($this->admin)
            ->from(route('admin.tournaments.registrations.create', $tournament))
            ->post(route('admin.tournaments.registrations.store', $tournament), [
                'division_id' => $division->id,
                'name' => 'Cap',
                'email' => 'cap@example.com',
                'phone' => '0811',
                'team_name' => 'Blokes',
                'payment_action' => 'pending',
                'players' => [
                    adminRegPlayer('Cap', 'male'),
                    adminRegPlayer('Two', 'male'),
                ],
            ])
            ->assertSessionHasErrors('players');

        expect(TournamentRegistration::count())->toBe(0);
    });

    it('renders the edit page for a registration', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
        ]);
        TournamentRegistrationPlayer::factory()->create([
            'tournament_registration_id' => $registration->id,
            'tournament_id' => $tournament->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.registrations.edit', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Registrations/Edit')
                ->where('registration.id', $registration->id)
            );
    });

    it('updates a registration roster and contact details', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $p1 = TournamentRegistrationPlayer::factory()->create([
            'tournament_registration_id' => $registration->id,
            'tournament_id' => $tournament->id,
            'name' => 'P1',
        ]);
        $p2 = TournamentRegistrationPlayer::factory()->create([
            'tournament_registration_id' => $registration->id,
            'tournament_id' => $tournament->id,
            'name' => 'P2',
        ]);
        $p3 = TournamentRegistrationPlayer::factory()->create([
            'tournament_registration_id' => $registration->id,
            'tournament_id' => $tournament->id,
            'name' => 'P3',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.registrations.update', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'phone' => '0899',
                'team_name' => 'Renamed',
                'players' => [
                    ['id' => $p1->id, 'name' => 'P1', 'email' => 'p1@example.com', 'phone' => '08', 'gender' => 'male', 'level' => 'Intermediate'],
                    ['name' => 'P4', 'email' => 'p4@example.com', 'phone' => '08', 'gender' => 'male', 'level' => 'Intermediate'],
                ],
            ])
            ->assertRedirect();

        $registration->refresh();

        expect($registration->name)->toBe('New Name')
            ->and($registration->email)->toBe('new@example.com')
            ->and($registration->players()->count())->toBe(2);

        $this->assertModelMissing($p2);
        $this->assertModelMissing($p3);
        $this->assertModelExists($p1);
        expect($p1->fresh()->name)->toBe('P1')
            ->and($registration->players()->where('name', 'P4')->exists())->toBeTrue();
    });

    it('deletes a registration and its team', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);
        $team = Team::factory()->create(['name' => 'Doomed']);
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.registrations.destroy', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]))
            ->assertRedirect(route('admin.tournaments.registrations.index', $tournament));

        $this->assertDatabaseMissing('tournament_registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    });

    it('blocks deleting a registration placed in a live bracket', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);
        $registration = TournamentRegistration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
        ]);

        $bracket = Bracket::factory()->create([
            'division_id' => $division->id,
            'status' => BracketStatus::Active,
        ]);
        BracketSlot::factory()->create([
            'bracket_id' => $bracket->id,
            'entrant_a_id' => $registration->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.registrations.destroy', [
                'tournament' => $tournament,
                'registration' => $registration,
            ]))
            ->assertSessionHasErrors('registration');

        $this->assertDatabaseHas('tournament_registrations', ['id' => $registration->id]);
    });

    it('forbids non-admin users from creating registrations', function () {
        $tournament = Tournament::factory()->create();
        $division = adminRegDivision($tournament);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.tournaments.registrations.store', $tournament), [
                'division_id' => $division->id,
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'phone' => '08',
                'payment_action' => 'pending',
                'players' => [adminRegPlayer('Nope')],
            ])
            ->assertForbidden();
    });
});
