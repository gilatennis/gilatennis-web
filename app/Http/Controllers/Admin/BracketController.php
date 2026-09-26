<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BracketGenerateRequest;
use App\Http\Requests\Admin\BracketSeedRequest;
use App\Http\Requests\Admin\BracketSlotStartRequest;
use App\Http\Requests\Admin\BracketSlotUpdateRequest;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class BracketController extends Controller
{
    public function __construct(private BracketService $brackets) {}

    public function store(BracketGenerateRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        try {
            $this->brackets->generate($division, $request->validated('seeding_mode'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bracket' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bracket generated.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function destroy(Tournament $tournament, Division $division): RedirectResponse
    {
        if ($division->bracket !== null) {
            $this->brackets->delete($division->bracket);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bracket removed.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function seed(BracketSeedRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        $bracket = $this->bracketOrFail($division);

        try {
            $this->brackets->seed($bracket, $request->validated('entrant_ids'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['entrant_ids' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Seeding saved.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function startSlot(
        BracketSlotStartRequest $request,
        Tournament $tournament,
        Division $division,
        int $slot,
    ): RedirectResponse {
        $bracketSlot = $this->slotOrFail($division, $slot);

        $overrides = array_filter(
            $request->validated(),
            fn ($value) => $value !== null,
        );

        try {
            $match = $this->brackets->startSlot($bracketSlot, $overrides);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        if ($division->participation_category !== 'team') {
            return redirect()->route('admin.tennis-matches.show', $match);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team rubber added.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function updateSlot(
        BracketSlotUpdateRequest $request,
        Tournament $tournament,
        Division $division,
        int $slot,
    ): RedirectResponse {
        $bracketSlot = $this->slotOrFail($division, $slot);
        $entrantId = $request->validated('winner_entrant_id');

        $winner = $entrantId === null
            ? null
            : TournamentRegistration::where('division_id', $division->id)->findOrFail($entrantId);

        $this->brackets->setWinner($bracketSlot, $winner, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Slot updated.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function undoSlot(
        Tournament $tournament,
        Division $division,
        int $slot,
    ): RedirectResponse {
        $bracketSlot = $this->slotOrFail($division, $slot);

        if ($bracketSlot->winner_entrant_id === null) {
            return back()->withErrors(['slot' => 'Nothing to undo.']);
        }

        if ($bracketSlot->bye) {
            return back()->withErrors(['slot' => 'Byes advance automatically.']);
        }

        if ($this->brackets->laterRoundsHaveProgress($bracketSlot)) {
            return back()->withErrors(['slot' => 'Undo the later rounds first.']);
        }

        $this->brackets->setWinner($bracketSlot, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Advance undone.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    private function bracketOrFail(Division $division): Bracket
    {
        return $division->bracket ?? abort(404);
    }

    private function slotOrFail(Division $division, int $slot): BracketSlot
    {
        return $this->bracketOrFail($division)->slots()->findOrFail($slot);
    }

    private function redirectToDivisionBracket(Tournament $tournament, Division $division): RedirectResponse
    {
        return redirect()->route('admin.tournaments.divisions.show', [
            'tournament' => $tournament,
            'division' => $division,
            'tab' => 'matches',
        ]);
    }
}
