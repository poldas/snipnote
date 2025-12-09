import { expect, Page } from '@playwright/test';

/**
 * Login page (auth layout).
 */
export class LoginPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/login');
        expect(response?.ok()).toBeTruthy();
    }

    async clickRegisterLink() {
        await this.page.locator('[data-test-id="login-link-register"]').click();
    }

    async clickForgotPasswordLink() {
        await this.page.locator('[data-test-id="login-link-forgot-password"]').click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }
}

