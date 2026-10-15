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

/** Award `count` points to one side, awaiting each persisted projection. */
export async function scorePoints(
    page: Page,
    side: 'a' | 'b',
    count: number,
): Promise<void> {
    for (let i = 0; i < count; i++) {
        await awardPoint(page, side);
    }
}

/** The format knobs the New Match form exposes, used by the engine spec. */
export interface MatchFormat {
    a: string;
    b: string;
    setsToWin: number;
    gamesToWin: number;
    tiebreakTo: number;
    extendAtTie: boolean;
    extendTiebreak: boolean;
    suddenDeath: boolean;
}

/**
 * Create a standalone match through the real admin New Match form and return its
 * id. The form redirects to the show page, so the caller lands on the scoreboard.
 */
export async function createMatch(
    page: Page,
    format: MatchFormat,
): Promise<number> {
    await page.goto('/admin/tennis-matches/create');

    await page.locator('input[name="side_a_name"]').fill(format.a);
    await page.locator('input[name="side_b_name"]').fill(format.b);
    await page
        .locator('input[name="sets_to_win"]')
        .fill(String(format.setsToWin));
    await page
        .locator('input[name="games_to_win"]')
        .fill(String(format.gamesToWin));
    await page
        .locator('input[name="tiebreak_to"]')
        .fill(String(format.tiebreakTo));

    await setSwitch(page, 'extend-at-tie', format.extendAtTie);
    await setSwitch(page, 'extend-tiebreak', format.extendTiebreak);
    await setSwitch(page, 'sudden-death', format.suddenDeath);

    await page.getByRole('button', { name: 'Create Match' }).click();
    await page.waitForURL(/\/admin\/tennis-matches\/\d+$/);

    const match = /\/admin\/tennis-matches\/(\d+)$/.exec(page.url());

    if (!match) {
        throw new Error(`Could not read match id from ${page.url()}`);
    }

    return Number(match[1]);
}

async function setSwitch(
    page: Page,
    testId: string,
    value: boolean,
): Promise<void> {
    const toggle = page.getByTestId(testId);

    if ((await toggle.getAttribute('aria-checked')) !== String(value)) {
        await toggle.click();
    }
}
