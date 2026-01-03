import { expect, Page } from '@playwright/test';

/**
 * Forgot password page (auth layout).
 */
export class ForgotPasswordPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/forgot-password');
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page.getByRole('heading', { name: 'Odzyskaj dostęp do konta' })).toBeVisible();
        await expect(this.page.getByLabel('Adres email')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Wyślij link resetu' })).toBeVisible();
    }

    async expectFormElements() {
        await expect(this.page.locator('input[name="email"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Wyślij link resetu' })).toBeVisible();
        await expect(this.page.locator('[data-test-id="forgot-link-login"]')).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Zarejestruj się' })).toBeVisible();
    }

    async clickLoginLink() {
        await this.page.locator('[data-test-id="forgot-link-login"]').click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

