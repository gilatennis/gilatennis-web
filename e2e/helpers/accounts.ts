/*
 * Fixed fixture values shared by the Playwright specs. Mirrored in
 * database/seeders/E2ESeeder.php and .env.e2e — keep in sync.
 */

export const E2E_ADMIN_EMAIL =
    process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
export const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? 'password';

export const E2E_TOURNAMENT_SLUG = 'e2e-live-open';

export const E2E_MATCHES = {
    pending: 101,
    live: 102,
    completed: 103,
} as const;

/** The multi-stage, multi-division fixture used by the live-board spec. */
export const E2E_BOARD_SLUG = 'e2e-board';

export const E2E_BOARD_MATCHES = {
    groupALive: 205,
    semiCompleted: 207,
    finalLive: 208,
    adhoc: 209,
    doublesFinal: 210,
    teamRubberCompleted: 211,
    teamRubberLive: 212,
} as const;
