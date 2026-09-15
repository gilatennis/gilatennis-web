<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TennisMatchStoreRequest;
use App\Http\Requests\Admin\TennisMatchUpdateRequest;
use App\Models\TennisMatch;
use App\Models\TennisMatchState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TennisMatchController extends Controller
{
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

    public function store(TennisMatchStoreRequest $request)
    {
        $match = TennisMatch::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Match created.']);

        return redirect()->route('admin.tennis-matches.show', $match);
    }

    public function show(TennisMatch $match): Response
    {
        $match->loadMissing('state');

        return Inertia::render('Admin/TennisMatches/Score', [
            'match' => $this->matchPayload($match),
        ]);
    }

    public function update(TennisMatchUpdateRequest $request, TennisMatch $match)
    {
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

                if ($next === MatchStatus::Running && $match->started_at === null) {
                    $match->started_at = $request->validated('started_at') ?? now();
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

            if ($request->has('state')) {
                TennisMatchState::updateOrCreate(
                    ['tennis_match_id' => $match->id],
                    $request->validated('state'),
                );
            }
        });

        $match->refresh();
        $match->load('state');

        if (! $request->hasAny(['status', 'state'])) {
            return back();
        }

        return Inertia::render('Admin/TennisMatches/Score', [
            'match' => $this->matchPayload($match),
        ]);
    }

    private function matchPayload(TennisMatch $match): array
    {
        $state = $match->state;

        return [
            'id' => $match->id,
            'side_a_name' => $match->side_a_name,
            'side_b_name' => $match->side_b_name,
            'sets_to_win' => $match->sets_to_win,
            'games_to_win' => $match->games_to_win,
            'extend_at_tie' => $match->extend_at_tie,
            'tiebreak_to' => $match->tiebreak_to,
            'status' => $match->status,
            'started_at' => $match->started_at?->toIso8601String(),
            'ended_at' => $match->ended_at?->toIso8601String(),
            'created_at' => $match->created_at?->toIso8601String(),
            'updated_at' => $match->updated_at?->toIso8601String(),
            'format_label' => $match->format_label,
            'server' => $state?->server,
            'first_server' => $state?->first_server,
            'set_a' => $state?->set_a ?? 0,
            'set_b' => $state?->set_b ?? 0,
            'game_a' => $state?->game_a ?? 0,
            'game_b' => $state?->game_b ?? 0,
            'tiebreak' => $state?->tiebreak ?? false,
            'tb_a' => $state?->tb_a ?? 0,
            'tb_b' => $state?->tb_b ?? 0,
            'tb_first' => $state?->tb_first,
            'sets' => $state?->sets ?? [],
            'win' => $state?->win,
        ];
    }
}
