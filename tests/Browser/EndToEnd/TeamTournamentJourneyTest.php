<?php

use App\Enums\BracketStatus;
use App\Enums\MatchStatus;
use App\Services\BracketService;

/**
 * Drives a whole team tournament through the real public and admin UI:
 * register a team, settle it, generate round-robin groups, play every group
 * tie on the scoreboard, promote the winners into a knockout with a bye, then
 * play the knockout to a champion. A team tie is a stack of rubbers and is
 * never auto-resolved — the tie winner is always an explicit admin decision.
 */
describe('team tournament journey', function () {
    it('registers a team through the public form', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(5);

        $registration = teamJourneyRegisterTeamViaUi($tournament, $division, 'Net Ninjas', 'ninjas@example.com');
        $registration->load(['team', 'players']);

        expect($registration->payment_status)->toBe('processing')
            ->and($registration->team?->name)->toBe('Net Ninjas')
            ->and($registration->players)->toHaveCount(2);

        teamJourneyLogin($admin);

        $page = teamJourneyGotoDivision($tournament, $division, 'registrants');
        $page->assertPresent('@registrant-row-'.$registration->id)
            ->assertSee('Net Ninjas')
            ->assertNoJavaScriptErrors();
    });

    it('marks a registration paid from the admin UI', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(5);

        $registration = teamJourneyRegisterTeamViaUi($tournament, $division, 'Net Ninjas', 'ninjas@example.com');

        teamJourneyLogin($admin);
        $paid = teamJourneyMarkPaidViaUi($tournament, $division, $registration);

        expect($paid->payment_status)->toBe('paid')
            ->and($paid->paid_at)->not->toBeNull();
    });

    it('generates three round-robin groups through the UI', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(6);

        teamJourneyLogin($admin);
        $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
        teamJourneyGenerateGroups($page, 3);

        $groupStage = teamJourneyGroupStage($division)->load('groups.slots');

        expect($groupStage->groups)->toHaveCount(3)
            ->and($groupStage->groups->first()->slots)->toHaveCount(1);

        $page->assertSee('Group A')
            ->assertSee('Group B')
            ->assertSee('Group C')
            ->assertNoJavaScriptErrors();
    });

    it('plays a group tie through the rubbers dialog and scoreboard', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(6);

        teamJourneyLogin($admin);
        $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
        teamJourneyGenerateGroups($page, 3);

        $group = teamJourneyGroupStage($division)->groups()->with('slots')->first();
        $slot = $group->slots->first();

        $match = teamJourneyPlayTieViaUi($tournament, $division, $slot->id, 1);
        $slot->refresh();

        expect($slot->winner_entrant_id)->toBe($slot->entrant_a_id)
            ->and($match->status)->toBe(MatchStatus::Completed);

        $standings = app(BracketService::class)->standings($group->fresh());
        $winnerRow = $standings->firstWhere('entrant_id', $slot->entrant_a_id);

        expect($winnerRow)->not->toBeNull();
        expect($winnerRow['wins'])->toBe(1)
            ->and($winnerRow['games_for'])->toBeGreaterThan(0);
    });

    it('plays every group tie and picks the group winners', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(6);

        teamJourneyLogin($admin);
        teamJourneyCompleteGroupStage($tournament, $division);

        $picked = teamJourneyGroupStage($division)->groups()->get();

        expect($picked->every(fn ($group) => count($group->winner_entrant_ids ?? []) === 1))->toBeTrue();
    });

    it('promotes the group winners into a knockout with a bye', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(6);

        teamJourneyLogin($admin);
        teamJourneyCompleteGroupStage($tournament, $division);
        teamJourneyPromoteWinners($tournament, $division);

        $knockout = teamJourneyKnockout($division);
        $roundOne = $knockout->slots()->where('round', 1)->orderBy('position')->get();

        expect($knockout->size)->toBe(4)
            ->and($roundOne->where('bye', true))->toHaveCount(1)
            ->and($roundOne->firstWhere('bye', true)->winner_entrant_id)->not->toBeNull()
            ->and($roundOne->firstWhere('bye', false))->not->toBeNull();
    });

    it('plays the knockout to a champion through the scoreboard', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(6);

        teamJourneyLogin($admin);
        teamJourneyCompleteGroupStage($tournament, $division);
        teamJourneyPromoteWinners($tournament, $division);

        $knockout = teamJourneyKnockout($division);
        $live = $knockout->slots()->where('round', 1)->where('bye', false)->firstOrFail();
        $final = $knockout->finalSlot();

        // The live semi decides who meets the bye entrant in the final.
        teamJourneyPlayTieViaUi($tournament, $division, $live->id, 1);

        $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
        $page->assertPresent('@advance-slot-'.$final->id.'-side-1');

        teamJourneyPlayTieViaUi($tournament, $division, $final->id, 1);

        $knockout->refresh();
        $champion = $final->fresh()->winnerEntrant()->firstOrFail();

        expect($knockout->status)->toBe(BracketStatus::Completed);

        $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
        $page->assertSee($champion->display_name)->assertNoJavaScriptErrors();
    });

    it('carries the whole team tournament from registration to champion', function () {
        [$admin, $tournament, $division] = teamJourneyTournament(5);

        // The sixth team joins through the public form and is settled by hand.
        $registration = teamJourneyRegisterTeamViaUi($tournament, $division, 'Net Ninjas', 'ninjas@example.com');
        teamJourneyBeat();

        teamJourneyLogin($admin);
        $paid = teamJourneyMarkPaidViaUi($tournament, $division, $registration);
        teamJourneyBeat();

        expect($paid->payment_status)->toBe('paid');

        // Round-robin groups, then the knockout.
        teamJourneyCompleteGroupStage($tournament, $division);
        teamJourneyBeat();

        expect(teamJourneyGroupStage($division)->groups()->count())->toBe(3);

        teamJourneyPromoteWinners($tournament, $division);
        teamJourneyBeat();

        // Knockout: the live semi, then the final.
        $knockout = teamJourneyKnockout($division);
        $live = $knockout->slots()->where('round', 1)->where('bye', false)->firstOrFail();
        $final = $knockout->finalSlot();

        teamJourneyPlayTieViaUi($tournament, $division, $live->id, 1);
        teamJourneyBeat();
        teamJourneyPlayTieViaUi($tournament, $division, $final->id, 1);

        $knockout->refresh();
        $champion = $final->fresh()->winnerEntrant()->firstOrFail();

        expect($knockout->status)->toBe(BracketStatus::Completed);

        $page = teamJourneyGotoDivision($tournament, $division, 'brackets');
        $page->assertSee($champion->display_name)->assertNoJavaScriptErrors();
    });
});
