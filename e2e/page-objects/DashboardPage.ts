import { expect, Page } from '@playwright/test';

/**
 * Dashboard page (/notes) - authenticated user dashboard with notes.
 */
export class DashboardPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/notes');
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page.getByRole('button', { name: 'Dodaj notatkę' })).toBeVisible();
        await expect(this.page.getByText('Snipnote')).toBeVisible();
    }

    async expectUserLoggedIn(email: string) {
        // Look specifically in the header area, not in debug toolbar
        await expect(this.page.locator('header').getByText(email)).toBeVisible();
    }

    async clickLogout() {
        await this.page.getByRole('button', { name: 'Wyloguj' }).click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}
