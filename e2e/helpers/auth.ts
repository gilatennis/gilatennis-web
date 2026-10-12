import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

import { E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD } from './accounts';

/*
 * Admin login through the real form (no session cookie is injected), so each
 * spec proves the credential path it depends on.
 */
export async function loginAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill(E2E_ADMIN_EMAIL);
    // `exact` so the label never collides with the "Show password" toggle button.
    await page.getByLabel('Password', { exact: true }).fill(E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL('**/admin/dashboard');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
}
