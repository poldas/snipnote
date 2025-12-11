import { expect, Page } from '@playwright/test';

/**
 * Landing page with embedded login form.
 * Uses data-test-id selectors for resilient navigation steps.
 */
export class LandingPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/');
        expect(response?.ok()).toBeTruthy();
    }

    async expectHeroVisible() {
        await expect(this.page.getByRole('heading', { name: /snipnote/i })).toBeVisible();
    }

    async goToLogin() {
        await this.page.getByRole('link', { name: /zaloguj|login/i }).click();
    }

    async expectTaglineVisible() {
        await expect(this.page.getByText('Notuj. Udostępniaj. Współpracuj w Snipnote.')).toBeVisible();
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

