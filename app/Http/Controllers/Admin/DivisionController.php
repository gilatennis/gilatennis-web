<?php

namespace App\Http\Controllers\Admin;

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
    use ShapesRegistration;

    public function validateDivision(DivisionValidateRequest $request): JsonResponse
    {
        return response()->json(['valid' => true]);
    }

    public function show(Tournament $tournament, Division $division)
    {
        abort_if($division->tournament_id !== $tournament->id, 404);

        $division->load('prizes');

        $query = $division->registrations()
            ->with(['team', 'players']);

        if ($status = request('status')) {
            $query->where('payment_status', $status);
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('confirmation_code', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderByDesc('created_at')->paginate(10)
            ->through(fn (TournamentRegistration $r) => self::shapeRegistration($r));

        $stats = [
            'total' => $division->registrations()->count(),
            'paid' => $division->registrations()->where('payment_status', 'paid')->count(),
            'pending' => $division->registrations()->where('payment_status', 'pending')->count(),
            'failed' => $division->registrations()->where('payment_status', 'failed')->count(),
            'refunded' => $division->registrations()->where('payment_status', 'refunded')->count(),
            'filled' => $division->entry_count,
            'draw' => $division->draw_size,
        ];

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
                'prizes' => $division->prizes->map(fn ($p) => [
                    'id' => $p->id,
                    'place' => $p->place,
                    'value' => $p->value,
                    'sort_order' => $p->sort_order,
                ]),
            ],
            'registrations' => $registrations,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }
}
