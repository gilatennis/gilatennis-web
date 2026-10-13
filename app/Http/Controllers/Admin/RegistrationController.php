<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentEventSource;
use App\Enums\PlayerAssessmentStatus;
use App\Http\Controllers\Admin\Concerns\ShapesRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegistrationStoreRequest;
use App\Http\Requests\Admin\RegistrationUpdateRequest;
use App\Http\Requests\Admin\UpdatePlayerAssessmentsRequest;
use App\Http\Requests\Admin\UpdateRegistrationPaymentRequest;
use App\Http\Requests\Admin\WithdrawRegistrationRequest;
use App\Models\Division;
use App\Models\PlayerAssessment;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Queries\RegistrationIndexQuery;
use App\Services\Bracket\BracketStructure;
use App\Services\TournamentPaymentService;
use App\Services\TournamentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RegistrationController extends Controller
{
    use ShapesRegistration;

    public function index(Tournament $tournament, Request $request): Response
    {
        $index = new RegistrationIndexQuery($tournament, $request);

        $registrations = $index->paginate()
            ->through(fn (TournamentRegistration $r) => self::shapeRegistration($r, includeDivision: true));

        $tournament->loadMissing('divisions');

        return Inertia::render('Admin/Tournaments/Registrations', [
            'tournament' => $this->tournamentSummary($tournament),
            'registrations' => $registrations,
            'divisions' => $tournament->divisions->map(fn (Division $d) => [
                'id' => $d->id,
                'name' => $d->name,
            ]),
            'stats' => $index->stats(),
            'filters' => $index->filters(),
        ]);
    }

    public function show(Tournament $tournament, Division $division, TournamentRegistration $registration, BracketStructure $structure): Response
    {
        $registration->load(['division', 'team', 'players.assessment']);

        $shaped = self::shapeRegistration($registration, includeDivision: true);

        $side = match ($division->participation_category) {
            'singles' => 'Singles',
            'doubles' => 'Doubles Partner',
            'team' => 'Team Member',
            default => 'Player',
        };

        return Inertia::render('Admin/Tournaments/Registrations/Show', [
            'tournament' => $this->tournamentSummary($tournament),
            'division' => [
                'id' => $division->id,
                'name' => $division->name,
                'slug' => $division->slug,
                'participation_category' => $division->participation_category,
                'category_label' => $division->category_label,
            ],
            'registration' => $shaped,
            'side_label' => $side,
            'back_url' => route('admin.tournaments.divisions.show', [$tournament, $division]),
            'update_url' => route('admin.tournaments.divisions.registrations.assessments.update', [$tournament, $division, $registration]),
            'payment_url' => route('admin.tournaments.registrations.payment', [$tournament, $registration]),
            'withdraw_url' => route('admin.tournaments.registrations.withdraw', [$tournament, $registration]),
            'draw_started' => $structure->stageStarted($division),
            'assessment_statuses' => array_map(
                fn (PlayerAssessmentStatus $s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                    'color' => $s->color(),
                ],
                PlayerAssessmentStatus::cases(),
            ),
        ]);
    }

    public function updateAssessments(
        Tournament $tournament,
        Division $division,
        TournamentRegistration $registration,
        UpdatePlayerAssessmentsRequest $request,
    ): RedirectResponse {
        $userId = $request->user()?->id;
        $assessments = (array) $request->input('assessments', []);

        $validPlayerIds = $registration->players()
            ->whereIn('id', array_column($assessments, 'player_id'))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($assessments, $validPlayerIds, $userId) {
            foreach ($assessments as $row) {
                $playerId = (int) $row['player_id'];

                if (! in_array($playerId, $validPlayerIds, true)) {
                    continue;
                }

                PlayerAssessment::updateOrCreate(
                    ['player_id' => $playerId],
                    [
                        'status' => PlayerAssessmentStatus::from($row['status']),
                        'reason' => $row['reason'] ?? null,
                        'video_url' => $row['video_url'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'assessed_by' => $userId,
                        'assessed_at' => now(),
                    ],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Assessments saved.']);

        return redirect()->back();
    }

    public function updatePayment(
        Tournament $tournament,
        TournamentRegistration $registration,
        UpdateRegistrationPaymentRequest $request,
        TournamentPaymentService $payments,
        TournamentRegistrationService $registrations,
    ): RedirectResponse {
        $validated = $request->validated();

        $newStatus = $validated['status'];
        $reference = $validated['reference'] ?? null;

        $context = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'raw_payload' => ['status' => $newStatus, 'reference' => $reference],
        ];

        if ($newStatus === 'paid') {
            $payments->markPaid(
                $registration,
                $reference ?? $registration->payment_reference,
                PaymentEventSource::Admin,
                $context,
            );
        } else {
            $registrations->refund($registration, $context);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment status updated.']);

        return redirect()->back();
    }

    public function withdraw(
        Tournament $tournament,
        TournamentRegistration $registration,
        WithdrawRegistrationRequest $request,
        TournamentRegistrationService $service,
    ): RedirectResponse {
        $context = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        try {
            $service->withdraw(
                $registration,
                $request->validated('reason'),
                $request->boolean('refund'),
                $context,
            );
        } catch (InvalidArgumentException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors(['registration' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration withdrawn.']);

        return redirect()->back();
    }

    public function create(Tournament $tournament): Response
    {
        return Inertia::render('Admin/Tournaments/Registrations/Create', [
            'tournament' => $this->tournamentSummary($tournament),
            'divisions' => $this->divisionOptions($tournament),
        ]);
    }

    public function store(
        Tournament $tournament,
        RegistrationStoreRequest $request,
        TournamentRegistrationService $service,
    ): RedirectResponse {
        $division = $request->resolveRegistrationDivision()
            ?? abort(422, 'The selected division does not belong to this tournament.');

        $service->create($tournament, $division, [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'team_name' => $request->team_name,
            'players' => $request->input('players', []),
        ], [
            'mark_paid' => $request->input('payment_action') === 'paid',
            'payment_reference' => $request->input('payment_reference'),
            'context' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration created.']);

        return redirect()->route('admin.tournaments.registrations.index', $tournament);
    }

    public function edit(Tournament $tournament, TournamentRegistration $registration): Response
    {
        $registration->load(['division', 'team', 'players']);

        return Inertia::render('Admin/Tournaments/Registrations/Edit', [
            'tournament' => $this->tournamentSummary($tournament),
            'divisions' => $this->divisionOptions($tournament),
            'registration' => [
                'id' => $registration->id,
                'confirmation_code' => $registration->confirmation_code,
                'division_id' => $registration->division_id,
                'name' => $registration->name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'date_of_birth' => $registration->date_of_birth?->toDateString(),
                'payment_status' => $registration->payment_status,
                'payment_reference' => $registration->payment_reference,
                'team_name' => $registration->team?->name,
                'players' => $registration->players->map(fn (TournamentRegistrationPlayer $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'gender' => $p->gender,
                    'date_of_birth' => $p->date_of_birth?->toDateString(),
                    'rating_type' => $p->rating_type,
                    'rating_value' => $p->rating_value,
                    'level' => $p->level,
                ])->values(),
            ],
        ]);
    }

    public function update(
        Tournament $tournament,
        TournamentRegistration $registration,
        RegistrationUpdateRequest $request,
        TournamentRegistrationService $service,
    ): RedirectResponse {
        $service->update($registration, [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'team_name' => $request->team_name,
            'players' => $request->input('players', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration updated.']);

        return redirect()->route('admin.tournaments.registrations.index', $tournament);
    }

    public function destroy(
        Tournament $tournament,
        TournamentRegistration $registration,
        TournamentRegistrationService $service,
    ): RedirectResponse {
        $service->delete($registration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration deleted.']);

        return redirect()->route('admin.tournaments.registrations.index', $tournament);
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function tournamentSummary(Tournament $tournament): array
    {
        return [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'slug' => $tournament->slug,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function divisionOptions(Tournament $tournament): array
    {
        $tournament->load('divisions.playerFieldRequirements');

        return $tournament->divisions->map(fn (Division $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'participation_category' => $d->participation_category,
            'fee' => $d->fee,
            'draw_size' => $d->draw_size,
            'spots_available' => $d->spots_available,
            'min_team_size' => $d->min_team_size,
            'max_team_size' => $d->max_team_size,
            'min_female_players' => $d->min_female_players,
            'min_male_players' => $d->min_male_players,
            'level_requirements' => $d->level_requirements ?? [],
            'gender' => $d->gender,
            'player_field_requirements' => $d->playerFieldRequirements->map(fn ($r) => [
                'field_key' => $r->field_key,
                'required' => (bool) $r->required,
            ])->values(),
        ])->values()->all();
    }
}
