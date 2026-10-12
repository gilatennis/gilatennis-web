import { defineConfig, devices } from '@playwright/test';

/*
 * Browser end-to-end suite for the whole app (docs/E2E.md).
 *
 * The fixture is a dedicated, destructive MySQL database (`gilatennis_e2e`)
 * reseeded by global-setup, so the suite runs single-worker: parallel workers
 * would wipe each other's data mid-test. Chrome is used through
 * `channel: "chrome"` rather than a downloaded Chromium build.
 */

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8010';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    reporter: [['list']],
    globalSetup: './e2e/global-setup.ts',
    use: {
        baseURL,
        channel: 'chrome',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        {
            name: 'chrome',
            use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        },
    ],
    webServer: {
        // `APP_ENV=e2e` makes the served process load `.env.e2e` (via Laravel's
        // environment detection); `--env=e2e` only tells `serve` which file to
        // watch for reloads.
        command:
            'APP_ENV=e2e php artisan serve --host=127.0.0.1 --port=8010 --env=e2e',
        url: `${baseURL}/up`,
        reuseExistingServer: true,
        timeout: 180_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
