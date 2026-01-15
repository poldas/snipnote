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
        await expect(this.page.getByRole('heading', { name: /Sprawdź swoją skrzynkę email/ })).toBeVisible();
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
        await expect(this.page.getByTestId('resend-verification-button')).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Wróć do logowania' })).toBeVisible();
    }

    async clickResendVerificationButton() {
        await this.page.getByTestId('resend-verification-button').click();
    }

    async expectRateLimitAlert() {
        await expect(this.page.getByTestId('rate-limit-alert')).toBeVisible();
        await expect(this.page.getByTestId('rate-limit-alert')).toContainText('Zbyt wiele prób wysłania linku. Spróbuj ponownie później.');
    }

    async expectSuccessAlert() {
        // Wait for all network requests to complete, to ensure the page is fully rendered
        await this.page.waitForLoadState('networkidle');
        await expect(this.page.getByText('Nowy link weryfikacyjny został wysłany.')).toBeVisible();
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
