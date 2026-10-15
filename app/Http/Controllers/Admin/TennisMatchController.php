<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TennisMatchStoreRequest;
use App\Http\Requests\Admin\TennisMatchUpdateRequest;
use App\Models\TennisMatch;
use App\Services\TennisMatchScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TennisMatchController extends Controller
{
    public function __construct(private TennisMatchScoringService $scoring) {}

    public function index(): Response
    {
        $matches = TennisMatch::orderByDesc('created_at')
            ->paginate(10)
            ->through(fn (TennisMatch $m) => [
                'id' => $m->id,
                'side_a_name' => $m->side_a_name,
                'side_b_name' => $m->side_b_name,
                'format_label' => $m->format_label,
                'status' => $m->status,
                'started_at' => $m->started_at?->toIso8601String(),
            ]);

        // TODO: this needs to be cached
        $stats = [
            'total' => TennisMatch::count(),
            'pending' => TennisMatch::pending()->count(),
            'running' => TennisMatch::running()->count(),
            'completed' => TennisMatch::completed()->count(),
        ];

        return Inertia::render('Admin/TennisMatches/Index', [
            'matches' => $matches,
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TennisMatches/Create');
    }

    public function store(TennisMatchStoreRequest $request): RedirectResponse
    {
        $match = TennisMatch::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Match created.']);

        return redirect()->route('admin.tennis-matches.show', $match);
    }

    public function show(TennisMatch $match): Response
    {
        $match->loadMissing('state');

        $bracketUrl = null;

        if ($match->bracket_slot_id !== null) {
            $match->loadMissing('division');

            if ($match->division !== null) {
                $bracketUrl = route('admin.tournaments.divisions.show', [
                    'tournament' => $match->division->tournament_id,
                    'division' => $match->division_id,
                    'tab' => 'brackets',
                ]);
            }
        }

        return Inertia::render('Admin/TennisMatches/Score', [
            'match' => $match->toScorePayload(),
            'bracketUrl' => $bracketUrl,
            'shareUrl' => $match->publicScoreUrl(),
        ]);
    }

    /**
     * Update match configuration (rubber sides, format) and manual status.
     * Score state is server-owned and written only via the scoring endpoints.
     */
    public function update(TennisMatchUpdateRequest $request, TennisMatch $match): RedirectResponse
    {
        DB::transaction(function () use ($request, $match) {
            $match->fill($request->safe()->only([
                'side_a_name',
                'side_b_name',
                'sets_to_win',
                'games_to_win',
                'extend_at_tie',
                'extend_tiebreak',
                'sudden_death',
                'tiebreak_to',
            ]));

            if ($match->isDirty()) {
                $match->save();
            }

            if ($request->has('status')) {
                $this->scoring->setStatus(
                    $match,
                    MatchStatus::from($request->validated('status')),
                    $request->date('started_at'),
                    $request->date('ended_at'),
                );
            }
        });

        $match->refresh();

        return back();
    }
}
