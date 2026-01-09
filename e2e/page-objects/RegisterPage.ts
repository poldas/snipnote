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
        await expect(this.page.getByTestId('register-link-login')).toBeVisible();
    }

    async clickLoginLink() {
        await this.page.getByTestId('register-link-login').click();
    }

    async clickLogoHome() {
        await this.page.getByTestId('logo-home-link').click();
    }

    async register(email: string, pass: string) {
        await this.page.locator('input[name="email"]').fill(email);
        await this.page.locator('input[name="password"]').fill(pass);
        await this.page.locator('input[name="passwordConfirm"]').fill(pass);
        await this.page.getByRole('button', { name: 'Załóż konto' }).click();
    }

    async expectVerificationNotice() {
        await expect(this.page).toHaveURL(/.*\/verify\/email\/notice/);
        await expect(this.page.getByRole('heading', { name: /Sprawdź swoją skrzynkę|Potwierdź e-mail/ })).toBeVisible();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

