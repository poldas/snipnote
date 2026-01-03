import { expect, Page } from '@playwright/test';

/**
 * Email verification notice page (auth layout).
 */
export class VerifyNoticePage {
    constructor(private readonly page: Page) { }

    async goto(state: string = 'pending', email: string = 'test@example.com') {
        const response = await this.page.goto(`/verify/email/notice?state=${state}&email=${email}`);
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page.getByRole('heading', { name: /Potwierdź swój adres email/ })).toBeVisible();
    }

    async expectPendingState() {
        await expect(this.page.getByText('Wysłaliśmy link aktywacyjny na Twój email.')).toBeVisible();
    }

    async expectSuccessState() {
        await expect(this.page.getByText(/Dziękujemy za rejestrację/)).toBeVisible();
    }

    async expectFormElements() {
        await expect(this.page.getByLabel('Adres email')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Wyślij ponownie link' })).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Wróć do logowania' })).toBeVisible();
    }

    async clickBackToLogin() {
        await this.page.getByRole('link', { name: 'Wróć do logowania' }).click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}
