# Phase 7 — Match Scoring Umpire Screen

Interactive umpire scoring screen for tennis matches. Umpire creates a match, scores point-by-point, status messages and set scores update live, match auto-completes at threshold.

## Reference

- **Design**: `/Users/kevin/open-design/.od/projects/brand-gilatennis-047d5f/umpire-app.html` (3 screens: Setup, Match, Over)
- **Brand**: `/Users/kevin/open-design/.od/projects/brand-gilatennis-047d5f/DESIGN.md` (coral + Inter Tight/Instrument Sans already integrated)
- **Go reference**: `/Users/kevin/code/gilatennis/web-app/entities/match.go` + `domain/match/service.go`

## Goals

MVP — interactive umpire screen, dummy data, admin-gated, flexible format support.

Out of scope: real umpire auth, bracket generation, Side entity (use name strings), Stage/Group/Round hierarchy, public scoreboard view.

## Stack Additions

None — uses existing Laravel 13 + Inertia Svelte 5 + Tailwind v4 + Bits UI. No new packages.

## Data Model

5 new tables. All use integer `id`, `timestamps`, `foreignId()->constrained()`. No soft deletes, no UUIDs (matches existing repo convention).

### `matches`

```php
Schema::create('matches', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $t->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
    $t->foreignId('umpire_user_id')->nullable()->constrained('users')->nullOnDelete();
    $t->foreignId('next_match_id')->nullable()->constrained('matches')->nullOnDelete();
    $t->unsignedInteger('number')->default(1);
    $t->string('side_a_name');
    $t->string('side_b_name');
    $t->unsignedTinyInteger('sets_to_win')->default(2);
    $t->unsignedTinyInteger('games_to_win')->default(6);
    $t->boolean('extend_at_tie')->default(false);
    $t->unsignedTinyInteger('tiebreak_to')->default(7);
    $t->unsignedTinyInteger('first_server_side')->nullable(); // 1=A 2=B
    $t->unsignedTinyInteger('server_side')->nullable();       // current server
    $t->enum('status', ['running','completed','canceled'])->default('running');
    $t->string('location')->nullable();
    $t->boolean('shot_recording')->default(true);
    $t->boolean('event_recording')->default(true);
    $t->datetime('started_at')->nullable();
    $t->datetime('ended_at')->nullable();
    $t->timestamps();
    $t->index(['tournament_id', 'status']);
});
```

### `match_sets`

```php
Schema::create('match_sets', function (Blueprint $t) {
    $t->id();
    $t->foreignId('match_id')->constrained()->cascadeOnDelete();
    $t->unsignedInteger('number');
    $t->unsignedInteger('side_a_score')->default(0);  // game count
    $t->unsignedInteger('side_b_score')->default(0);
    $t->enum('status', ['ongoing','completed'])->default('ongoing');
    $t->unsignedTinyInteger('winner_side')->nullable(); // 1=A 2=B
    $t->boolean('is_tiebreak')->default(false);
    $t->timestamps();
    $t->unique(['match_id', 'number']);
    $t->index('match_id');
});
```

### `games`

```php
Schema::create('games', function (Blueprint $t) {
    $t->id();
    $t->foreignId('match_set_id')->constrained()->cascadeOnDelete();
    $t->foreignId('match_id')->constrained()->cascadeOnDelete();
    $t->unsignedInteger('number');
    $t->unsignedInteger('side_a_score')->default(0);  // point count
    $t->unsignedInteger('side_b_score')->default(0);
    $t->enum('status', ['ongoing','completed'])->default('ongoing');
    $t->unsignedTinyInteger('winner_side')->nullable();
    $t->boolean('is_tiebreak')->default(false);
    $t->timestamps();
    $t->unique(['match_set_id', 'number']);
    $t->index('match_id');
});
```

### `points`

```php
Schema::create('points', function (Blueprint $t) {
    $t->id();
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('match_id')->constrained()->cascadeOnDelete();
    $t->unsignedInteger('number');
    $t->unsignedTinyInteger('winner_side'); // 1=A 2=B
    $t->enum('shot_type', ['ace','winner','unforced_error','forced_error'])->nullable();
    $t->timestamp('created_at')->useCurrent();
});
```
No `updated_at` — points are immutable.

### `match_undo_snapshots`

```php
Schema::create('match_undo_snapshots', function (Blueprint $t) {
    $t->id();
    $t->foreignId('match_id')->constrained()->cascadeOnDelete();
    $t->unsignedBigInteger('point_id')->nullable()->constrained('points')->nullOnDelete();
    $t->json('state'); // full state snapshot
    $t->timestamp('created_at')->useCurrent();
    $t->index(['match_id', 'created_at']);
});
```

## Models

- `TennisMatch` (table `matches`) — `Match` is a PHP reserved word, so model is `TennisMatch`. `TennisMatch::StatusRunning = 'running'` etc. constants for status. Accessor: `getSetsScoreStringAttribute()` → `"6-4, 3-6, 7-6(3)"`. Relationships: `tournament, division, umpire, nextMatch, sets, points`.
- `MatchSet` — relationships: `match, games`. Casts: `is_tiebreak → bool, winner_side → int`.
- `Game` — relationships: `matchSet, match, points`. Same casts.
- `Point` — `const UPDATED_AT = null`. Casts: `winner_side → int, shot_type → string`. Relationships: `game, match`.

All use `HasFactory`, `$fillable` arrays (no `$guarded`), no SoftDeletes.

## Service: `app/Services/MatchScoringService.php`

Pure logic, port from `domain/match/service.go` (Go reference). All mutations wrap in `DB::transaction()`.

### Methods

```php
public function createMatch(array $data): TennisMatch
public function addPoint(int $matchId, int $side, ?string $shotType = null): void
public function undoLastPoint(int $matchId): void
public function resumeMatch(int $matchId): void
public function cancelMatch(int $matchId): void
```

### `createMatch($data)` flow
1. Insert `TennisMatch` with `status='running'`, `started_at=now()`, `server_side=$data['first_server_side']`.
2. Create `MatchSet` #1, then `Game` #1 in that set.
3. Return match.

### `addPoint($matchId, $side, $shotType)` flow
1. Load match with latest set + latest game.
2. Write `match_undo_snapshots` row capturing pre-state.
3. Insert `Point` (increment number from current count).
4. Call `saveScores($matchId)` — recompute all derived scores from points log.
5. Auto-progress: if current game just completed:
   - If current set just completed:
     - Increment `sets_won` for winner (computed from completed sets).
     - Rotate `server_side` for new set.
     - If `sets_won >= $match->sets_to_win`: `status='completed'`, `ended_at=now()`.
     - Else: create new `MatchSet` + first `Game`.
   - Else (set ongoing):
     - Compute `shouldBeTiebreak = !$match->extend_at_tie && $aScore == $match->games_to_win && $bScore == $match->games_to_win`.
     - Create new `Game` in current set with `is_tiebreak=$shouldBeTiebreak`.
6. If point was in tiebreak: rotate server per `tbServerRotation(tbTotalPoints, $firstTbServer)`.
7. If game just completed (non-TB): rotate server.

### `undoLastPoint($matchId)` flow
1. Find latest `match_undo_snapshots` row for match.
2. Read snapshot JSON, restore state on match (server_side), set (games_won counts), game (is_tiebreak, server_before), point numbering.
3. Delete the snapshot + the point it referenced.
4. Recompute via `saveScores`.

### `saveScores($matchId)` logic
For each set in match, for each game in set:
- Reset `side_a_score`/`side_b_score` to 0, sum from `points` (count winner_side=1 vs =2).
- Game complete if regular `score >= 4 && diff >= 2`, or TB `score >= $match->tiebreak_to && diff >= 2`.
- Set `game.winner_side` and `game.status`.
- Accumulate game count into `set.side_a_score`/`side_b_score`.

For each set:
- If any game in set has `is_tiebreak=true`: set complete, winner = higher game count.
- Else if `aScore >= $match->games_to_win && aScore - bScore >= 2`: A wins.
- Else if `bScore >= $match->games_to_win && bScore - aScore >= 2`: B wins.
- Else: ongoing.

### Server rotation

Non-TB: alternate per game complete → `server_side = server_side === 1 ? 2 : 1`.
TB: per design formula (lines 244-250 in `umpire-app.html`):
```php
function tbServerRotation(int $totalPoints, int $firstSrv): int
{
    $t = $totalPoints - 1;
    if ($t === 0) return $firstSrv;
    $alt = $firstSrv === 1 ? 2 : 1;
    if ($t === 1) return $alt;
    $grp = intdiv($t - 1, 2);
    return $grp % 2 === 0 ? $alt : $firstSrv;
}
```
Pattern: 1, 2, 2, 1, 1, 2, 2, 1, 1, 2, 2, 1, ...

## Routes (`routes/admin.php` append)

```php
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // ... existing routes ...
    
    Route::prefix('matches')->name('matches.')->group(function () {
        Route::get('/', [MatchScoreController::class, 'index'])->name('index');
        Route::get('/create', [MatchScoreController::class, 'create'])->name('create');
        Route::post('/', [MatchScoreController::class, 'store'])->name('store');
        Route::get('/{match}', [MatchScoreController::class, 'show'])->name('show');
        Route::post('/{match}/points', [MatchScoreController::class, 'addPoint'])->name('points.store');
        Route::post('/{match}/undo', [MatchScoreController::class, 'undoPoint'])->name('points.undo');
        Route::post('/{match}/resume', [MatchScoreController::class, 'resume'])->name('resume');
        Route::post('/{match}/cancel', [MatchScoreController::class, 'cancel'])->name('cancel');
        Route::delete('/{match}', [MatchScoreController::class, 'destroy'])->name('destroy');
    });
});
```

## Controller: `app/Http/Controllers/Admin/MatchScoreController.php`

Inject `MatchScoringService`. Methods mirror route names. Inertia responses only — no JSON API.

### `index(Request)`
List all matches with tournament, eager-load sets count. Paginated. Render `Admin/Matches/Index`.

### `create(Request)`
Render `Admin/Matches/Create` with tournaments list + 5 preset metadata.

### `store(MatchCreateRequest)`
Validate via FormRequest (sets_to_win, games_to_win, extend_at_tie, tiebreak_to, side_a_name, side_b_name, first_server_side, shot_recording, event_recording, tournament_id, division_id, location). Call service. Flash toast. Redirect to `admin.matches.show`.

### `show(Request, TennisMatch $match)`
Load match with `sets.games.points`. Build `matchShape()` array. Render `Admin/Matches/Score`.

### `addPoint(Request, TennisMatch $match)`
Validate `side ∈ {1,2}`, `shot_type ∈ {ace,winner,unforced_error,forced_error}|null`. Call service. On success: redirect back with success toast. On exception: flash error, redirect back.

### `undoPoint(Request, TennisMatch $match)`
Call service. Flash toast. Redirect back.

### `resume(Request, TennisMatch $match)`, `cancel(Request, TennisMatch $match)`, `destroy(Request, TennisMatch $match)`
Call service. Flash toast. Redirect to `admin.matches.index`.

### `matchShape(TennisMatch $match): array`

```php
return [
    'id' => $match->id,
    'side_a_name' => $match->side_a_name,
    'side_b_name' => $match->side_b_name,
    'format' => [
        'sets_to_win' => $match->sets_to_win,
        'games_to_win' => $match->games_to_win,
        'extend_at_tie' => $match->extend_at_tie,
        'tiebreak_to' => $match->tiebreak_to,
    ],
    'status' => $match->status,
    'server_side' => $match->server_side,
    'sets_won' => ['a' => ..., 'b' => ...],
    'shot_recording' => $match->shot_recording,
    'event_recording' => $match->event_recording,
    'tournament' => ['id' => ..., 'name' => ...],
    'division' => ['id' => ..., 'name' => ...] ?? null,
    'sets' => $match->sets->map(fn($s) => [
        'id', 'number', 'side_a_score', 'side_b_score',
        'status', 'winner_side', 'is_tiebreak',
        'games' => $s->games->map(fn($g) => [
            'id', 'number', 'side_a_score', 'side_b_score',
            'status', 'winner_side', 'is_tiebreak',
            'points' => $g->points->map(fn($p) => [
                'id', 'number', 'winner_side', 'shot_type',
            ]),
        ]),
    ])->all(),
];
```

## Form Request: `app/Http/Requests/Admin/MatchCreateRequest.php`

```php
public function rules(): array
{
    return [
        'tournament_id' => ['required', 'exists:tournaments,id'],
        'division_id' => ['nullable', 'exists:divisions,id'],
        'side_a_name' => ['required', 'string', 'max:255'],
        'side_b_name' => ['required', 'string', 'max:255'],
        'sets_to_win' => ['required', 'integer', 'in:1,2,3'],
        'games_to_win' => ['required', 'integer', 'min:1', 'max:12'],
        'extend_at_tie' => ['required', 'boolean'],
        'tiebreak_to' => ['required', 'integer', 'min:5', 'max:20'],
        'first_server_side' => ['required', 'integer', 'in:1,2'],
        'shot_recording' => ['required', 'boolean'],
        'event_recording' => ['required', 'boolean'],
        'location' => ['nullable', 'string', 'max:255'],
    ];
}
```

## Frontend

### `resources/js/lib/tennis-score.ts`

```ts
export function displayPointScore(a: number, b: number, isTB: boolean): [string, string]
export function computeStatusText(state: MatchState, opts: StatusOpts): { text: string; class: string }
export function tbServerRotation(totalPoints: number, firstSrv: 1 | 2): 1 | 2
export function formatSetScore(set: SetShape, tiebreakTo: number): string
export function formatMatchScore(sets: SetShape[]): string
```

Port directly from `umpire-app.html` lines 200-207, 244-250, 310-338.

### `pages/Admin/Matches/Index.svelte`

Table: ID, Tournament, P1 vs P2, Format badge, Status badge, Score summary, "Score" link. Header with "New match" coral button → `/admin/matches/create`.

### `pages/Admin/Matches/Create.svelte`

Sections:
1. Players: 2 name inputs
2. Match Format: 5 preset chips (Best of 3 / Best of 5 / First to 6 / First to 8 / First to 8 (TB))
3. Custom: 4 inputs (Sets to win, Games per set, Extend at tie toggle, Tiebreak to)
4. First server: 2 large serve buttons (P1 coral / P2 teal)
5. Toggles: Shot recording, Event recording
6. Location: optional text input
7. Tournament: select (required)
8. Division: select (optional)
9. "Start match" coral primary button (full width)

Preset selection auto-fills 4 format inputs via `$effect`. Toggling custom inputs updates which preset is "selected".

### `pages/Admin/Matches/Score.svelte`

Layout: mobile-first single column (`max-w-md mx-auto`), works on desktop admin too.

**Sub-view: running (match.status === 'running')**:
- Status bar (dynamic bg + text from `computeStatusText`)
- 2 player rows w/ color dot (P1 coral, P2 teal), name, completed set scores, current game score (`displayPointScore`)
- "Undo" + "X serving" row
- If `shot_recording`: 4 chip buttons (Ace, Winner, Unforced error, Forced error), tap to set `pendingShot`
- If `event_recording`: 3 chip buttons (Fault, Double fault, Let), tap to set `fault`/`let`/`double fault`
- "Point won by" — 2 large buttons w/ P1/P2 name + colored dot. Click → addPoint with `pendingShot`.

**Sub-view: completed (match.status === 'completed')**:
- Trophy + "Match Winner" label
- Winner name in their color
- Set list (each set: P1 score — P2 score, winner highlighted)
- "New match" button → `/admin/matches/create`
- "Resume scoring" button → resumeMatch

**Sub-view: canceled (match.status === 'canceled')**:
- Simple "Match canceled" + "New match" button

Local `$state`: `pendingShot: string | null`, `fault: boolean`, `letState: boolean`. All transient, lost on refresh.

### `resources/css/app.css` modification

Add after line 19:
```css
--color-teal: oklch(57.7% 0.12 175);
--color-teal-foreground: oklch(100% 0 0);
```
Brand exception: 2-entity color-coding for player distinction. Coral = P1, teal = P2.

### `resources/js/components/AdminSidebar.svelte` modification

Add new `SidebarMenuItem` between Tournaments and System Settings:
```svelte
<SidebarMenuLink href={matchesIndex.url()} label="Matches" icon={Swords} />
```
Import `Swords` from `lucide-svelte/icons/swords`.

## Factories

### `TennisMatchFactory`
```php
public function definition(): array
{
    return [
        'tournament_id' => Tournament::factory(),
        'division_id' => null,
        'umpire_user_id' => null,
        'next_match_id' => null,
        'number' => 1,
        'side_a_name' => fake()->name(),
        'side_b_name' => fake()->name(),
        'sets_to_win' => 2,
        'games_to_win' => 6,
        'extend_at_tie' => false,
        'tiebreak_to' => 7,
        'first_server_side' => 1,
        'server_side' => 1,
        'status' => 'running',
        'location' => null,
        'shot_recording' => true,
        'event_recording' => true,
        'started_at' => now(),
        'ended_at' => null,
    ];
}

public function running(): self
public function completed(): self
public function forTournament(Tournament $t): self
public function forDivision(Division $d): self
```

### `MatchSetFactory`, `GameFactory`, `PointFactory`

Minimal definitions creating rows attached to existing parents via state methods.

## Seeder: `database/seeders/MatchScoringDemoSeeder.php`

Creates 1 Tournament (uses `Tournament::factory()->open()`), 1 Division (singles), 2 TennisMatches:
- Match 1: locked-ish (status=running, no points) — fresh
- Match 2: in-progress (set 1 ongoing, game 1, aScore=2, bScore=1) — live test data

Call from `DatabaseSeeder`.

## Tests: `tests/Feature/Admin/MatchScoringTest.php`

Pest feature tests, `RefreshDatabase`, acting as admin user (with `super-admin` role).

### Cases
- `it_lists_matches_on_index_page`
- `it_shows_create_form_with_presets`
- `it_creates_match_with_initial_set_and_game`
- `it_validates_match_creation`
- `it_adds_point_and_updates_game_score`
- `it_persists_shot_type_on_point`
- `it_completes_game_at_4_points_with_2_margin`
- `it_completes_set_at_6_games_with_2_margin`
- `it_triggers_tiebreak_at_games_to_win_each_when_extend_false`
- `it_does_not_trigger_tiebreak_when_extend_true`
- `it_triggers_tiebreak_at_games_to_win_each_for_games_to_win_8`
- `it_completes_tiebreak_at_tiebreak_to_with_2_margin`
- `it_extends_tiebreak_past_tiebreak_to_when_tied`
- `it_completes_match_when_sets_won_reaches_sets_to_win`
- `it_does_not_complete_match_before_threshold`
- `it_rotates_server_after_each_game`
- `it_rotates_server_in_tiebreak_per_two_points`
- `it_undoes_last_point_and_restores_state`
- `it_resumes_completed_match`
- `it_cancels_match`
- `it_blocks_non_admin_users`

Run: `php artisan test --compact --filter=MatchScoring`

## Verification Steps

1. `php artisan migrate` — fresh migrations apply clean
2. `php artisan test --compact --filter=MatchScoring` — all green
3. `vendor/bin/pint --dirty --format agent` — PHP style
4. `npm run lint && npm run types:check` — frontend
5. `php artisan wayfinder:generate` (or `npm run dev`) — route helpers regenerated
6. `php artisan db:seed --class=MatchScoringDemoSeeder` — dummy data
7. Browser smoke: admin login → `/admin/matches` → "New match" → fill setup → "Start match" → land on score screen → tap points → reach match win → Over view

## File Inventory

### New
```
database/migrations/2026_06_29_000001_create_matches_table.php
database/migrations/2026_06_29_000002_create_match_sets_table.php
database/migrations/2026_06_29_000003_create_games_table.php
database/migrations/2026_06_29_000004_create_points_table.php
database/migrations/2026_06_29_000005_create_match_undo_snapshots_table.php
app/Models/TennisMatch.php
app/Models/MatchSet.php
app/Models/Game.php
app/Models/Point.php
app/Services/MatchScoringService.php
app/Http/Controllers/Admin/MatchScoreController.php
app/Http/Requests/Admin/MatchCreateRequest.php
database/factories/TennisMatchFactory.php
database/factories/MatchSetFactory.php
database/factories/GameFactory.php
database/factories/PointFactory.php
database/seeders/MatchScoringDemoSeeder.php
resources/js/pages/Admin/Matches/Index.svelte
resources/js/pages/Admin/Matches/Create.svelte
resources/js/pages/Admin/Matches/Score.svelte
resources/js/lib/tennis-score.ts
tests/Feature/Admin/MatchScoringTest.php
```

### Modified
```
routes/admin.php                              (append matches routes)
resources/css/app.css                         (add --color-teal)
resources/js/components/AdminSidebar.svelte   (add Matches nav)
database/seeders/DatabaseSeeder.php           (call MatchScoringDemoSeeder)
docs/plan/README.md                           (add Phase 7 row)
```

## Decisions

- **Model class `TennisMatch`** not `Match` — PHP reserved word. Table stays `matches`.
- **DB `enum()` columns** not PHP enum classes — matches repo convention.
- **No Stage/Group/Round** — skip bracket logic for MVP. `next_match_id` kept for future knockout support.
- **Side = name strings** — no participant FK. Migration path: add `side_a_registration_id` nullable later.
- **Admin gate only** — no umpire role/policy yet. MVP.
- **No server tracking on Point** — server is `match.server_side` (current), rotated by service on game complete + per TB point.
- **Auto-create set/game on first point** — umpire never manually adds sets/games. All progression automatic.
- **Undo via snapshots table** — cleanest reversible mutation. Simpler than `Point.metadata` JSON.
- **No `MatchResult`/`MatchOpponent`/`GameResult`** — Go's deprecated entities skipped.
- **Best-of-N + 2-margin + flexible TB** — fully expressed via 4 input fields (`sets_to_win`, `games_to_win`, `extend_at_tie`, `tiebreak_to`).
- **TB at 6-6 or 7-7** — depends on `games_to_win` (6 vs 8) and `extend_at_tie`. Auto-detected by service.
- **No "PointType" enum (Ace/Winner/UE/FE)** beyond `shot_type` column — Go had this commented out. Design wants it. Added.

## Out of Scope (deferred)

- Real umpire role + MatchPolicy + AccessControl rows
- Stage/Group/Round hierarchy + bracket generation
- Side entity + polymorphic participant (PlayerProfile/DoublesPair/Team)
- Public scoreboard view
- Court assignment
- Match scheduling, time slots
- Bracket auto-progression (winner advances to next match)
- Server-side tournament structure (rounds/stages)
- American/rotating doubles format
- Real-time WebSocket updates (admin refreshes on action)
- Score history timeline UI
- Match notes/incident log
- Photo/video attachments
- Score export/print
