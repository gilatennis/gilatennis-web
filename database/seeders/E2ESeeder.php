<?php

namespace Database\Seeders;

use App\Enums\BracketStatus;
use App\Enums\BracketType;
use App\Enums\TennisMatchEventType;
use App\Enums\TournamentStatus;
use App\Models\Bracket;
use App\Models\BracketGroup;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Role;
use App\Models\Team;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Models\User;
use App\Services\TennisMatchScoringService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Deterministic fixture for the Playwright suite (docs/E2E.md).
 *
 * Kept separate from DatabaseSeeder so the dev seed data is never touched.
 * Credentials and slugs are mirrored in e2e/helpers/accounts.ts — keep in sync.
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('e2e')) {
            throw new RuntimeException('E2ESeeder is destructive and only runs in the e2e environment.');
        }

        $this->call(RoleSeeder::class);

        $this->admin();

        $this->liveOpen();

        $this->board();

        $this->rubbers();

        $this->registration();
    }

    /**
     * A single-division tournament used by the live-scoring spec.
     */
    private function liveOpen(): void
    {
        $tournament = Tournament::updateOrCreate(
            ['slug' => 'e2e-live-open'],
            [
                'name' => 'E2E Live Open',
                'status' => TournamentStatus::Open,
                'dates' => today()->format('M j').' - '.today()->addDays(2)->format('M j, Y'),
                'start' => today(),
                'end' => today()->addDays(2),
                'city' => 'Jakarta',
                'surface' => 'hard',
                'venue_type' => 'outdoor',
                'ball_type' => 'Penn Championship',
                'registration_closes' => today()->addDay(),
            ],
        );

        $division = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'e2e-singles'],
            [
                'name' => 'E2E Singles',
                'participation_category' => 'singles',
                'draw_size' => 8,
                'fee' => 50000,
                'sort_order' => 0,
            ],
        );

        // A match the admin will start and score from the scoreboard.
        $this->match($division, 101, 'E2E Pending A', 'E2E Pending B', 'pending');

        // A match already on court, ready to score immediately.
        $this->match($division, 102, 'E2E Live A', 'E2E Live B', 'running');

        // A finished match so the board shows a "Recent results" section.
        $this->match($division, 103, 'E2E Done A', 'E2E Done B', 'completed', [
            'server' => 1,
            'first_server' => 1,
            'set_a' => 2,
            'set_b' => 0,
            'sets' => [
                ['a' => 6, 'b' => 4],
                ['a' => 6, 'b' => 3],
            ],
            'win' => 1,
        ]);
    }

    /**
     * A multi-stage, multi-division tournament used by the live-board spec:
     * group stage + knockout, a knockout-only division, a team tie, and an
     * ad-hoc slot-less match.
     */
    private function board(): void
    {
        $tournament = Tournament::updateOrCreate(
            ['slug' => 'e2e-board'],
            [
                'name' => 'E2E Board Open',
                'status' => TournamentStatus::Open,
                'dates' => today()->format('M j').' - '.today()->addDays(2)->format('M j, Y'),
                'start' => today(),
                'end' => today()->addDays(2),
                'city' => 'Jakarta',
                'surface' => 'hard',
                'venue_type' => 'outdoor',
                'ball_type' => 'Penn Championship',
                'registration_closes' => today()->addDay(),
            ],
        );

        $this->boardSingles($tournament);
        $this->boardDoubles($tournament);
        $this->boardTeam($tournament);
    }

    private function boardSingles(Tournament $tournament): void
    {
        $division = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'board-singles'],
            [
                'name' => 'Singles',
                'participation_category' => 'singles',
                'draw_size' => 8,
                'fee' => 50000,
                'sort_order' => 0,
            ],
        );

        $groupStage = Bracket::create([
            'division_id' => $division->id,
            'type' => BracketType::GroupStage,
            'status' => BracketStatus::Active,
            'size' => 8,
        ]);

        $groupA = BracketGroup::create(['bracket_id' => $groupStage->id, 'name' => 'Group A', 'position' => 0, 'advance_count' => 2]);
        $groupB = BracketGroup::create(['bracket_id' => $groupStage->id, 'name' => 'Group B', 'position' => 1, 'advance_count' => 2]);

        // Five finished ties in Group A (the board caps a bucket at three) plus
        // one on court, so the group is also represented in "On court now".
        $position = 0;

        for ($i = 0; $i < 5; $i++) {
            $slot = $this->slot($groupStage, $groupA, 1, $position++);
            $this->match($division, 200 + $i, "Board A{$i} One", "Board A{$i} Two", 'completed', $this->completedState(), $slot->id, now()->subHours(5));
        }

        $runningSlot = $this->slot($groupStage, $groupA, 1, $position++);
        $this->match($division, 205, 'Board Live A', 'Board Live B', 'running', [], $runningSlot->id);

        $groupBSlot = $this->slot($groupStage, $groupB, 1, $position++);
        $this->match($division, 206, 'Board B0 One', 'Board B0 Two', 'completed', $this->completedState(), $groupBSlot->id, now()->subHours(4));

        $knockout = Bracket::create([
            'division_id' => $division->id,
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Active,
            'size' => 4,
        ]);

        $semi = $this->slot($knockout, null, 1, 0, 'Semifinal');
        $this->match($division, 207, 'Board Semi One', 'Board Semi Two', 'completed', $this->completedState(), $semi->id, now()->subHour());

        $final = $this->slot($knockout, null, 2, 0, 'Final');
        $this->match($division, 208, 'Board Final One', 'Board Final Two', 'running', [], $final->id);

        // An ad-hoc match with no bracket slot → the "Matches" bucket.
        $this->match($division, 209, 'Board Adhoc One', 'Board Adhoc Two', 'completed', $this->completedState(), null, now()->subHours(3));
    }

    private function boardDoubles(Tournament $tournament): void
    {
        $division = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'board-doubles'],
            [
                'name' => 'Doubles',
                'participation_category' => 'doubles',
                'draw_size' => 4,
                'fee' => 50000,
                'sort_order' => 1,
            ],
        );

        $knockout = Bracket::create([
            'division_id' => $division->id,
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Active,
            'size' => 2,
        ]);

        $final = $this->slot($knockout, null, 1, 0, 'Final');
        $this->match($division, 210, 'Board Doubles One', 'Board Doubles Two', 'completed', $this->completedState(), $final->id);
    }

    private function boardTeam(Tournament $tournament): void
    {
        $division = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'board-team'],
            [
                'name' => 'Team',
                'participation_category' => 'team',
                'draw_size' => 2,
                'fee' => 100000,
                'sort_order' => 2,
                'min_team_size' => 2,
                'max_team_size' => 2,
            ],
        );

        $entrantA = $this->teamEntrant($tournament, $division, 'Board Team Alpha');
        $entrantB = $this->teamEntrant($tournament, $division, 'Board Team Bravo');

        $knockout = Bracket::create([
            'division_id' => $division->id,
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Active,
            'size' => 2,
        ]);

        $tie = $this->slot($knockout, null, 1, 0, 'Final');
        $tie->update(['entrant_a_id' => $entrantA->id, 'entrant_b_id' => $entrantB->id]);

        // Two rubbers under one tie: one finished, one on court.
        $this->match($division, 211, 'Rubber One A', 'Rubber One B', 'completed', $this->completedState(), $tie->id);
        $this->match($division, 212, 'Rubber Two A', 'Rubber Two B', 'running', [], $tie->id);
    }

    /**
     * A bracket-less tournament with singles, doubles and team divisions for
     * the admin registration CRUD spec, plus one division whose draw is already
     * live with a placed entrant (to prove edit/delete is guarded).
     */
    /**
     * A team tournament with one prepared tie and no rubbers yet, used by the
     * rubbers spec to add a rubber through the modal and read it back.
     */
    private function rubbers(): void
    {
        $tournament = Tournament::updateOrCreate(
            ['slug' => 'e2e-rubber'],
            [
                'name' => 'E2E Rubber Open',
                'status' => TournamentStatus::Open,
                'dates' => today()->format('M j').' - '.today()->addDays(2)->format('M j, Y'),
                'start' => today(),
                'end' => today()->addDays(2),
                'city' => 'Jakarta',
                'surface' => 'hard',
                'venue_type' => 'outdoor',
                'ball_type' => 'Penn Championship',
                'registration_closes' => today()->addDay(),
            ],
        );

        $division = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'rubber-team'],
            [
                'name' => 'Rubber Team',
                'participation_category' => 'team',
                'draw_size' => 2,
                'fee' => 100000,
                'sort_order' => 0,
                'min_team_size' => 2,
                'max_team_size' => 2,
            ],
        );

        $entrantA = $this->teamEntrant($tournament, $division, 'Rubber Team Alpha');
        $entrantB = $this->teamEntrant($tournament, $division, 'Rubber Team Bravo');

        // One ready tie with no rubbers: the modal opens straight into the form.
        $knockout = Bracket::create([
            'division_id' => $division->id,
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Active,
            'size' => 2,
        ]);

        $tie = $this->slot($knockout, null, 1, 0, 'Final');
        $tie->update(['entrant_a_id' => $entrantA->id, 'entrant_b_id' => $entrantB->id]);
    }

    private function registration(): void
    {
        $tournament = Tournament::updateOrCreate(
            ['slug' => 'e2e-registration'],
            [
                'name' => 'E2E Reg Open',
                'status' => TournamentStatus::Open,
                'dates' => today()->format('M j').' - '.today()->addDays(2)->format('M j, Y'),
                'start' => today(),
                'end' => today()->addDays(2),
                'city' => 'Jakarta',
                'surface' => 'hard',
                'venue_type' => 'outdoor',
                'ball_type' => 'Penn Championship',
                'registration_closes' => today()->addDay(),
            ],
        );

        Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'reg-singles'],
            [
                'name' => 'E2E Reg Singles',
                'participation_category' => 'singles',
                'draw_size' => 8,
                'fee' => 50000,
                'sort_order' => 0,
            ],
        );

        Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'reg-doubles'],
            [
                'name' => 'E2E Reg Doubles',
                'participation_category' => 'doubles',
                'draw_size' => 4,
                'fee' => 50000,
                'sort_order' => 1,
            ],
        );

        Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'reg-team'],
            [
                'name' => 'E2E Reg Team',
                'participation_category' => 'team',
                'draw_size' => 4,
                'fee' => 100000,
                'sort_order' => 2,
                'min_team_size' => 2,
                'max_team_size' => 2,
            ],
        );

        $guardDivision = Division::updateOrCreate(
            ['tournament_id' => $tournament->id, 'slug' => 'reg-guard'],
            [
                'name' => 'E2E Reg Guard',
                'participation_category' => 'singles',
                'draw_size' => 2,
                'fee' => 50000,
                'sort_order' => 3,
            ],
        );

        $guardEntry = TournamentRegistration::factory()
            ->forTournament($tournament)
            ->create([
                'division_id' => $guardDivision->id,
                'name' => 'E2E Guard Entry',
                'payment_status' => 'paid',
            ]);

        TournamentRegistrationPlayer::create([
            'tournament_registration_id' => $guardEntry->id,
            'tournament_id' => $tournament->id,
            'name' => 'E2E Guard Player',
            'email' => 'guard.player@example.com',
            'phone' => '081200000001',
            'gender' => 'male',
            'sort_order' => 0,
        ]);

        $bracket = Bracket::create([
            'division_id' => $guardDivision->id,
            'type' => BracketType::SingleElimination,
            'status' => BracketStatus::Active,
            'size' => 2,
        ]);

        $slot = $this->slot($bracket, null, 1, 0, 'Final');
        $slot->update(['entrant_a_id' => $guardEntry->id]);
    }

    private function teamEntrant(Tournament $tournament, Division $division, string $name): TournamentRegistration
    {
        $team = Team::create(['name' => $name]);

        return TournamentRegistration::factory()
            ->forTournament($tournament)
            ->create([
                'division_id' => $division->id,
                'team_id' => $team->id,
                'name' => $name,
                'payment_status' => 'paid',
            ]);
    }

    private function slot(Bracket $bracket, ?BracketGroup $group, int $round, int $position, ?string $label = null): BracketSlot
    {
        return BracketSlot::create([
            'bracket_id' => $bracket->id,
            'bracket_group_id' => $group?->id,
            'round' => $round,
            'position' => $position,
            'leg' => 1,
            'label' => $label,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function completedState(): array
    {
        return [
            'server' => 1,
            'first_server' => 1,
            'set_a' => 2,
            'set_b' => 0,
            'sets' => [
                ['a' => 6, 'b' => 4],
                ['a' => 6, 'b' => 3],
            ],
            'win' => 1,
        ];
    }

    private function admin(): void
    {
        $admin = User::updateOrCreate(
            ['email' => config('e2e.admin_email')],
            [
                'name' => 'E2E Admin',
                'password' => Hash::make(config('e2e.admin_password')),
                'email_verified_at' => now(),
                'is_guest' => false,
            ],
        );

        $roleId = Role::where('slug', User::ROLE_ADMIN)->value('id');

        if ($roleId !== null) {
            $admin->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function match(Division $division, int $id, string $a, string $b, string $status, array $state = [], ?int $bracketSlotId = null, ?CarbonInterface $endedAt = null): TennisMatch
    {
        $match = new TennisMatch([
            'division_id' => $division->id,
            'bracket_slot_id' => $bracketSlotId,
            'side_a_name' => $a,
            'side_b_name' => $b,
            'sets_to_win' => 2,
            'games_to_win' => 6,
            'extend_at_tie' => false,
            'tiebreak_to' => 7,
            'status' => $status,
            'started_at' => $status === 'pending' ? null : ($endedAt !== null ? $endedAt->copy()->subHours(2) : now()->subMinutes(30)),
            'ended_at' => $status === 'completed' ? ($endedAt ?? now()->subMinutes(5)) : null,
        ]);

        // Explicit ids keep the fixture addressable from the Playwright specs.
        $match->id = $id;
        $match->save();

        if ($status === 'running') {
            // Start the match the way the app does — record a server event — so
            // the scoreboard, which replays the event log, keeps `first_server`
            // after the first point instead of reverting to "pick who serves
            // first". Setting the state column directly would be discarded on
            // the next projection (the replay finds no server event).
            app(TennisMatchScoringService::class)->appendEvent($match, TennisMatchEventType::Server, 1);
        } elseif ($state !== []) {
            $match->state()->update($state);
        }

        return $match;
    }
}
