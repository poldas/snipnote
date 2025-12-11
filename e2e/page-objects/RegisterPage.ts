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

    async clickLoginLink() {
        await this.page.locator('[data-test-id="register-link-login"]').click();
    }

    async clickLogoHome() {
        await this.page.locator('[data-test-id="logo-home-link"]').click();
    }
}

