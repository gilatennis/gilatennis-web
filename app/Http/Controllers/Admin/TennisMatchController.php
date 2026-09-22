<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TennisMatchStoreRequest;
use App\Http\Requests\Admin\TennisMatchUpdateRequest;
use App\Models\TennisMatch;
use App\Services\BracketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TennisMatchController extends Controller
{
    public function __construct(private BracketService $brackets) {}

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

        return Inertia::render('Admin/TennisMatches/Score', [
            'match' => $match->toScorePayload(),
        ]);
    }

    /**
     * Update match configuration (rubber sides, format) and manual status.
     * Score state is server-owned and written only via the scoring endpoints.
     */
    public function update(TennisMatchUpdateRequest $request, TennisMatch $match): RedirectResponse
    {
        $previousStatus = $match->status;

        DB::transaction(function () use ($request, $match) {
            $match->fill($request->safe()->only([
                'side_a_name',
                'side_b_name',
                'sets_to_win',
                'games_to_win',
                'extend_at_tie',
                'tiebreak_to',
            ]));

            if ($match->isDirty()) {
                $match->save();
            }

            if ($request->has('status')) {
                $current = $match->status;
                $next = MatchStatus::from($request->validated('status'));

                if ($next !== $current && ! $current->canTransitionTo($next)) {
                    throw ValidationException::withMessages([
                        'status' => "Cannot transition from {$current->value} to {$next->value}.",
                    ]);
                }

                if ($next === MatchStatus::Running) {
                    if ($match->started_at === null) {
                        $match->started_at = $request->validated('started_at') ?? now();
                    }

                    $match->ended_at = null;
                }

                if ($next === MatchStatus::Completed && $match->ended_at === null) {
                    $match->ended_at = $request->validated('ended_at') ?? now();
                }

                if ($next === MatchStatus::Pending) {
                    $match->started_at = null;
                    $match->ended_at = null;
                }

                $match->status = $next;
                $match->save();
            }
        });

        $match->refresh();

        if ($previousStatus === MatchStatus::Completed && $match->status !== MatchStatus::Completed) {
            $this->clearBracketWinner($match);
        }

        return back();
    }

    private function clearBracketWinner(TennisMatch $match): void
    {
        if ($match->bracket_slot_id === null) {
            return;
        }

        $slot = $match->bracketSlot()->first();

        if ($slot !== null && $slot->winner_entrant_id !== null) {
            $this->brackets->setWinner($slot, null);
        }
    }
}
