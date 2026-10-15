import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { loginAdmin } from './helpers/auth';
import type { MatchFormat } from './helpers/scoreboard';
import { createMatch, scorePoints, startMatch } from './helpers/scoreboard';

/*
 * Match engine rules end-to-end. Each test creates its own match through the
 * real admin New Match form and scores it on the admin scoreboard, so the rules
 * are proved through the actual endpoints and the persisted projection — not the
 * engine in isolation (that is tests/Unit/TennisScoringEngineTest.php).
 *
 * The unit + parity suites own the exhaustive rule matrix; this spec guards the
 * wiring: the format entered in the form reaches the scoreboard, drives the
 * scoring, and renders the expected banner/sets/winner.
 *
 * Tiny formats keep the rule boundaries within a handful of clicks: a 2-game set
 * tiebreaks at 1-1 (or 2-2 when extending), so no test needs 40 points.
 */

const A = 'Engine A';
const B = 'Engine B';

const base = {
    a: A,
    b: B,
    setsToWin: 1,
    gamesToWin: 2,
    tiebreakTo: 3,
    extendAtTie: false,
    extendTiebreak: false,
    suddenDeath: false,
} satisfies MatchFormat;

/** Create the match from the form and start it (picks A to serve first). */
async function startNewMatch(page: Page, format: MatchFormat): Promise<void> {
    await createMatch(page, format);
    await startMatch(page);
}

const tiebreakBanner = (page: Page) => page.getByText('Tiebreak', { exact: true });
const deuceBanner = (page: Page) => page.getByText('Deuce', { exact: true });
const decidingBanner = (page: Page) =>
    page.getByText('Deciding point', { exact: true });
const winner = (page: Page) => page.getByTestId('match-winner');
const pointA = (page: Page) => page.getByTestId('point-a');

test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
});

test('opens the tiebreak one game before the target and decides it without a two-point margin', async ({
    page,
}) => {
    await startNewMatch(page, base);

    await expect(page.getByText('Best of 1 set(s), 2 games, TB to 3')).toBeVisible();

    // A wins game one → 1-0; no tiebreak yet.
    await scorePoints(page, 'a', 4);
    await expect(tiebreakBanner(page)).toHaveCount(0);

    // B wins game two → 1-1, which is tiebreakAt(2, false) → tiebreak opens.
    await scorePoints(page, 'b', 4);
    await expect(tiebreakBanner(page)).toBeVisible();

    // Sudden-death tiebreak (extend_tiebreak off) to 3: A wins on its 3rd point.
    await scorePoints(page, 'a', 1);
    await scorePoints(page, 'b', 1);
    await scorePoints(page, 'a', 1);
    await scorePoints(page, 'b', 1);
    await scorePoints(page, 'a', 1);

    await expect(winner(page)).toContainText(A);
    await expect(page.getByTestId('final-set-0-a')).toHaveText('2');
    await expect(page.getByTestId('final-set-0-b')).toHaveText('1');
});

test('defers the tiebreak to the target when extending and needs a two-point margin to win it', async ({
    page,
}) => {
    await startNewMatch(page, {
        ...base,
        extendAtTie: true,
        extendTiebreak: true,
    });

    // 1-1 stays in games when extending (tiebreakAt(2, true) → 2).
    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'b', 4);
    await expect(tiebreakBanner(page)).toHaveCount(0);

    // 2-2 opens the tiebreak.
    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'b', 4);
    await expect(tiebreakBanner(page)).toBeVisible();

    // Win-by-two to 3: 3-2 is not enough …
    await scorePoints(page, 'a', 1);
    await scorePoints(page, 'b', 1);
    await scorePoints(page, 'a', 1);
    await scorePoints(page, 'b', 1);
    await scorePoints(page, 'a', 1);
    await expect(winner(page)).toHaveCount(0);

    // … 3-3, 4-3 still not, and 5-3 decides.
    await scorePoints(page, 'b', 1);
    await scorePoints(page, 'a', 1);
    await expect(winner(page)).toHaveCount(0);
    await scorePoints(page, 'a', 1);

    await expect(winner(page)).toContainText(A);
    await expect(page.getByTestId('final-set-0-a')).toHaveText('3');
    await expect(page.getByTestId('final-set-0-b')).toHaveText('2');
});

test('a best-of-three match is decided only after two sets', async ({ page }) => {
    await startNewMatch(page, { ...base, setsToWin: 2 });

    // First set 2-0 — not decided yet.
    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'a', 4);
    await expect(winner(page)).toHaveCount(0);

    // Second set 2-0 — match won.
    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'a', 4);

    await expect(winner(page)).toContainText(A);
    await expect(page.getByTestId('final-set-0-a')).toHaveText('2');
    await expect(page.getByTestId('final-set-1-a')).toHaveText('2');
});

test('alternates the server every game', async ({ page }) => {
    await startNewMatch(page, { ...base, setsToWin: 2, gamesToWin: 6, tiebreakTo: 7 });

    const serving = (side: '1' | '2') =>
        page.locator(`[data-testid="serving-dot"][data-side="${side}"]`);

    // A serves first.
    await expect(serving('1')).toBeVisible();
    await expect(serving('2')).toHaveCount(0);

    // A wins game one → the serve hands over to B.
    await scorePoints(page, 'a', 4);
    await expect(serving('2')).toBeVisible();
    await expect(serving('1')).toHaveCount(0);

    // B wins game two → back to A.
    await scorePoints(page, 'b', 4);
    await expect(serving('1')).toBeVisible();
    await expect(serving('2')).toHaveCount(0);
});

test('undo restores a match that has just been decided', async ({ page }) => {
    await startNewMatch(page, base);

    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'a', 4);

    await expect(winner(page)).toBeVisible();
    await expect(pointA(page)).toBeDisabled();

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/undo') &&
                response.request().method() === 'POST' &&
                response.ok(),
        ),
        page.getByTestId('undo').click(),
    ]);

    await expect(winner(page)).toHaveCount(0);
    await expect(pointA(page)).toBeEnabled();
});

test('marks a decided match completed and reopens it', async ({ page }) => {
    await startNewMatch(page, base);

    await scorePoints(page, 'a', 4);
    await scorePoints(page, 'a', 4);
    await expect(winner(page)).toBeVisible();

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/status') &&
                response.request().method() === 'POST' &&
                response.ok(),
        ),
        page.getByTestId('mark-completed').click(),
    ]);

    await expect(page.getByTestId('reopen-match')).toBeVisible();

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/status') &&
                response.request().method() === 'POST' &&
                response.ok(),
        ),
        page.getByTestId('reopen-match').click(),
    ]);

    // Reopening keeps the recorded winner, so the match is decided again and can
    // be completed (or undone) rather than returned to the serving prompt.
    await expect(winner(page)).toBeVisible();
    await expect(page.getByTestId('mark-completed')).toBeVisible();
    await expect(page.getByTestId('reopen-match')).toHaveCount(0);
});

test('a sudden-death game ends at 4-3 with no advantage', async ({ page }) => {
    await startNewMatch(page, {
        ...base,
        setsToWin: 2,
        gamesToWin: 6,
        tiebreakTo: 7,
        suddenDeath: true,
    });

    // 3-3 is a deciding point, not a deuce.
    await scorePoints(page, 'a', 3);
    await scorePoints(page, 'b', 3);
    await expect(decidingBanner(page)).toBeVisible();

    // The next point wins the game 4-3 — the deciding banner clears.
    await scorePoints(page, 'a', 1);
    await expect(decidingBanner(page)).toHaveCount(0);
    await expect(winner(page)).toHaveCount(0);
});

test('a deuce game offers advantage instead of deciding at 4-3', async ({
    page,
}) => {
    await startNewMatch(page, {
        ...base,
        setsToWin: 2,
        gamesToWin: 6,
        tiebreakTo: 7,
        suddenDeath: false,
    });

    await scorePoints(page, 'a', 3);
    await scorePoints(page, 'b', 3);
    await expect(deuceBanner(page)).toBeVisible();

    await scorePoints(page, 'a', 1);
    await expect(page.getByText(/Advantage · /)).toBeVisible();
    await expect(winner(page)).toHaveCount(0);
});
