# Group Stage (Round-Robin) — Working Plan

Status: **complete**. Phases 1–8 committed. `composer ci:check` green (504 tests).

## Locked decisions

- Group count is admin-chosen at generate time.
- `advance_count` (winners per group) is configurable per group.
- Group assignment is auto (snake) or manual, mirroring `seeding_mode`.
- Matchdays are first-class: `bracket_slots.round` = matchday number, `position` = fixture within matchday.
- Double round-robin supported: globally at generate time, overridable per group.
- `bracket_slots.leg` marks the meeting (1 = first, 2 = return, sides swapped).
- Standings tie-break: wins → set difference → game difference → head-to-head → name.
- Group winner(s) are picked manually; the engine computes and shows standings.
- Knockout handoff: second bracket in the same division (`type = single_elimination`), seeded from picked winners. Non-power-of-two qualifier counts are allowed with a **bye warning + confirm** (option B).
- `seedPairsApart = true` on promotion (group-mates split into opposite halves).
- A group's membership, round-robin length, and fixtures are **locked once the group has started** (any tie decided or any match created).

## Committed phases

| Phase | Commit | Summary |
|---|---|---|
| 1 | `4fbc9d4` | `bracket_groups` table; `bracket_slots.bracket_group_id` + `leg` + `bracket_scope`; relaxed `brackets` unique to `(division_id, type)`; `BracketGroup` model/factory; relations + `TYPE_GROUP_STAGE` |
| 2 | `5f21953` | `RoundRobinScheduler` (circle method, single/double) + unit tests |
| 3 | `22f0aa8` | `BracketService::generateGroups` (snake distribution, fixtures) + endpoint + tests |
| 4 | `18d9c88` | `BracketService::standings` + tests |
| 5 | `85e69af` | Group membership pivot (`bracket_group_entrants`) as source of truth; `addEntrant`/`moveEntrant`/`setGroupDoubleRoundRobin`; endpoints + routes; `groupOrFail`/`bracketOrFail`/`slotOrFail` fixes; tests |
| 6 | pending | `bracket_groups.winner_entrant_ids`; `setGroupWinners` + `promoteToKnockout`; endpoints + requests; bye warning; tests |
| 7 | pending | `ShapesBracket` branch; `Division` bracket relations; TS types; `GroupStageView`/`GroupCard`/`StandingsTable`/dialogs; browser tests |
| 8 | pending | End-to-end lifecycle (feature) + full UI journey (browser); `composer ci:check` |

Full suite after Phase 3: 468 passed. Phase 4 added 5 green tests. Full suite after Phase 5: 485 passed. Full suite after Phase 6: 496 passed.

## Key schema notes

- `brackets` unique is `(division_id, type)` — one group-stage and one knockout per division.
- `bracket_slots` unique is `(bracket_id, bracket_scope, round, position)`. `bracket_scope` is a real column: `0` for tree slots, the group id for group fixtures (keeps matchdays restarting at round 1 per group while preserving DB enforcement).
- `bracket_group_entrants` (unique `(bracket_group_id, tournament_registration_id)`, `position`) is the **source of truth** for group membership. Fixtures in `bracket_slots` are derived from it and rebuilt on every change, so a group can hold 1 member (or 0) with no fixtures. Group deletion cascades to its membership rows.
- MySQL caveat: dropping a unique that backs a FK requires dropping the FK first (done in migrations).

## Phase 5 — group membership management (DONE `85e69af`)

Originally planned to derive membership from fixtures; that loses a lone entrant when a
manual group is bootstrapped. Switched to a membership pivot (option 2):

- `bracket_group_entrants` migration + `BracketGroupEntrant` model/factory.
- `BracketGroup::entrants()` + `entrantIds()` now read the pivot (ordered by `position`).
- `BracketService`: `placeEntrants()` writes membership then rebuilds fixtures
  (`buildGroupFixtures(BracketGroup)` reads membership); `addEntrant` rejects an entrant
  already placed anywhere in the stage; `moveEntrant` moves the pivot row and rebuilds
  both groups; `setGroupDoubleRoundRobin` rebuilds from membership. `entrantsById` removed.
- `standings()` seeds rows from membership so unplayed members still appear.
- `groupHasStarted`/`assertGroupEditable` unchanged (fixtures/winner/match based) — edits
  are blocked once a group has started.
- Controller: `groupOrFail` resolves the group-stage bracket explicitly; `bracketOrFail`
  resolves the knockout bracket explicitly; `slotOrFail` resolves a slot across **all**
  brackets in the division (`whereIn bracket_id`) so group ties are decided through the
  shared slot endpoints.
- Routes (`scopeBindings`): `POST .../groups/{group}/entrants`,
  `POST .../groups/{group}/entrants/move`, `PATCH .../groups/{group}/settings`.
- Tests: add/move/toggle + blocked-once-started + endpoint coverage + shared-slot decision.

Deferred: `destroy()` still uses the ambiguous `$division->bracket`; `syncStatus` can mark
a group bracket `completed` when the graph's `final` slot is decided (Phase 6 concern).

## Phase 6 — winners + promotion (DONE)

Winners are the **picked qualifiers**, stored on the group (not derived from standings):

- Migration: `bracket_groups.winner_entrant_ids` (JSON, nullable) + `BracketGroup::winnerIds()`/cast.
- `BracketService::setGroupWinners(BracketGroup, array $winnerIds)` — validates the picks are
  distinct group members and exactly `advance_count` of them; stores them in seed order.
- `BracketService::promoteToKnockout(Division, array $winnerIdsInSeedOrder, bool $seedPairsApart = true)`
  — requires every group to have recorded its winners; when `seedPairsApart` the seed order is
  taken from the group picks (top seeds split across halves, so group-mates meet late), otherwise
  the caller's order is honoured. Delegates to a new `writeSingleElimination()` helper (also used
  by `generate()`), so the knockout is created/regenerated with a fresh slot tree.
- Endpoints: `POST .../groups/{group}/winners` (validate `winner_ids`), `POST .../bracket/promote`
  (`winner_ids`, optional `seed_pairs_apart`). Promotion emits a **warning** toast when the field
  is not a power of two (byes), else a success toast.
- Requests: `BracketGroupWinnerRequest`, `BracketPromoteRequest`.
- Tests: `tests/Feature/Admin/BracketGroupPromotionTest.php` (11 tests) — record/reject picks,
  seed order, group-mate split, bye field, endpoint coverage.

Still open: `syncStatus` can flip a group bracket `completed` (Phase 7 concern); `destroy()`
still uses the ambiguous `$division->bracket`.

## Phase 7 — shaping + frontend (DONE)

- `ShapesBracket` branches on `bracket.type`: `shapeKnockout()` (rounds) and
  `shapeGroupStage()`/`shapeGroup()` (groups → `matchdays[]` + `standings[]` + `entrants[]` +
  `winner_entrant_ids`).
- `Division` model: `bracket()` is now the knockout (type-filtered), `groupStage()` the
  group stage, plus `brackets()`; both are loaded and shaped on the division page
  (`bracket` + `groupStage` props). `destroy()` takes `?type=` (default knockout).
- TS types (`types/bracket.ts`): `BracketType` gains `'group_stage'`; new `BracketGroup`,
  `GroupMatchday`, `GroupStanding`, `KnockoutBracket`, `GroupStageBracket`; `Bracket` carries
  both `rounds` and `groups`.
- Svelte: `GroupStageView` (generate/promote/delete + entrant management wiring +
  shared advance/undo/rubbers flow), `GroupCard`, `StandingsTable` (with winner picking),
  `GenerateGroupsDialog`, `PromoteWinnersDialog` (bye warning). `Division.svelte` renders the
  group stage above the knockout on the Matches tab.
- Tests: group-stage payload shaping + type-scoped delete (feature);
  `tests/Browser/Admin/GroupStageTest.php` (generate via UI, promote, locked assignment).

Full suite after Phase 7: 502 passed (includes browser).

## Phase 8 — e2e + browser (DONE)

- Shared e2e helpers live in `tests/Feature/EndToEnd/EndToEndHelpers.php` (global functions,
  loaded from `tests/Pest.php`): `e2eAdmin`, `e2eTournament`, `e2eFakeInvoiceService`,
  `e2eRegister`, `e2ePlaySlot`, `e2eAdvance`, `e2ePlayAndAdvance`. Both lifecycle test files
  use them, so the group and knockout e2e no longer duplicate helpers.
- `tests/Feature/EndToEnd/GroupStageLifecycleTest.php` covers three shapes end to end:
  - **4 paid, 2 groups × 2, top 1** — the minimal promotion path (single Final).
  - **8 paid, 2 groups × 4, top 2** — a full 12-tie group stage, standings ranked 1–4, four
    qualifiers promoted without byes, group-mates seeded into opposite halves, then a
    three-match knockout to a champion.
  - **6 paid, 3 groups × 2, top 1** — three qualifiers into a four-draw with a single bye; the
    bye entrant is pre-resolved into a semifinal, the one live tie is played, then the final.
- `tests/Browser/Admin/GroupStageTest.php` additionally drives the bye path through the UI:
  6 paid, bump to 3 groups via `@groups-inc`, set per-group advance counts, pick winners,
  assert the `@bye-warning` in the promote dialog, then the `Semifinal` round renders with the
  bye tie resolved (no advance controls) and the live tie playable.
- Tie-break ordering (wins → set diff → game diff → head-to-head → name) is owned by
  `tests/Feature/Admin/BracketGroupStandingsTest.php`; the lifecycle e2e asserts only the wins
  cohort and that `standings()` order drives the picks.
- `composer ci:check` green: eslint, prettier, svelte-check, pint, full suite.

## Commands

- Tests: `php artisan test --compact <file>`
- Full gate: `composer ci:check`
- Format PHP: `vendor/bin/pint --dirty --format agent`
- Wayfinder: `php artisan wayfinder:generate`
- Browser: `npm run build && php artisan test tests/Browser/...`

## Gotcha log

- Stale `bootstrap/cache` can make `runningUnitTests()` false → spurious 419 in feature tests. Fix: `php artisan optimize:clear`.
- Lazy loading is disabled globally in tests — eager-load relations in assertions.
- `standard()` test helpers and `@testid` selectors per project conventions.
