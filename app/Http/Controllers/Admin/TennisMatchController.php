<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TennisMatchStoreRequest;
use App\Models\TennisMatch;
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
        return Inertia::render('Admin/TennisMatches/Score', [
            'match' => [
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
                'format_label' => $match->format_label,
            ],
        ]);
    }
}
