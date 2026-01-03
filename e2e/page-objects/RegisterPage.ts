import { expect, Page } from '@playwright/test';

/**
 * Register page (auth layout).
 */
export class RegisterPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/register');
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page.getByRole('heading', { name: 'Załóż konto w Snipnote' })).toBeVisible();
        await expect(this.page.locator('input[name="email"]')).toBeVisible();
        await expect(this.page.locator('input[name="password"]')).toBeVisible();
        await expect(this.page.locator('input[name="passwordConfirm"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Załóż konto' })).toBeVisible();
    }

    async expectFormElements() {
        await expect(this.page.locator('input[name="email"]')).toBeVisible();
        await expect(this.page.locator('input[name="password"]')).toBeVisible();
        await expect(this.page.locator('input[name="passwordConfirm"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Załóż konto' })).toBeVisible();
        await expect(this.page.locator('[data-test-id="register-link-login"]')).toBeVisible();
    }

    async clickLoginLink() {
        await this.page.locator('[data-test-id="register-link-login"]').click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

