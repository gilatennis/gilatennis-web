import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

import { resolveTournamentId } from './registrations';

/*
 * Resolving admin bracket pages. Admin routes bind by id and the fixture ids are
 * not stable, so the ids are read from the admin UI (mirrors resolveTournamentId
 * in registrations.ts), never hard-coded.
 */

export function bracketsUrl(
    tournamentId: number,
    divisionId: number,
): string {
    return `/admin/tournaments/${tournamentId}/divisions/${divisionId}?tab=brackets`;
}

/**
 * Resolve a division's id (and its tournament's) by walking the admin UI: find
 * the tournament by name on the index, then its division link on the show page.
 */
export async function resolveDivisionId(
    page: Page,
    tournamentName: string,
    divisionName: string,
): Promise<{ tournamentId: number; divisionId: number }> {
    const tournamentId = await resolveTournamentId(page, tournamentName);

    await page.goto(`/admin/tournaments/${tournamentId}`);

    const link = page
        .getByRole('link')
        .filter({ hasText: divisionName })
        .first();
    await expect(link).toBeVisible();

    const href = await link.getAttribute('href');
    const match = href?.match(/\/divisions\/(\d+)/);

    if (!match) {
        throw new Error(`Could not resolve division id from "${href}".`);
    }

    return { tournamentId, divisionId: Number(match[1]) };
}
