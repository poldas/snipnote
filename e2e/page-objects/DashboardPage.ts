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
        await expect(this.page).toHaveURL(/\/notes/);
        // Logo link should be visible in either mobile or desktop version
        await expect(this.page.locator('[data-test-id="logo-home-link"]').filter({ visible: true }).first()).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Dodaj notatkę' }).filter({ visible: true }).first()).toBeVisible();
    }

    async expectUserLoggedIn(email: string) {
        // Look specifically in the header area, not in debug toolbar
        // Might have multiple instances (mobile/desktop), pick visible one
        await expect(this.page.locator('header').getByText(email).filter({ visible: true }).first()).toBeVisible();
    }

    async clickLogout() {
        await this.page.getByRole('button', { name: 'Wyloguj' }).click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}
