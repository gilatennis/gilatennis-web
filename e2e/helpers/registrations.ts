import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/*
 * Helpers for driving the admin tournament-registration CRUD screens
 * (docs/E2E.md). Every selector targets a stable id or `data-testid` so the
 * specs survive markup tweaks.
 */

export interface PlayerInput {
    name: string;
    email: string;
    phone: string;
    gender: 'Male' | 'Female';
    level?: string;
}

export function registrationsUrl(tournamentId: number): string {
    return `/admin/tournaments/${tournamentId}/registrations`;
}

export function createRegistrationUrl(tournamentId: number): string {
    return `${registrationsUrl(tournamentId)}/create`;
}

const indexUrlPattern = (tournamentId: number) =>
    new RegExp(`${registrationsUrl(tournamentId)}$`);

/**
 * Resolve a tournament id by name from the admin index. Admin routes bind by
 * id and the fixture ids are not stable, so the spec finds the row link and
 * reads the id from its href.
 */
export async function resolveTournamentId(
    page: Page,
    name: string,
): Promise<number> {
    await page.goto('/admin/tournaments');

    const link = page.getByRole('link').filter({ hasText: name }).first();
    await expect(link).toBeVisible();

    const href = await link.getAttribute('href');
    const match = href?.match(/\/admin\/tournaments\/(\d+)/);

    if (!match) {
        throw new Error(`Could not resolve tournament id from "${href}".`);
    }

    return Number(match[1]);
}

export async function gotoRegistrations(
    page: Page,
    tournamentId: number,
): Promise<void> {
    await page.goto(registrationsUrl(tournamentId));
    await expect(page).toHaveURL(indexUrlPattern(tournamentId));
}

async function selectOption(
    page: Page,
    triggerSelector: string,
    label: string,
): Promise<void> {
    await page.locator(triggerSelector).click();
    await page.getByRole('option', { name: label, exact: true }).click();
}

export async function selectDivision(page: Page, name: string): Promise<void> {
    await page.getByRole('button').filter({ hasText: name }).first().click();
}

export async function fillCaptain(
    page: Page,
    player: PlayerInput,
): Promise<void> {
    await page.locator('#name').fill(player.name);
    await page.locator('#email').fill(player.email);
    await page.locator('#phone').fill(player.phone);
    await selectOption(page, '#gender', player.gender);

    if (player.level) {
        await selectOption(page, '#level', player.level);
    }
}

export async function addPlayer(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Add player' }).click();
}

export async function fillMember(
    page: Page,
    index: number,
    player: PlayerInput,
): Promise<void> {
    await page.locator(`#player-${index}-name`).fill(player.name);
    await page.locator(`#player-${index}-email`).fill(player.email);
    await page.locator(`#player-${index}-phone`).fill(player.phone);
    await selectOption(page, `#player-${index}-gender`, player.gender);

    if (player.level) {
        await selectOption(page, `#player-${index}-level`, player.level);
    }
}

export async function setPayment(
    page: Page,
    action: 'pending' | 'paid',
): Promise<void> {
    await page
        .getByRole('radio', {
            name: action === 'paid' ? /Mark as paid/ : /Leave as pending/,
        })
        .check();
}

/** Submit the create form and wait for the redirect back to the index. */
export async function submitRegistration(
    page: Page,
    tournamentId: number,
): Promise<void> {
    await page.getByTestId('registration-submit').click();
    await expect(page).toHaveURL(indexUrlPattern(tournamentId));
}

/** Click a registration's name in the list to open the slide-over. */
export async function openRegistrationRow(
    page: Page,
    name: string,
): Promise<void> {
    const row = page
        .getByTestId(/^registrant-row-/)
        .filter({ hasText: name })
        .first();
    await row.getByText(name, { exact: true }).click();
    await expect(page.getByTestId('registration-manage-edit')).toBeVisible();
}

export async function editViaSlideOver(page: Page): Promise<void> {
    await page.getByTestId('registration-manage-edit').click();
    await expect(page).toHaveURL(/\/edit$/);
}

export async function deleteViaSlideOver(
    page: Page,
    tournamentId: number,
): Promise<void> {
    await page.getByTestId('registration-manage-delete').click();
    await page.getByTestId('registration-delete-confirm').click();
    // The slide-over closes on success; the list refreshes behind it.
    await expect(page.getByTestId('registration-manage-edit')).toBeHidden();
    await expect(page).toHaveURL(indexUrlPattern(tournamentId));
}
