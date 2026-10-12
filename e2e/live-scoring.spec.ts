import { expect, test } from '@playwright/test';

import { E2E_MATCHES, E2E_TOURNAMENT_SLUG } from './helpers/accounts';
import { loginAdmin } from './helpers/auth';
import { awardPoint, startMatch } from './helpers/scoreboard';

/*
 * Public live scoring end-to-end: a visitor's board must reflect points as the
 * admin records them. The spectator polls every 4s (Inertia usePoll), so each
 * assertion waits a couple of cycles.
 */

const liveBoard = (slug: string) => `/tournaments/${slug}/live`;

test('a spectator sees a point appear on the live board', async ({
    browser,
}) => {
    const spectator = await browser.newContext();
    const admin = await browser.newContext();
    const spectatorPage = await spectator.newPage();
    const adminPage = await admin.newPage();

    await spectatorPage.goto(liveBoard(E2E_TOURNAMENT_SLUG));

    const row = spectatorPage
        .getByTestId(/^live-match-/)
        .filter({ hasText: 'E2E Live A' });

    await expect(row).toBeVisible();

    // The e2e DB persists between runs, so don't assume a starting score —
    // capture it and assert the recorded point advances it.
    const points = row.getByTestId('live-points-a');
    const before = (await points.textContent())?.trim() ?? '';

    // The finished match shows under recent results on the same page.
    await expect(spectatorPage.getByText('Recent results')).toBeVisible();
    await expect(
        spectatorPage
            .getByTestId(/^live-match-/)
            .filter({ hasText: 'E2E Done A' }),
    ).toBeVisible();

    await loginAdmin(adminPage);
    await adminPage.goto(`/admin/tennis-matches/${E2E_MATCHES.live}`);
    await awardPoint(adminPage, 'a');

    await spectatorPage.bringToFront();
    await expect(points).not.toHaveText(before, { timeout: 30_000 });

    await spectator.close();
    await admin.close();
});

test('a match appears on the live board once the admin starts it', async ({
    browser,
}) => {
    const spectator = await browser.newContext();
    const admin = await browser.newContext();
    const spectatorPage = await spectator.newPage();
    const adminPage = await admin.newPage();

    const row = spectatorPage
        .getByTestId(/^live-match-/)
        .filter({ hasText: 'E2E Pending A' });

    await spectatorPage.goto(liveBoard(E2E_TOURNAMENT_SLUG));
    await expect(row).toHaveCount(0);

    await loginAdmin(adminPage);
    await adminPage.goto(`/admin/tennis-matches/${E2E_MATCHES.pending}`);
    await startMatch(adminPage);

    // The match appears on the board as soon as it starts (0 points) …
    await spectatorPage.bringToFront();
    await expect(row).toBeVisible({ timeout: 30_000 });

    const points = row.getByTestId('live-points-a');
    const before = (await points.textContent())?.trim() ?? '';

    // … and then advances as the admin records a point.
    await awardPoint(adminPage, 'a');

    await spectatorPage.bringToFront();
    await expect(points).not.toHaveText(before, { timeout: 30_000 });

    await spectator.close();
    await admin.close();
});
