import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/*
 * Drives the admin scoreboard (resources/js/pages/Admin/TennisMatches/Score.svelte).
 * Every action goes through the real endpoints, so the score a spectator sees is
 * the one the server persisted.
 */

/** Pick the first server and start the match; only needed while pending. */
export async function startMatch(page: Page): Promise<void> {
    await page.getByTestId('server-pick-a').click();

    // Start, and wait for the persisted /server save to complete. The scoreboard
    // renders the point buttons optimistically, so DOM alone is not enough.
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/server') &&
                response.request().method() === 'POST' &&
                response.ok(),
        ),
        page.getByTestId('start-match').click(),
    ]);

    // The scoreboard no-ops point taps while a save is in flight (its `saving`
    // guard), so wait for the autosave indicator before touching the board.
    await expect(page.getByText(/Autosaved at/)).toBeVisible();
    await expect(page.getByTestId('point-a')).toBeEnabled();
}

/** Award a point to a side and wait for the persisted projection to return. */
export async function awardPoint(page: Page, side: 'a' | 'b'): Promise<void> {
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/points') &&
                response.request().method() === 'POST' &&
                response.ok(),
        ),
        page.getByTestId(`point-${side}`).click(),
    ]);
}
