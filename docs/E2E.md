# End-to-end testing (Playwright)

How to drive the whole app in a real browser against a seeded database. The
suite lives in `e2e/`, runs on Playwright, and is the browser-level complement
to the Pest tests (`tests/Feature`, `tests/Unit`, `tests/Browser`).

Everything here is **local-only** by design: the fixture uses a dedicated,
destructive MySQL database (`gilatennis_e2e`) that `global-setup.ts` drops and
reseeds on every run. It never touches the dev database (`gilatennis_web`) or
`DatabaseSeeder`.

## Why this is separate from `DatabaseSeeder` and Pest

- `DatabaseSeeder` is dev/production-safe. The E2E fixture (`E2ESeeder`) creates
  an admin it can log in as and matches it can score, and is re-seeded
  destructively — so it lives apart.
- `tests/Browser` (Pest + `pest-plugin-browser`) already drives the UI, but
  through Pest's runner. This suite is a standalone `@playwright/test` project,
  matching the `billiard-booking` setup, so it can grow into app-wide browser
  coverage.

## How it runs

`playwright.config.ts` sets `testDir: ./e2e`, pins the browser to system Google
Chrome (`channel: "chrome"` — no `playwright install`), and starts the app via
`webServer`:

```
APP_ENV=e2e php artisan serve --host=127.0.0.1 --port=8010 --env=e2e
```

`APP_ENV=e2e` is what makes the served process load `.env.e2e` (Laravel detects
`.env.{APP_ENV}`); `--env=e2e` only tells `serve` which file to watch for
reloads. The server listens on `8010` (not the dev `8000`) so it never collides
with `composer run dev`.

`global-setup.ts` runs once before the suite:

1. copies `.env.e2e.example` → `.env.e2e` if missing,
2. `CREATE DATABASE IF NOT EXISTS gilatennis_e2e` (via the `mysql` client),
3. `php artisan migrate:fresh --env=e2e --force`,
4. `php artisan db:seed --class=E2ESeeder --env=e2e --force`,
5. `npm run build` (the served app needs a Vite manifest).

It runs a single worker (`workers: 1`, `fullyParallel: false`) because every
spec shares one database.

## Commands

```bash
npm run e2e                                  # whole suite (headless)
npm run e2e:ui                               # Playwright UI mode — inspect live
npx playwright test --headed                 # watch it run in a real window
npx playwright test --debug                  # step through with the inspector
npx playwright test e2e/live-scoring.spec.ts # one file
npx playwright test -g "spectator"           # by test title
```

Because the browser is real Chrome, `--headed` and `--ui` let you watch and
inspect the UI as the tests run.

## Fixture

Defined in `database/seeders/E2ESeeder.php` and mirrored in
`e2e/helpers/accounts.ts` (keep in sync):

| Value                            | Meaning                                             |
| -------------------------------- | --------------------------------------------------- |
| `admin@example.com` / `password` | Admin login (verified, `admin` role)                |
| tournament `e2e-live-open`       | Open tournament shown on the public site            |
| match `101`                      | Pending — started from the scoreboard during a spec |
| match `102`                      | Running, first server set — scored immediately      |
| match `103`                      | Completed — appears under "Recent results"          |
| tournament `e2e-board`           | Multi-stage, multi-division live-board fixture      |

`e2e-board` carries three divisions so the board's organisation can be exercised:
`Board Singles` (group stage **Group A** / **Group B** + knockout **Semifinal** /
**Final**, plus a slot-less "Matches" match), `Board Doubles` (knockout only), and
`Board Team` (one tie with two rubber matches). Ids `200`–`212` are explicit and
mirrored in `E2E_BOARD_MATCHES`.

Match ids are explicit so specs can address them directly. A fresh
`migrate:fresh` resets them each run.

## Spec catalogue

| Spec                   | What it verifies                                                                                                                                            |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `live-scoring.spec.ts` | a spectator's public live board reflects a point the admin records (via Inertia polling), and a pending match appears on the board once the admin starts it |
| `live-board.spec.ts`   | division tabs, results grouped by stage/group/round, the per-bucket result cap, team rubbers nested under their tie, and slot-less matches under "Matches"   |

Helpers live in `e2e/helpers/`: `accounts.ts` (fixture values), `auth.ts`
(`loginAdmin` through the real form — no session cookie injection), and
`scoreboard.ts` (start a match, award a point through the real endpoints).

## Artifacts

A failing case writes a screenshot, trace, and DOM snapshot under
`test-results/` (gitignored). Open a trace with:

```bash
npx playwright show-trace test-results/<case>/trace.zip
```

## Scope

Today the suite covers public live scoring only. It is structured to grow into
whole-app browser coverage (auth, registration, admin screens) — add specs under
`e2e/` and fixture data to `E2ESeeder`. Not covered yet: payment settlement
(the Duitku hosted page), and the existing Pest browser journeys.
