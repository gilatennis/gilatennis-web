<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BracketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BracketGenerateRequest;
use App\Http\Requests\Admin\BracketGroupEntrantRequest;
use App\Http\Requests\Admin\BracketGroupGenerateRequest;
use App\Http\Requests\Admin\BracketGroupSettingsRequest;
use App\Http\Requests\Admin\BracketGroupWinnerRequest;
use App\Http\Requests\Admin\BracketPromoteRequest;
use App\Http\Requests\Admin\BracketRubberStoreRequest;
use App\Http\Requests\Admin\BracketSeedRequest;
use App\Http\Requests\Admin\BracketSlotStartRequest;
use App\Http\Requests\Admin\BracketSlotUpdateRequest;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

class BracketController extends Controller
{
    public function __construct(private BracketService $brackets) {}

    public function store(BracketGenerateRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->brackets->generate($division, $request->validated('seeding_mode')),
            'bracket',
            'Bracket generated.',
            $tournament,
            $division,
        );
    }

    /**
     * Create (or regenerate) the round-robin group stage for a division. This
     * lives alongside the knockout bracket rather than replacing it.
     */
    public function storeGroups(BracketGroupGenerateRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->brackets->generateGroups(
                $division,
                $request->integer('group_count'),
                $request->validated('advance_counts'),
                (bool) $request->boolean('double_round_robin'),
                $request->validated('seeding_mode'),
            ),
            'bracket',
            'Group stage generated.',
            $tournament,
            $division,
        );
    }

    /**
     * Add a paid entrant to a group, rebuilding its round-robin fixtures.
     */
    public function addGroupEntrant(
        BracketGroupEntrantRequest $request,
        Tournament $tournament,
        Division $division,
        BracketGroup $group,
    ): RedirectResponse {
        $entrant = TournamentRegistration::where('division_id', $division->id)
            ->findOrFail($request->integer('entrant_id'));

        return $this->attempt(
            fn () => $this->brackets->addEntrant($group, $entrant),
            'entrant_id',
            'Entrant added to group.',
            $tournament,
            $division,
        );
    }

    /**
     * Move an entrant from one group to another, rebuilding both fixtures.
     */
    public function moveGroupEntrant(
        BracketGroupEntrantRequest $request,
        Tournament $tournament,
        Division $division,
        BracketGroup $group,
    ): RedirectResponse {
        if ($request->validated('to_group_id') === null) {
            return back()->withErrors(['to_group_id' => 'Choose the destination group.']);
        }

        $to = $division->groups()->findOrFail($request->integer('to_group_id'));
        $entrant = TournamentRegistration::where('division_id', $division->id)
            ->findOrFail($request->integer('entrant_id'));

        return $this->attempt(
            fn () => $this->brackets->moveEntrant($group, $to, $entrant),
            'entrant_id',
            'Entrant moved.',
            $tournament,
            $division,
        );
    }

    /**
     * Toggle a group between single and double round-robin, rebuilding fixtures.
     */
    public function updateGroupSettings(
        BracketGroupSettingsRequest $request,
        Tournament $tournament,
        Division $division,
        BracketGroup $group,
    ): RedirectResponse {
        return $this->attempt(
            fn () => $this->brackets->setGroupDoubleRoundRobin(
                $group,
                $request->boolean('double_round_robin'),
            ),
            'group',
            'Group updated.',
            $tournament,
            $division,
        );
    }

    /**
     * Record the entrants picked to advance from a group, in seed order.
     */
    public function setGroupWinners(
        BracketGroupWinnerRequest $request,
        Tournament $tournament,
        Division $division,
        BracketGroup $group,
    ): RedirectResponse {
        return $this->attempt(
            fn () => $this->brackets->setGroupWinners($group, $request->validated('winner_ids')),
            'winner_ids',
            'Advancing winners saved.',
            $tournament,
            $division,
        );
    }

    /**
     * Seed the division's knockout bracket from the picked group winners. A
     * non-power-of-two field is allowed and surfaces as a bye warning.
     */
    public function promote(
        BracketPromoteRequest $request,
        Tournament $tournament,
        Division $division,
    ): RedirectResponse {
        $bracket = null;

        if ($failure = $this->failureOrNull(function () use ($request, $division, &$bracket) {
            $bracket = $this->brackets->promoteToKnockout(
                $division,
                $request->validated('winner_ids'),
                $request->boolean('seed_pairs_apart', true),
            );
        }, 'bracket')) {
            return $failure;
        }

        $byes = $bracket->slots()->where('round', 1)->where('bye', true)->count();

        $clashes = $request->boolean('seed_pairs_apart', true)
            ? $this->brackets->firstRoundGroupMateClashes($bracket, $division)
            : 0;

        $toast = match (true) {
            $byes > 0 => ['type' => 'warning', 'message' => "Winners promoted. {$byes} entrant(s) enter the knockout with a bye."],
            $clashes > 0 => ['type' => 'warning', 'message' => 'Winners promoted. Group-mates meet in the first round of the knockout.'],
            default => ['type' => 'success', 'message' => 'Winners promoted to the knockout.'],
        };

        Inertia::flash('toast', $toast);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    /**
     * Delete one of the division's brackets. Defaults to the knockout bracket;
     * pass `?type=group_stage` to remove the round-robin stage instead.
     */
    public function destroy(Request $request, Tournament $tournament, Division $division): RedirectResponse
    {
        $type = BracketType::tryFrom((string) $request->query('type', BracketType::SingleElimination->value))
            ?? abort(404);

        $bracket = Bracket::where('division_id', $division->id)
            ->where('type', $type)
            ->first();

        if ($bracket !== null) {
            $this->brackets->delete($bracket);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bracket removed.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function seed(BracketSeedRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        $bracket = $division->bracket ?? abort(404);

        return $this->attempt(
            fn () => $this->brackets->seed($bracket, $request->validated('entrant_ids')),
            'entrant_ids',
            'Seeding saved.',
            $tournament,
            $division,
        );
    }

    /**
     * Start the single match that decides a singles/doubles tie, then send the
     * admin straight to its scoreboard. Team divisions play individual rubbers
     * instead and must use {@see self::addRubber()}.
     */
    public function startMatch(
        BracketSlotStartRequest $request,
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
    ): RedirectResponse {
        if ($division->participation_category === 'team') {
            return back()->withErrors(['slot' => 'Team ties are played as individual rubbers. Add a rubber instead.']);
        }

        $match = null;

        if ($failure = $this->failureOrNull(function () use ($slot, $request, &$match) {
            $match = $this->brackets->startSlot($slot, $this->slotOverrides($request));
        }, 'slot')) {
            return $failure;
        }

        return redirect()->route('admin.tennis-matches.show', $match);
    }

    /**
     * Append one rubber (an individual match) to a team tie. A tie may carry
     * several rubbers, so this keeps the admin on the bracket to add more.
     */
    public function addRubber(
        BracketRubberStoreRequest $request,
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
    ): RedirectResponse {
        if ($division->participation_category !== 'team') {
            return back()->withErrors(['slot' => 'Rubbers are only used for team ties.']);
        }

        return $this->attempt(
            fn () => $this->brackets->startSlot($slot, $this->slotOverrides($request)),
            'slot',
            'Team rubber added.',
            $tournament,
            $division,
        );
    }

    public function updateSlot(
        BracketSlotUpdateRequest $request,
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
    ): RedirectResponse {
        $entrantId = $request->validated('winner_entrant_id');

        $winner = $entrantId === null
            ? null
            : TournamentRegistration::where('division_id', $division->id)->findOrFail($entrantId);

        $this->brackets->setWinner($slot, $winner, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Slot updated.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    public function undoSlot(
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
    ): RedirectResponse {
        if ($slot->winner_entrant_id === null) {
            return back()->withErrors(['slot' => 'Nothing to undo.']);
        }

        if ($slot->bye) {
            return back()->withErrors(['slot' => 'Byes advance automatically.']);
        }

        if ($this->brackets->laterRoundsHaveProgress($slot)) {
            return back()->withErrors(['slot' => 'Undo the later rounds first.']);
        }

        $this->brackets->setWinner($slot, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Advance undone.']);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    /**
     * Run a bracket mutation, mapping its domain errors onto the form. Returns
     * the error redirect on failure, or null when the action succeeded.
     */
    private function failureOrNull(callable $action, string $errorKey): ?RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors([$errorKey => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Run a bracket mutation and, on success, flash a toast and return to the
     * division's matches tab.
     */
    private function attempt(
        callable $action,
        string $errorKey,
        string $successMessage,
        Tournament $tournament,
        Division $division,
    ): RedirectResponse {
        if ($failure = $this->failureOrNull($action, $errorKey)) {
            return $failure;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $successMessage]);

        return $this->redirectToDivisionBracket($tournament, $division);
    }

    /**
     * @return array<string, mixed>
     */
    private function slotOverrides(FormRequest $request): array
    {
        return array_filter(
            $request->validated(),
            fn ($value) => $value !== null,
        );
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
