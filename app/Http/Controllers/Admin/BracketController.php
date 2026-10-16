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
use App\Http\Requests\Admin\BracketSettingsRequest;
use App\Http\Requests\Admin\BracketSlotEntrantRequest;
use App\Http\Requests\Admin\BracketSlotStartRequest;
use App\Http\Requests\Admin\BracketSlotUpdateRequest;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\BracketService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;

class BracketController extends Controller
{
    public function __construct(private BracketService $brackets) {}

    public function store(BracketGenerateRequest $request, Tournament $tournament, Division $division): RedirectResponse
    {
        if ($division->groupStage()->exists()) {
            $message = 'Promote winners from the group stage to build the knockout bracket.';

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->withErrors(['bracket' => $message]);
        }

        return $this->attempt(
            fn () => $this->brackets->generate(
                $division,
                $request->validated('seeding_mode'),
                $request->validated('manual_assignment', false),
            ),
            'bracket',
            'Bracket generated.',
            $tournament,
            $division,
        );
    }

    /**
     * Toggle whether entrants may be placed into the knockout's first round by
     * hand. Only permitted while the draw is still a draft.
     */
    public function updateBracketSettings(
        BracketSettingsRequest $request,
        Tournament $tournament,
        Division $division,
    ): RedirectResponse {
        $bracket = $division->bracket ?? abort(404);

        return $this->attempt(
            fn () => $this->brackets->setManualAssignment(
                $bracket,
                $request->validated('manual_assignment'),
            ),
            'bracket',
            'Bracket updated.',
            $tournament,
            $division,
        );
    }

    /**
     * Place an entrant into (or clear) one side of a first-round slot by hand.
     */
    public function assignSlotEntrant(
        BracketSlotEntrantRequest $request,
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
    ): RedirectResponse {
        $entrantId = $request->validated('entrant_id');

        $entrant = $entrantId === null
            ? null
            : TournamentRegistration::where('division_id', $division->id)->findOrFail($entrantId);

        return $this->attempt(
            fn () => $this->brackets->assignSlotEntrant($slot, $request->validated('side'), $entrant),
            'entrant_id',
            'Slot updated.',
            $tournament,
            $division,
        );
    }

    /**
     * Finish a manual-assignment draw: resolve byes and lock the first round.
     */
    public function completeDraw(
        Tournament $tournament,
        Division $division,
    ): RedirectResponse {
        $bracket = $division->bracket ?? abort(404);

        return $this->attempt(
            fn () => $this->brackets->completeDraw($bracket),
            'bracket',
            'Draw completed.',
            $tournament,
            $division,
        );
    }

    /**
     * Reopen a completed manual draw for further editing.
     */
    public function reopenDraw(
        Tournament $tournament,
        Division $division,
    ): RedirectResponse {
        $bracket = $division->bracket ?? abort(404);

        return $this->attempt(
            fn () => $this->brackets->reopenDraw($bracket),
            'bracket',
            'Draw reopened for editing.',
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
     * Remove an entrant from the group stage, rebuilding the affected group's
     * fixtures. Blocked once the group has started.
     */
    public function removeGroupEntrant(
        BracketGroupEntrantRequest $request,
        Tournament $tournament,
        Division $division,
        BracketGroup $group,
    ): RedirectResponse {
        $entrant = TournamentRegistration::where('division_id', $division->id)
            ->findOrFail($request->integer('entrant_id'));

        $isMember = BracketGroupEntrant::where('bracket_group_id', $group->id)
            ->where('tournament_registration_id', $entrant->id)
            ->exists();

        if (! $isMember) {
            return back()->withErrors(['entrant_id' => 'That entrant is not in this group.']);
        }

        return $this->attempt(
            fn () => $this->brackets->removeEntrant($division, $entrant),
            'entrant_id',
            'Entrant removed from group.',
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
            if ($failure = $this->failureOrNull(fn () => $this->brackets->delete($bracket), 'bracket')) {
                return $failure;
            }
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

    /**
     * Remove a rubber from a team tie. Only pending rubbers may be deleted, so
     * the request is rejected once a rubber has been sent to court. The match
     * is bound to the slot by the route's scoped binding.
     */
    public function removeRubber(
        Tournament $tournament,
        Division $division,
        BracketSlot $slot,
        TennisMatch $match,
    ): RedirectResponse {
        if ($division->participation_category !== 'team') {
            return back()->withErrors(['slot' => 'Rubbers are only used for team ties.']);
        }

        return $this->attempt(
            fn () => $this->brackets->removeRubber($match),
            'slot',
            'Rubber removed.',
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

        if ($failure = $this->failureOrNull(
            fn () => $this->brackets->setWinner($slot, $winner, $request->validated('reason')),
            'slot',
        )) {
            return $failure;
        }

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

        $slot->loadMissing('bracket');

        if ($slot->bracket->isEditing()) {
            $this->brackets->undoWhileEditing($slot);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Advance undone.']);

            return $this->redirectToDivisionBracket($tournament, $division);
        }

        if ($slot->bye) {
            return back()->withErrors(['slot' => 'Byes advance automatically.']);
        }

        if ($this->brackets->laterRoundsHaveProgress($slot)) {
            return back()->withErrors(['slot' => 'Undo the later rounds first.']);
        }

        if ($failure = $this->failureOrNull(fn () => $this->brackets->setWinner($slot, null), 'slot')) {
            return $failure;
        }

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
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors([$errorKey => $e->getMessage()]);
        } catch (QueryException $e) {
            Log::error('Bracket operation failed', ['error' => $e->getMessage()]);

            $message = 'The bracket operation could not be completed. Please try again.';

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->withErrors([$errorKey => $message]);
        }

        return null;
    }

    /**
     * Run a bracket mutation and, on success, flash a toast and return to the
     * division's brackets tab.
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
            'tab' => 'brackets',
        ]);
    }
}
