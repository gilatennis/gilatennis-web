import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { E2E_BOARD_MATCHES, E2E_BOARD_SLUG } from './helpers/accounts';
import { loginAdmin } from './helpers/auth';
import { awardPoint } from './helpers/scoreboard';

/*
 * Organisation of the public live board when a tournament has several divisions
 * and both a group stage and a knockout: tabs, grouped results, per-bucket caps,
 * team rubbers nested under their tie, and slot-less matches under "Matches".
 */

const liveBoard = (slug: string) => `/tournaments/${slug}/live`;

const section = (page: Page, name: string) =>
    page.getByTestId(`division-section-${name}`);

test.describe('live board organisation', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(liveBoard(E2E_BOARD_SLUG));
        await expect(
            page.getByRole('heading', { name: 'E2E Board Open' }),
        ).toBeVisible();
    });

    test('lists a tab per division plus All', async ({ page }) => {
        await expect(page.getByTestId('division-tabs')).toBeVisible();
        await expect(page.getByTestId('division-tab-all')).toBeVisible();
        await expect(page.getByTestId('division-tab-singles')).toBeVisible();
        await expect(page.getByTestId('division-tab-doubles')).toBeVisible();
        await expect(page.getByTestId('division-tab-team')).toBeVisible();
    });

    test('filters to a single division when its tab is selected', async ({
        page,
    }) => {
        await page.getByTestId('division-tab-doubles').click();

        await expect(section(page, 'doubles')).toBeVisible();
        await expect(section(page, 'singles')).toHaveCount(0);
        await expect(section(page, 'team')).toHaveCount(0);

        // Doubles has no match on court, so the live section empties out.
        await expect(
            page.getByText('No matches are on court right now.'),
        ).toBeVisible();
    });

    test('groups results by stage, group and round', async ({ page }) => {
        const singles = section(page, 'singles');

        await expect(singles.getByTestId('stage-group-stage')).toBeVisible();
        await expect(singles.getByTestId('bucket-group-a')).toBeVisible();
        await expect(singles.getByTestId('bucket-group-b')).toBeVisible();
        await expect(singles.getByTestId('stage-knockout')).toBeVisible();
        await expect(singles.getByTestId('bucket-semifinal')).toBeVisible();
    });

    test('orders recent results newest first', async ({ page }) => {
        const singles = section(page, 'singles');

        const buckets = await singles
            .locator('[data-testid^="bucket-"]')
            .evaluateAll((els) =>
                els.map((el) => el.getAttribute('data-testid')),
            );

        // The semifinal finished most recently, so its bucket precedes the older
        // group-stage buckets.
        expect(buckets.indexOf('bucket-semifinal')).toBeLessThan(
            buckets.indexOf('bucket-group-a'),
        );
        expect(buckets.indexOf('bucket-semifinal')).toBeLessThan(
            buckets.indexOf('bucket-group-b'),
        );
    });

    test('caps results per bucket at three', async ({ page }) => {
        const singles = section(page, 'singles');

        await expect(
            singles.getByTestId('bucket-group-a').getByTestId(/^live-match-/),
        ).toHaveCount(3);
        await expect(
            singles.getByTestId('bucket-group-b').getByTestId(/^live-match-/),
        ).toHaveCount(1);
    });

    test('shows slot-less matches under a Matches bucket', async ({ page }) => {
        const matches = section(page, 'singles').getByTestId('bucket-matches');

        await expect(matches).toBeVisible();
        await expect(
            matches.getByTestId(`live-match-${E2E_BOARD_MATCHES.adhoc}`),
        ).toBeVisible();
    });

    test('nests team rubbers under their tie', async ({ page }) => {
        const tie = section(page, 'team').getByTestId(/^tie-/);

        await expect(tie).toBeVisible();
        await expect(tie).toContainText('Board Team Alpha vs Board Team Bravo');
        await expect(
            tie.getByTestId(
                `live-match-${E2E_BOARD_MATCHES.teamRubberCompleted}`,
            ),
        ).toBeVisible();
    });

    test('renders slotted singles matches without an empty VS header', async ({
        page,
    }) => {
        const singles = section(page, 'singles');

        await expect(
            singles.getByTestId(
                `live-match-${E2E_BOARD_MATCHES.semiCompleted}`,
            ),
        ).toBeVisible();
        await expect(singles.getByTestId(/^tie-/)).toHaveCount(0);
        await expect(singles.getByText('vs', { exact: true })).toHaveCount(0);

        await expect(section(page, 'team').getByTestId(/^tie-/)).toHaveCount(1);
    });

    test('shows every division on court now under All, then filters by tab', async ({
        page,
    }) => {
        await expect(
            page.getByTestId(`live-match-${E2E_BOARD_MATCHES.groupALive}`),
        ).toBeVisible();
        await expect(
            page.getByTestId(`live-match-${E2E_BOARD_MATCHES.finalLive}`),
        ).toBeVisible();
        await expect(
            page.getByTestId(`live-match-${E2E_BOARD_MATCHES.teamRubberLive}`),
        ).toBeVisible();

        await page.getByTestId('division-tab-team').click();

        await expect(
            page.getByTestId(`live-match-${E2E_BOARD_MATCHES.teamRubberLive}`),
        ).toBeVisible();
        await expect(
            page.getByTestId(`live-match-${E2E_BOARD_MATCHES.groupALive}`),
        ).toHaveCount(0);
    });

    test('updates a board score as the admin records a point', async ({
        browser,
    }) => {
        const spectator = await browser.newContext();
        const admin = await browser.newContext();
        const spectatorPage = await spectator.newPage();
        const adminPage = await admin.newPage();

        await spectatorPage.goto(liveBoard(E2E_BOARD_SLUG));

        const row = spectatorPage.getByTestId(
            `live-match-${E2E_BOARD_MATCHES.finalLive}`,
        );
        await expect(row).toBeVisible();

        // The e2e DB persists between runs, so don't assume a starting score —
        // capture it and assert the recorded point advances it.
        const points = row.getByTestId('live-points-a');
        const before = (await points.textContent())?.trim() ?? '';

        await loginAdmin(adminPage);
        await adminPage.goto(
            `/admin/tennis-matches/${E2E_BOARD_MATCHES.finalLive}`,
        );
        await awardPoint(adminPage, 'a');

        await spectatorPage.bringToFront();
        await expect(points).not.toHaveText(before, { timeout: 30_000 });

        await spectator.close();
        await admin.close();
    });
});
