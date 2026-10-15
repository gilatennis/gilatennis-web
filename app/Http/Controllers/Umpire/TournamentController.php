<?php

namespace App\Http\Controllers\Umpire;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\UmpireMatchListService;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function __construct(private UmpireMatchListService $matches) {}

    public function index(): Response
    {
        $search = request('search');

        $tournaments = $this->visibleTournaments()
            ->when($search, fn (Builder $query) => $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            }))
            ->orderByDesc('start')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'city' => $tournament->city,
                'dates' => $tournament->dates,
                'status' => $tournament->status,
                'status_label' => $tournament->status_label,
            ]);

        return Inertia::render('Umpire/Tournaments', [
            'tournaments' => $tournaments,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Tournament $tournament): Response
    {
        abort_if($this->isHidden($tournament), 404);

        return Inertia::render('Umpire/Tournament', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            ...$this->matches->present($tournament),
        ]);
    }

    /**
     * Tournaments an umpire may score in: every non-draft meet that has not been
     * canceled and finished.
     *
     * @return Builder<Tournament>
     */
    private function visibleTournaments(): Builder
    {
        return Tournament::query()
            ->where('status', '!=', TournamentStatus::Draft)
            ->where(function (Builder $query) {
                $query->where('status', '!=', TournamentStatus::Canceled)
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('end')->whereDate('end', '>=', today()));
            });
    }

    private function isHidden(Tournament $tournament): bool
    {
        return $tournament->status === TournamentStatus::Draft
            || $tournament->isCanceledAndOver();
    }
}
