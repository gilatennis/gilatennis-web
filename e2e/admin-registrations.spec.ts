import { expect, test } from '@playwright/test';

import {
    E2E_GUARD_ENTRY,
    E2E_REG_DIVISIONS,
    E2E_REGISTRATION_TOURNAMENT,
} from './helpers/accounts';
import { loginAdmin } from './helpers/auth';
import {
    addPlayer,
    createRegistrationUrl,
    deleteViaSlideOver,
    editViaSlideOver,
    fillCaptain,
    fillMember,
    gotoRegistrations,
    openRegistrationRow,
    resolveTournamentId,
    selectDivision,
    setPayment,
    submitRegistration,
} from './helpers/registrations';

/*
 * Admin registration CRUD end-to-end: an admin registers a player/team for a
 * tournament from the panel. Uses the dedicated, bracket-less `e2e-registration`
 * fixture (docs/E2E.md) plus one entrant already placed in a live draw.
 */

test('admin registers a team and manages it end to end', async ({ page }) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.team);
    await page.locator('#teamName').fill('E2E Aces');
    await fillCaptain(page, {
        name: 'Cap Team',
        email: 'reg.team.cap@e2e.test',
        phone: '081200000010',
        gender: 'Male',
        level: 'Intermediate',
    });
    await addPlayer(page);
    await fillMember(page, 1, {
        name: 'Member Two',
        email: 'reg.team.m2@e2e.test',
        phone: '081200000011',
        gender: 'Female',
        level: 'Upper Beginner',
    });
    await setPayment(page, 'paid');
    await submitRegistration(page, id);

    // The paid team registration appears with its members.
    await openRegistrationRow(page, 'Cap Team');
    await expect(page.getByText('Paid').first()).toBeVisible();
    await expect(page.getByText('E2E Aces').first()).toBeVisible();
    await expect(page.getByText('Member Two')).toBeVisible();

    // Edit the captain's name (division and team name are fixed after
    // creation; the edit step renders the locked division block).
    await editViaSlideOver(page);
    await page.locator('#name').fill('Cap Team Renamed');
    await page.getByTestId('registration-submit').click();
    await expect(page).toHaveURL(
        new RegExp(`/admin/tournaments/${id}/registrations$`),
    );
    await expect(page.getByText('Cap Team Renamed').first()).toBeVisible();

    // Delete it.
    await openRegistrationRow(page, 'Cap Team Renamed');
    await deleteViaSlideOver(page, id);
    await expect(page.getByText('Cap Team Renamed')).toHaveCount(0);
});

test('admin registers a single as pending', async ({ page }) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.singles);
    await fillCaptain(page, {
        name: 'Solo Player',
        email: 'reg.single@e2e.test',
        phone: '081200000020',
        gender: 'Male',
    });
    await setPayment(page, 'pending');
    await submitRegistration(page, id);

    const row = page
        .getByTestId(/^registrant-row-/)
        .filter({ hasText: 'Solo Player' })
        .first();
    await expect(row).toBeVisible();
    await expect(row.getByText('Pending')).toBeVisible();

    await openRegistrationRow(page, 'Solo Player');
    await deleteViaSlideOver(page, id);
});

test('admin overrides a registration payment from the slide-over', async ({
    page,
}) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.singles);
    await fillCaptain(page, {
        name: 'Override Player',
        email: 'reg.override@e2e.test',
        phone: '081200000040',
        gender: 'Male',
    });
    await setPayment(page, 'pending');
    await submitRegistration(page, id);

    await openRegistrationRow(page, 'Override Player');
    await page.getByRole('button', { name: 'Mark as Paid' }).click();
    await page.getByRole('button', { name: 'Confirm' }).click();

    // A broken payment URL (wrong route key) would 404 silently and leave the
    // status untouched, so re-open after a reload to prove it really changed.
    await page.reload();
    await openRegistrationRow(page, 'Override Player');
    await expect(page.getByText('Paid').first()).toBeVisible();

    await deleteViaSlideOver(page, id);
});

test('admin registers a doubles pair', async ({ page }) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.doubles);
    await fillCaptain(page, {
        name: 'Dbl Cap',
        email: 'reg.dbl.cap@e2e.test',
        phone: '081200000030',
        gender: 'Male',
    });
    await fillMember(page, 1, {
        name: 'Dbl Partner',
        email: 'reg.dbl.p1@e2e.test',
        phone: '081200000031',
        gender: 'Female',
        level: 'Intermediate',
    });
    await setPayment(page, 'pending');
    await submitRegistration(page, id);

    const row = page
        .getByTestId(/^registrant-row-/)
        .filter({ hasText: 'Dbl Cap' })
        .first();
    await expect(row).toBeVisible();
    await expect(row.getByText(E2E_REG_DIVISIONS.doubles)).toBeVisible();

    await openRegistrationRow(page, 'Dbl Cap');
    await deleteViaSlideOver(page, id);
});

test('admin sees a validation error for an undersized team', async ({
    page,
}) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.team);
    await page.locator('#teamName').fill('E2E Lonely');
    await fillCaptain(page, {
        name: 'Lonely Cap',
        email: 'reg.lonely@e2e.test',
        phone: '081200000040',
        gender: 'Male',
        level: 'Intermediate',
    });
    // No team member added: the team is below its minimum size.
    await setPayment(page, 'pending');
    await page.getByTestId('registration-submit').click();

    await expect(
        page.getByText('Some fields need your attention.'),
    ).toBeVisible();
    await expect(
        page.getByText('Team requires at least 2 players.'),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/registrations\/create$/);
});

test('editing a registration placed in a live bracket is blocked', async ({
    page,
}) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await gotoRegistrations(page, id);
    await openRegistrationRow(page, E2E_GUARD_ENTRY);
    await editViaSlideOver(page);

    // No changes required — the guard fires before the update is applied.
    await page.getByTestId('registration-submit').click();

    await expect(
        page.getByText(/placed in a live bracket/).first(),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/edit$/);
});

test('admin withdraws a pre-draw entrant from its details page', async ({
    page,
}) => {
    await loginAdmin(page);
    const id = await resolveTournamentId(page, E2E_REGISTRATION_TOURNAMENT);

    await page.goto(createRegistrationUrl(id));
    await selectDivision(page, E2E_REG_DIVISIONS.singles);
    await fillCaptain(page, {
        name: 'Withdraw Player',
        email: 'reg.withdraw@e2e.test',
        phone: '081200000050',
        gender: 'Male',
    });
    await setPayment(page, 'paid');
    await submitRegistration(page, id);

    // Reach the scoring/details page through the division row (the list row
    // opens the slide-over, so use the division link to leave the list).
    const row = page
        .getByTestId(/^registrant-row-/)
        .filter({ hasText: 'Withdraw Player' })
        .first();
    const divisionHref = await row
        .getByRole('link', { name: E2E_REG_DIVISIONS.singles })
        .getAttribute('href');
    await page.goto(divisionHref!);

    await page
        .getByTestId(/^registrant-row-/)
        .filter({ hasText: 'Withdraw Player' })
        .first()
        .click();
    await expect(page).toHaveURL(/\/registrations\/GO-/);

    await page.getByTestId('withdraw-team').click();
    await page.getByTestId('confirm-withdraw').click();

    await expect(page.getByText('Withdrawn').first()).toBeVisible();
    await expect(page.getByTestId('withdraw-team')).toHaveCount(0);
});

