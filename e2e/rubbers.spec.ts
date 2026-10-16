import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import {
    E2E_RUBBER_DIVISION,
    E2E_RUBBER_TOURNAMENT,
} from './helpers/accounts';
import { loginAdmin } from './helpers/auth';
import { bracketsUrl, resolveDivisionId } from './helpers/brackets';

/*
 * Adding a rubber through the tie modal proves the format switches round-trip:
 * the toggles the admin flips are persisted by the real endpoint and read back
 * when the rubber is reopened for editing. The pending rubber is then deleted
 * through the confirm dialog. Uses the dedicated `e2e-rubber` fixture (one
 * ready tie, no rubbers yet).
 */

async function toggle(page: Page, testId: string, on: boolean): Promise<void> {
    const control = page.getByTestId(testId);

    await expect(control).toHaveAttribute('role', 'switch');

    if ((await control.getAttribute('aria-checked')) !== String(on)) {
        await control.click();
    }
}

test('sets a rubber format through the toggles and reads it back', async ({
    page,
}) => {
    await loginAdmin(page);

    const { tournamentId, divisionId } = await resolveDivisionId(
        page,
        E2E_RUBBER_TOURNAMENT,
        E2E_RUBBER_DIVISION,
    );

    await page.goto(bracketsUrl(tournamentId, divisionId));

    // The tie has no rubbers yet, so the modal opens straight into the form.
    await page.locator('[data-testid="rubbers"][data-slot]').click();

    // Three switches (not checkboxes) at their defaults.
    await expect(page.getByTestId('rubber-extend-at-tie')).toHaveAttribute(
        'aria-checked',
        'false',
    );
    await expect(page.getByTestId('rubber-extend-tiebreak')).toHaveAttribute(
        'aria-checked',
        'false',
    );
    await expect(page.getByTestId('rubber-sudden-death')).toHaveAttribute(
        'aria-checked',
        'true',
    );

    // A singles rubber is submittable straight from the tie's two entrants.
    await page.getByTestId('rubber-type-singles').click();

    await toggle(page, 'rubber-extend-at-tie', true);
    await toggle(page, 'rubber-sudden-death', false);

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/rubbers') &&
                response.request().method() === 'POST' &&
                // Inertia posts get a 302 redirect back to the page, so accept
                // the redirect rather than requiring a 2xx.
                response.status() < 400,
        ),
        page.getByTestId('rubber-submit').click(),
    ]);

    // Back on the list with the new rubber.
    await expect(page.getByTestId('rubber-row')).toHaveCount(1);

    // Reopen it: the toggles reflect the persisted format.
    await page.getByTestId('rubber-edit').click();

    await expect(page.getByTestId('rubber-extend-at-tie')).toHaveAttribute(
        'aria-checked',
        'true',
    );
    await expect(page.getByTestId('rubber-extend-tiebreak')).toHaveAttribute(
        'aria-checked',
        'false',
    );
    await expect(page.getByTestId('rubber-sudden-death')).toHaveAttribute(
        'aria-checked',
        'false',
    );

    // Back to the list, then delete the pending rubber through the confirm
    // dialog and read the empty state back.
    await page.getByTestId('rubber-cancel').click();
    await page.getByTestId('rubber-delete').click();

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/rubbers') &&
                response.request().method() === 'DELETE' &&
                response.status() < 400,
        ),
        page.getByTestId('confirm-modal-accept').click(),
    ]);

    await expect(page.getByTestId('rubber-row')).toHaveCount(0);
});
