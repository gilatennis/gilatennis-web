# Gilatennis

Tournament platform for Gilatennis — public site, tournament registration, bracket/draw management, admin tooling, and live scoring.

## Stack

- **Backend**: PHP 8.4, Laravel 13, MySQL
- **Frontend**: Svelte 5, Inertia v3, Tailwind v4
- **Testing**: Pest (backend), Playwright (browser end-to-end)

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run the full dev stack (server, queue, Vite, logs) with:

```bash
composer run dev
```

## Testing

Backend tests use Pest:

```bash
php artisan test --compact              # whole suite
php artisan test --compact tests/Feature
php artisan test --compact --filter=SomeTest
```

## End-to-end tests (Playwright)

Browser-level tests that drive the real app against a seeded database. They live in `e2e/` and are the complement to the Pest tests.

### Prerequisites

- **MySQL** reachable at `127.0.0.1:3306` as `root` with no password (override with `E2E_DB_HOST` / `E2E_DB_PORT` / `E2E_DB_USER` / `E2E_DB_DATABASE`).
- **Google Chrome** installed — the suite uses the system browser (`channel: "chrome"`), so no `playwright install` step is needed.
- Dependencies installed (`npm install`).

`.env.e2e` is created automatically from `.env.e2e.example` on the first run.

### Running the suite

```bash
npm run e2e                                   # whole suite (headless)
npm run e2e:ui                                # Playwright UI mode — inspect live
npx playwright test --headed                  # watch it run in a real window
npx playwright test --debug                   # step through with the inspector
npx playwright test e2e/live-scoring.spec.ts  # one file
npx playwright test -g "spectator"            # by test title
```

`global-setup.ts` drops and reseeds a dedicated, destructive MySQL database (`gilatennis_e2e`) before the suite and rebuilds assets, so a run is a clean slate every time. See [`docs/E2E.md`](docs/E2E.md) for the fixture, helpers, and failure artifacts.

### Available tests (19)

**`e2e/live-scoring.spec.ts`** (2)

- a spectator sees a point appear on the live board
- a match appears on the live board once the admin starts it

**`e2e/live-board.spec.ts`** (10 — `live board organisation`)

- lists a tab per division plus All
- filters to a single division when its tab is selected
- groups results by stage, group and round
- orders recent results newest first
- caps results per bucket at three
- shows slot-less matches under a Matches bucket
- nests team rubbers under their tie
- renders slotted singles matches without an empty VS header
- shows every division on court now under All, then filters by tab
- updates a board score as the admin records a point

**`e2e/admin-registrations.spec.ts`** (7)

- admin registers a team and manages it end to end
- admin registers a single as pending
- admin overrides a registration payment from the slide-over
- admin registers a doubles pair
- admin sees a validation error for an undersized team
- editing a registration placed in a live bracket is blocked
- admin withdraws a pre-draw entrant from its details page
