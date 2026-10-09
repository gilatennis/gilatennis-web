<?php

namespace App\Services;

use App\Http\Controllers\Admin\Concerns\ShapesBracket;
use App\Http\Controllers\Admin\Concerns\ShapesRegistration;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\Request;

/**
 * Builds the Inertia payload for the division detail page so the controller
 * stays thin and the query + shaping concerns live together.
 */
class DivisionPagePresenter
{
    use ShapesBracket, ShapesRegistration;

    public function __construct(private BracketService $brackets) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Tournament $tournament, Division $division, Request $request): array
    {
        $division->load(['prizes', 'playerFieldRequirements', 'bracket', 'groupStage']);

        $status = $request->query('status');
        $search = $request->query('search');
        $tab = in_array($request->query('tab'), ['registrants', 'prizes', 'brackets', 'schedule'], true)
            ? $request->query('tab')
            : 'registrants';

        $query = $division->registrations()
            ->with(['division', 'team', 'players.assessment']);

        if ($status) {
            $query->where('payment_status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('confirmation_code', 'like', "%{$search}%")
                    ->orWhereHas('team', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $registrations = $query->orderByDesc('created_at')->paginate(10)
            ->through(fn (TournamentRegistration $r) => self::shapeRegistration($r, includeDivision: true));

        $statusCounts = $division->registrations()
            ->selectRaw('payment_status, count(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status');

        $stageEntrants = $division->orderedPaidRegistrations()
            ->with('team')
            ->get()
            ->map(fn (TournamentRegistration $r) => [
                'id' => $r->id,
                'name' => $r->display_name,
            ])
            ->values();

        return [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            'division' => $this->shapeDivision($division),
            'registrations' => $registrations,
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'paid' => (int) ($statusCounts['paid'] ?? 0),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'failed' => (int) ($statusCounts['failed'] ?? 0),
                'refunded' => (int) ($statusCounts['refunded'] ?? 0),
                'filled' => $division->entry_count,
                'draw' => $division->draw_size,
            ],
            'bracket' => $this->shapeBracket($division->bracket, $this->brackets),
            'groupStage' => $this->shapeBracket($division->groupStage, $this->brackets),
            'stageEntrants' => $stageEntrants,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'tab' => $tab,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeDivision(Division $division): array
    {
        return [
            'id' => $division->id,
            'name' => $division->name,
            'slug' => $division->slug,
            'participation_category' => $division->participation_category,
            'category_label' => $division->category_label,
            'draw_size' => $division->draw_size,
            'spots_available' => $division->spots_available,
            'is_sold_out' => $division->is_sold_out,
            'fee' => $division->fee,
            'format' => $division->format,
            'gender' => $division->gender,
            'gender_label' => $division->gender_label,
            'rating_type' => $division->rating_type,
            'rating_min' => $division->rating_min,
            'rating_max' => $division->rating_max,
            'min_age' => $division->min_age,
            'max_age' => $division->max_age,
            'min_team_size' => $division->min_team_size,
            'max_team_size' => $division->max_team_size,
            'player_field_requirements' => $division->playerFieldRequirements->map(fn ($r) => [
                'field_key' => $r->field_key,
                'required' => (bool) $r->required,
            ])->values()->all(),
            'prizes' => $division->prizes->map(fn ($p) => [
                'id' => $p->id,
                'place' => $p->place,
                'value' => $p->value,
                'sort_order' => $p->sort_order,
            ]),
        ];
    }
}
