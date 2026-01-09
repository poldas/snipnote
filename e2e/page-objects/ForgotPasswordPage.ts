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
        await expect(this.page.getByLabel('Email')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Wyślij instrukcje resetu' })).toBeVisible();
    }

    async expectFormElements() {
        await expect(this.page.locator('input[name="email"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Wyślij instrukcje resetu' })).toBeVisible();
        await expect(this.page.getByTestId('forgot-link-login')).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Załóż konto' })).toBeVisible();
    }

    async clickLoginLink() {
        await this.page.getByTestId('forgot-link-login').click();
    }

    async clickLogoHome() {
        await this.page.getByTestId('logo-home-link').click();
    }

    async requestReset(email: string) {
        await this.page.locator('input[name="email"]').fill(email);
        await this.page.getByRole('button', { name: 'Wyślij instrukcje resetu' }).click();
    }

    async expectSuccessMessage() {
        await expect(this.page.getByText('Jeśli konto istnieje, wysłaliśmy instrukcje resetu hasła.')).toBeVisible();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

