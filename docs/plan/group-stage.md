# Group Stage (Round-Robin) — Working Plan

Status: **in progress**. Phases 1–5 committed. Next: Phase 6 (winners + promotion).

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

Full suite after Phase 3: 468 passed. Phase 4 added 5 green tests. Full suite after Phase 5: 485 passed.

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

## Phase 6 — winners + promotion

- `BracketService::setGroupWinners(BracketGroup, array $winnerIds)` (thin wrapper on `setWinner`; round-robin slots have no parents/children).
- `BracketService::promoteToKnockout(Division, array $winnerIdsInSeedOrder, bool $seedPairsApart = true)`; requires every group fully decided (advance_count winners each); creates/updates the `single_elimination` bracket via existing `generate()`; bye warning path.
- Endpoints: `POST .../groups/{group}/winners`, `POST .../bracket/promote`.
- Requests: `BracketGroupWinnerRequest`, `BracketPromoteRequest` (with division/type class docblocks).
- Tests.

## Phase 7 — shaping + frontend

- `ShapesBracket`: branch on `bracket.type`; `group_stage` → `groups[]` with `matchdays[]` + `standings[]`; `single_elimination` → existing `rounds[]`.
- Svelte: `GroupStageView`, `GroupCard`, `StandingsTable`, `GenerateGroupsDialog` (count, advance counts, single/double, auto/manual), `EntrantAssignmentPanel` (add/move; disabled when started), `GroupMatchdays`, `PromoteWinnersDialog` (bye warning).
- `BracketView` dispatches on `bracket.type`.
- TS types: `BracketGroup`, `GroupStanding`; extend `BracketType` with `'group_stage'`.
- `php artisan wayfinder:generate` after routes.

## Phase 8 — e2e + browser

- Feature e2e: paid entrants → groups → play matchdays → standings → pick winners → promote → knockout champion.
- Browser test: group→knockout through the UI (log in once; `npm run build` first).
- `composer ci:check`.

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
