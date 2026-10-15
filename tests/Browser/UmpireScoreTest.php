<?php

use App\Enums\MatchStatus;
use App\Models\TennisMatch;

function openUmpireScore(TennisMatch $match)
{
    return visit(route('umpire.score', ['match' => $match->share_token]))
        ->wait(1)
        ->assertPathIs('/score/'.$match->share_token);
}

describe('public umpire scoreboard', function () {
    it('scores a match from the shared link without logging in', function () {
        $match = TennisMatch::factory()->fast4()->create();

        $page = openUmpireScore($match);

        // Public shell only: the board renders, the admin navigation does not.
        $page->assertSee('Umpire')
            ->assertSee('Who serves first?')
            ->assertDontSee('System Settings')
            ->assertMissing('@start-match');

        $page->click('@server-pick-a')
            ->assertButtonEnabled('@start-match')
            ->click('@start-match')
            ->wait(2)
            ->assertSee('Running');

        $page->click('@point-a')->wait(2);

        $fresh = $match->fresh();

        expect($fresh->status)->toBe(MatchStatus::Running)
            ->and($fresh->events()->count())->toBe(2)
            ->and($fresh->state->first_server)->toBe(1);

        $page->assertNoJavaScriptErrors();
    });

    it('reopens a completed match from the shared link', function () {
        $match = TennisMatch::factory()->completed()->create();

        $page = openUmpireScore($match);

        $page->assertVisible('@reopen-match')
            ->click('@reopen-match')
            ->wait(2)
            ->assertMissing('@reopen-match')
            ->assertVisible('@server-pick-a');

        expect($match->fresh()->status)->toBe(MatchStatus::Running);

        $page->assertNoJavaScriptErrors();
    });
});
