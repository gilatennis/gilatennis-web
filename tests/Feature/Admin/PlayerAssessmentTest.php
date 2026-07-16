<?php

use App\Enums\PlayerAssessmentStatus;
use App\Models\Division;
use App\Models\PlayerAssessment;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);

    $this->tournament = Tournament::factory()->create();
    $this->division = Division::factory()->create([
        'tournament_id' => $this->tournament->id,
        'participation_category' => 'singles',
    ]);
    $this->registration = TournamentRegistration::factory()->create([
        'tournament_id' => $this->tournament->id,
        'division_id' => $this->division->id,
    ]);
    $this->players = TournamentRegistrationPlayer::factory()
        ->count(2)
        ->create([
            'tournament_registration_id' => $this->registration->id,
            'tournament_id' => $this->tournament->id,
        ]);
});

describe('Admin Player Assessments', function () {
    it('renders the registration show page with players and assessment statuses', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.registrations.show', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Registrations/Show')
                ->has('registration.players', 2)
                ->has('assessment_statuses', 3)
                ->where('side_label', 'Singles')
            );
    });

    it('upserts assessments for all players in a single request', function () {
        $payload = [
            'assessments' => $this->players->map(fn ($p) => [
                'player_id' => $p->id,
                'status' => PlayerAssessmentStatus::Cleared->value,
                'reason' => null,
                'video_url' => 'https://www.youtube.com/watch?v=abc123',
                'notes' => 'Strong player, cleared.',
            ])->all(),
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertRedirect();

        expect(PlayerAssessment::count())->toBe(2);

        $this->players->each(function (TournamentRegistrationPlayer $player) {
            $assessment = $player->assessment()->first();
            expect($assessment)->not->toBeNull();
            expect($assessment->status)->toBe(PlayerAssessmentStatus::Cleared);
            expect($assessment->video_url)->toBe('https://www.youtube.com/watch?v=abc123');
            expect($assessment->assessed_by)->toBe($this->admin->id);
            expect($assessment->assessed_at)->not->toBeNull();
        });
    });

    it('requires reason when status is does_not_meet_level', function () {
        $payload = [
            'assessments' => [
                [
                    'player_id' => $this->players[0]->id,
                    'status' => PlayerAssessmentStatus::DoesNotMeetLevel->value,
                    'reason' => '',
                    'video_url' => null,
                    'notes' => null,
                ],
                [
                    'player_id' => $this->players[1]->id,
                    'status' => PlayerAssessmentStatus::Unreviewed->value,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertSessionHasErrors('assessments.0.reason');
    });

    it('rejects reason shorter than 10 characters', function () {
        $payload = [
            'assessments' => [
                [
                    'player_id' => $this->players[0]->id,
                    'status' => PlayerAssessmentStatus::DoesNotMeetLevel->value,
                    'reason' => 'too short',
                    'video_url' => null,
                    'notes' => null,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertSessionHasErrors('assessments.0.reason');
    });

    it('rejects invalid video url', function () {
        $payload = [
            'assessments' => [
                [
                    'player_id' => $this->players[0]->id,
                    'status' => PlayerAssessmentStatus::Unreviewed->value,
                    'video_url' => 'not-a-url',
                    'notes' => null,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertSessionHasErrors('assessments.0.video_url');
    });

    it('rejects player_id that does not belong to this registration', function () {
        $other = TournamentRegistrationPlayer::factory()->create([
            'tournament_registration_id' => $this->registration->id,
            'tournament_id' => $this->tournament->id,
        ]);
        $stranger = TournamentRegistrationPlayer::factory()->create();

        $payload = [
            'assessments' => [
                [
                    'player_id' => $stranger->id,
                    'status' => PlayerAssessmentStatus::Unreviewed->value,
                ],
                [
                    'player_id' => $other->id,
                    'status' => PlayerAssessmentStatus::Unreviewed->value,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertSessionHasErrors('assessments.0.player_id');
    });

    it('rejects notes longer than 500 characters', function () {
        $payload = [
            'assessments' => [
                [
                    'player_id' => $this->players[0]->id,
                    'status' => PlayerAssessmentStatus::Unreviewed->value,
                    'notes' => str_repeat('a', 501),
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertSessionHasErrors('assessments.0.notes');
    });

    it('returns 404 when registration does not belong to the given division', function () {
        $otherDivision = Division::factory()->create([
            'tournament_id' => $this->tournament->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.divisions.registrations.show', [
                'tournament' => $this->tournament,
                'division' => $otherDivision,
                'registration' => $this->registration,
            ]))
            ->assertStatus(404);
    });

    it('forbids non-admin users from updating assessments', function () {
        $user = User::factory()->create();

        $payload = [
            'assessments' => [
                [
                    'player_id' => $this->players[0]->id,
                    'status' => PlayerAssessmentStatus::Cleared->value,
                ],
            ],
        ];

        $this->actingAs($user)
            ->patch(route('admin.tournaments.divisions.registrations.assessments.update', [
                'tournament' => $this->tournament,
                'division' => $this->division,
                'registration' => $this->registration,
            ]), $payload)
            ->assertStatus(403);
    });
});
