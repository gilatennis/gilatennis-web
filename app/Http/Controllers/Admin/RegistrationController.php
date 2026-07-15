<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ShapesRegistration;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Inertia\Inertia;

class RegistrationController extends Controller
{
    use ShapesRegistration;

    public function index(Tournament $tournament)
    {
        $query = $tournament->registrations()
            ->with(['division', 'team', 'players']);

        if ($status = request('status')) {
            $query->where('payment_status', $status);
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
            ],
        ]);
    }

    public function updatePayment(Tournament $tournament, TournamentRegistration $registration)
    {
        $newStatus = request('status');

        if ($newStatus === 'paid') {
            $registration->markAsPaid(request('reference') ?? $registration->payment_reference);
        } elseif ($newStatus === 'refunded') {
            $registration->update(['payment_status' => 'refunded']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment status updated.']);

        return redirect()->back();
    }
}
