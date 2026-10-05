<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ShapesBracket;
use App\Http\Controllers\Admin\Concerns\ShapesRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DivisionValidateRequest;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class DivisionController extends Controller
{
    use ShapesBracket, ShapesRegistration;

    public function validateDivision(DivisionValidateRequest $request): JsonResponse
    {
        return response()->json(['valid' => true]);
    }

    public function show(Tournament $tournament, Division $division)
    {
        $division->load(['prizes', 'playerFieldRequirements', 'bracket', 'groupStage']);

        $query = $division->registrations()
            ->with(['division', 'team', 'players.assessment']);

        $status = request('status');
        $search = request('search');
        $tab = in_array(request('tab'), ['registrants', 'prizes', 'matches', 'schedule'], true)
            ? request('tab')
            : 'registrants';

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

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'paid' => (int) ($statusCounts['paid'] ?? 0),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'failed' => (int) ($statusCounts['failed'] ?? 0),
            'refunded' => (int) ($statusCounts['refunded'] ?? 0),
            'filled' => $division->entry_count,
            'draw' => $division->draw_size,
        ];

        $bracketEntrants = $division->orderedPaidRegistrations()
            ->with('team')
            ->get()
            ->map(fn (TournamentRegistration $r) => [
                'id' => $r->id,
                'name' => $r->display_name,
            ])
            ->values();

        return Inertia::render('Admin/Tournaments/Division', [
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
            ],
            'registrations' => $registrations,
            'stats' => $stats,
            'bracket' => self::shapeBracket($division->bracket),
            'groupStage' => self::shapeBracket($division->groupStage),
            'bracketEntrants' => $bracketEntrants,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'tab' => $tab,
            ],
        ]);
    }
}
