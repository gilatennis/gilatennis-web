<?php

namespace App\Queries;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the admin registration list: the filtered, eager-loaded, paginated
 * query, the per-status aggregate counts, and the active filter set echoed back
 * to the frontend.
 */
class RegistrationIndexQuery
{
    /**
     * A registration is considered stuck when it has not settled this long
     * after being created (e.g. a successful payment we never processed).
     */
    private const STUCK_MINUTES = 30;

    public function __construct(
        private Tournament $tournament,
        private Request $request,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TournamentRegistration>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $row = $this->tournament->registrations()
            ->toBase()
            ->selectRaw(
                "COUNT(*) as total,
                 COALESCE(SUM(payment_status = 'paid'), 0) as paid,
                 COALESCE(SUM(payment_status = 'pending'), 0) as pending,
                 COALESCE(SUM(payment_status = 'failed'), 0) as failed,
                 COALESCE(SUM(payment_status = 'refunded'), 0) as refunded,
                 COALESCE(SUM(withdrawn_at IS NOT NULL), 0) as withdrawn,
                 COALESCE(SUM(payment_status IN ('pending', 'processing') AND created_at <= ?), 0) as stuck",
                [now()->subMinutes(self::STUCK_MINUTES)],
            )
            ->first();

        return [
            'total' => (int) $row->total,
            'paid' => (int) $row->paid,
            'pending' => (int) $row->pending,
            'failed' => (int) $row->failed,
            'refunded' => (int) $row->refunded,
            'withdrawn' => (int) $row->withdrawn,
            'stuck' => (int) $row->stuck,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'status' => $this->request->input('status'),
            'division_id' => $this->request->input('division_id'),
            'search' => $this->request->input('search'),
            'stuck' => $this->request->boolean('stuck'),
        ];
    }

    /**
     * @return Builder<TournamentRegistration>
     */
    private function baseQuery(): Builder
    {
        $query = TournamentRegistration::query()
            ->where('tournament_id', $this->tournament->id)
            ->with(['division', 'team', 'players.assessment']);

        if ($status = $this->request->input('status')) {
            if ($status === 'withdrawn') {
                $query->withdrawn();
            } else {
                $query->where('payment_status', $status);
            }
        }

        if ($this->request->boolean('stuck')) {
            $query->whereIn('payment_status', ['pending', 'processing'])
                ->where('created_at', '<=', now()->subMinutes(self::STUCK_MINUTES));
        }

        if ($divisionId = $this->request->input('division_id')) {
            $query->where('division_id', $divisionId);
        }

        if ($search = $this->request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('confirmation_code', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
