<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentEventSource;
use App\Enums\PlayerAssessmentStatus;
use App\Http\Controllers\Admin\Concerns\ShapesRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlayerAssessmentsRequest;
use App\Models\Division;
use App\Models\PlayerAssessment;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\TournamentPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    use ShapesRegistration;

    /**
     * A registration is considered stuck when it has not settled this long
     * after being created (e.g. a successful payment we never processed).
     */
    private const STUCK_MINUTES = 30;

    public function index(Tournament $tournament)
    {
        $query = $tournament->registrations()
            ->with(['division', 'team', 'players.assessment']);

        if ($status = request('status')) {
            $query->where('payment_status', $status);
        }

        if (request()->boolean('stuck')) {
            $query->whereIn('payment_status', ['pending', 'processing'])
                ->where('created_at', '<=', now()->subMinutes(self::STUCK_MINUTES));
        }

        if ($divisionId = request('division_id')) {
            $query->where('division_id', $divisionId);
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('confirmation_code', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderByDesc('created_at')->paginate(10)
            ->through(fn (TournamentRegistration $r) => self::shapeRegistration($r, includeDivision: true));

        $stats = [
            'total' => $tournament->registrations()->count(),
            'paid' => $tournament->registrations()->where('payment_status', 'paid')->count(),
            'pending' => $tournament->registrations()->where('payment_status', 'pending')->count(),
            'failed' => $tournament->registrations()->where('payment_status', 'failed')->count(),
            'refunded' => $tournament->registrations()->where('payment_status', 'refunded')->count(),
            'stuck' => $tournament->registrations()
                ->whereIn('payment_status', ['pending', 'processing'])
                ->where('created_at', '<=', now()->subMinutes(self::STUCK_MINUTES))
                ->count(),
        ];

        return Inertia::render('Admin/Tournaments/Registrations', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            'registrations' => $registrations,
            'divisions' => $tournament->divisions->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ]),
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'division_id' => $divisionId,
                'search' => $search,
                'stuck' => request()->boolean('stuck'),
            ],
        ]);
    }

    public function show(Tournament $tournament, Division $division, TournamentRegistration $registration): Response
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
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
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
        $userId = $request->user('web')?->id;
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

    public function updatePayment(Tournament $tournament, TournamentRegistration $registration, TournamentPaymentService $payments): RedirectResponse
    {
        $validated = request()->validate([
            'status' => ['required', 'in:paid,refunded'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $newStatus = $validated['status'];
        $reference = $validated['reference'] ?? null;

        $context = [
            'raw_payload' => ['status' => $newStatus, 'reference' => $reference],
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($newStatus === 'paid') {
            $payments->markPaid(
                $registration,
                $reference ?? $registration->payment_reference,
                PaymentEventSource::Admin,
                $context,
            );
        } else {
            $statusBefore = $registration->payment_status;

            $registration->update(['payment_status' => 'refunded']);

            $payments->record($registration, PaymentEventSource::Admin, $context + [
                'status_before' => $statusBefore,
                'status_after' => 'refunded',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment status updated.']);

        return redirect()->back();
    }
}
