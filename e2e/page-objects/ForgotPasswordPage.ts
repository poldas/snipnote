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

    async clickLoginLink() {
        await this.page.locator('[data-test-id="forgot-link-login"]').click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }
}

