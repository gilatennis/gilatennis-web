<?php

use App\Enums\BracketType;
use App\Enums\TennisMatchEventType;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\LiveScoreService;
use App\Services\TennisMatchScoringService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function liveTournament(array $attributes = []): Tournament
{
    return Tournament::factory()->create($attributes + [
        'slug' => 'live-open',
        'status' => 'open',
    ]);
}

function liveMatch(Tournament $tournament, string $state): TennisMatch
{
    $division = Division::factory()->for($tournament)->create();

    return TennisMatch::factory()
        ->{$state}()
        ->for($division)
        ->create([
            'side_a_name' => 'Side A',
            'side_b_name' => 'Side B',
        ]);
}

it('shows running and recently completed matches on the public live board', function () {
    $tournament = liveTournament();
    liveMatch($tournament, 'running');
    liveMatch($tournament, 'completed');

    $this->get(route('tournaments.live', $tournament))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tournaments/Live')
            ->where('tournament.slug', 'live-open')
            ->has('liveMatches', 1)
            ->has('recentResults', 1)
            ->where('liveMatches.0.status', 'running')
            ->where('recentResults.0.status', 'completed')
            // Spectators get the score, never scoring internals.
            ->missing('liveMatches.0.event_count')
            ->missing('liveMatches.0.entrant_a_id')
            ->missing('liveMatches.0.created_by')
        );
});

it('reflects a point as soon as it is recorded', function () {
    $tournament = liveTournament();
    $match = liveMatch($tournament, 'running');

    app(TennisMatchScoringService::class)->appendEvent($match, TennisMatchEventType::Point, 1);

    $this->get(route('tournaments.live', $tournament))
        ->assertInertia(fn (Assert $page) => $page->where('liveMatches.0.game_a', 1));
});

it('hides draft and canceled-and-over tournaments', function () {
    $draft = liveTournament(['slug' => 'live-draft', 'status' => 'draft']);
    Division::factory()->for($draft)->create();

    $canceled = liveTournament([
        'slug' => 'live-canceled',
        'status' => 'canceled',
        'end' => today()->subDay(),
    ]);

    $this->get(route('tournaments.live', $draft))->assertNotFound();
    $this->get(route('tournaments.live', $canceled))->assertNotFound();
});

it('loads the live board with a query count that does not grow per match', function () {
    $tournament = liveTournament();

    liveMatch($tournament, 'running');

    $service = app(LiveScoreService::class);

    DB::enableQueryLog();
    $service->liveMatches($tournament);
    $one = count(DB::getQueryLog());
    DB::flushQueryLog();

    liveMatch($tournament, 'running');
    liveMatch($tournament, 'running');
    liveMatch($tournament, 'running');

    DB::flushQueryLog();
    $service->liveMatches($tournament);
    $four = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The fixed eager-load cost for slot-less matches; a change here is a
    // deliberate review point, not something that should drift upward.
    expect($one)->toBeLessThanOrEqual(3);

    // …and extra matches must not add queries on top of it.
    expect($four)->toBe($one);
});

it('exposes stage, group and round metadata for bracket matches', function () {
    $tournament = liveTournament();
    $division = Division::factory()->for($tournament)->create();

    $groupBracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::GroupStage]);
    $group = BracketGroup::factory()->forBracket($groupBracket)->named('Group A', 0)->create();
    $groupSlot = BracketSlot::factory()->inGroup($group)->round(2, 0)->create();
    $groupMatch = TennisMatch::factory()->running()->for($division)->create(['bracket_slot_id' => $groupSlot->id]);

    $knockoutBracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::SingleElimination]);
    $finalSlot = BracketSlot::factory()->forBracket($knockoutBracket)->round(3, 0)->create(['label' => 'Final']);
    $finalMatch = TennisMatch::factory()->running()->for($division)->create(['bracket_slot_id' => $finalSlot->id]);

    $standalone = TennisMatch::factory()->running()->for($division)->create();

    $presented = collect(app(LiveScoreService::class)->liveMatches($tournament))->keyBy('id');

    expect($presented[$groupMatch->id]['stage'])->toBe('group_stage')
        ->and($presented[$groupMatch->id]['group']['name'])->toBe('Group A')
        ->and($presented[$groupMatch->id]['round'])->toBe(['number' => 2, 'label' => null])
        ->and($presented[$finalMatch->id]['stage'])->toBe('knockout')
        ->and($presented[$finalMatch->id]['round']['label'])->toBe('Final')
        ->and($presented[$standalone->id]['stage'])->toBeNull()
        ->and($presented[$standalone->id]['group'])->toBeNull()
        ->and($presented[$standalone->id]['round'])->toBeNull();
});

it('caps recent results per bucket so one group cannot crowd out another', function () {
    $tournament = liveTournament();
    $division = Division::factory()->for($tournament)->create();

    $bracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::GroupStage]);
    $groupA = BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();
    $groupB = BracketGroup::factory()->forBracket($bracket)->named('Group B', 1)->create();

    $slotA = BracketSlot::factory()->inGroup($groupA)->round(1, 0)->create();
    $slotB = BracketSlot::factory()->inGroup($groupB)->round(1, 1)->create();

    for ($i = 0; $i < 5; $i++) {
        TennisMatch::factory()->completed()->for($division)->create([
            'bracket_slot_id' => $slotA->id,
            'ended_at' => now()->subMinutes($i),
        ]);
        TennisMatch::factory()->completed()->for($division)->create([
            'bracket_slot_id' => $slotB->id,
            'ended_at' => now()->subMinutes($i),
        ]);
    }

    $results = collect(app(LiveScoreService::class)->recentResults($tournament));

    expect($results)->toHaveCount(6)
        ->and($results->where('group.name', 'Group A'))->toHaveCount(3)
        ->and($results->where('group.name', 'Group B'))->toHaveCount(3);
});

it('orders divisions by sort order', function () {
    $tournament = liveTournament();

    $laterDivision = Division::factory()->for($tournament)->create(['sort_order' => 1]);
    $knockoutBracket = Bracket::factory()->forDivision($laterDivision)->create(['type' => BracketType::SingleElimination]);
    $finalSlot = BracketSlot::factory()->forBracket($knockoutBracket)->round(3, 0)->create(['label' => 'Final']);
    TennisMatch::factory()->completed()->for($laterDivision)->create(['bracket_slot_id' => $finalSlot->id]);

    $earlierDivision = Division::factory()->for($tournament)->create(['sort_order' => 0]);
    $groupBracket = Bracket::factory()->forDivision($earlierDivision)->create(['type' => BracketType::GroupStage]);
    $group = BracketGroup::factory()->forBracket($groupBracket)->named('Group A', 0)->create();
    $groupSlot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create();
    TennisMatch::factory()->completed()->for($earlierDivision)->create(['bracket_slot_id' => $groupSlot->id]);

    $names = collect(app(LiveScoreService::class)->recentResults($tournament))->pluck('division.name');

    expect($names->all())->toBe([$earlierDivision->name, $laterDivision->name]);
});

it('orders recent results newest first within a division', function () {
    $tournament = liveTournament();
    $division = Division::factory()->for($tournament)->create();

    $groupBracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::GroupStage]);
    $group = BracketGroup::factory()->forBracket($groupBracket)->named('Group A', 0)->create();
    $groupSlot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create();
    $groupMatch = TennisMatch::factory()->completed()->for($division)->create([
        'bracket_slot_id' => $groupSlot->id,
        'ended_at' => now()->subHours(3),
    ]);

    $knockoutBracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::SingleElimination]);
    $finalSlot = BracketSlot::factory()->forBracket($knockoutBracket)->round(1, 0)->create(['label' => 'Final']);
    $finalMatch = TennisMatch::factory()->completed()->for($division)->create([
        'bracket_slot_id' => $finalSlot->id,
        'ended_at' => now()->subHour(),
    ]);

    $ids = collect(app(LiveScoreService::class)->recentResults($tournament))->pluck('id');

    // The newer knockout result floats above the older group-stage result.
    expect($ids->all())->toBe([$finalMatch->id, $groupMatch->id]);
});

it('orders matches within a bucket newest first', function () {
    $tournament = liveTournament();
    $division = Division::factory()->for($tournament)->create();

    $bracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::GroupStage]);
    $group = BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();
    $slot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create();

    $older = TennisMatch::factory()->completed()->for($division)->create([
        'bracket_slot_id' => $slot->id,
        'ended_at' => now()->subHours(3),
    ]);
    $newer = TennisMatch::factory()->completed()->for($division)->create([
        'bracket_slot_id' => $slot->id,
        'ended_at' => now()->subHour(),
    ]);

    $ids = collect(app(LiveScoreService::class)->recentResults($tournament))->pluck('id');

    expect($ids->all())->toBe([$newer->id, $older->id]);
});

it('keeps the live board query count flat with bracket matches', function () {
    $tournament = liveTournament();
    $service = app(LiveScoreService::class);

    $division = Division::factory()->for($tournament)->create();
    $bracket = Bracket::factory()->forDivision($division)->create(['type' => BracketType::GroupStage]);
    $group = BracketGroup::factory()->forBracket($bracket)->named('Group A', 0)->create();

    $firstSlot = BracketSlot::factory()->inGroup($group)->round(1, 0)->create();
    TennisMatch::factory()->running()->for($division)->create(['bracket_slot_id' => $firstSlot->id]);

    DB::enableQueryLog();
    $service->liveMatches($tournament);
    $one = count(DB::getQueryLog());
    DB::flushQueryLog();

    for ($i = 1; $i < 4; $i++) {
        $slot = BracketSlot::factory()->inGroup($group)->round(1, $i)->create();
        TennisMatch::factory()->running()->for($division)->create(['bracket_slot_id' => $slot->id]);
    }

    DB::flushQueryLog();
    $service->liveMatches($tournament);
    $four = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The fixed eager-load cost for bracket matches (adds the slot graph); a
    // change here is a deliberate review point, not something that should drift.
    expect($one)->toBeLessThanOrEqual(6);

    // …and extra matches must not add queries on top of it.
    expect($four)->toBe($one);
});
