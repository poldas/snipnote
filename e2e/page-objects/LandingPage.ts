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
        await expect(this.page.getByRole('heading', { name: /Notuj\. Udostępniaj\. Współpracuj/i })).toBeVisible();
    }

    async expectHeroSubtitleVisible() {
        await expect(this.page.getByText('Szybkie, bezpieczne i przejrzyste notatki')).toBeVisible();
    }

    async expectFeaturesSectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Wszystko, czego potrzebujesz' })).toBeVisible();
    }

    async expectHowItWorksSectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Jak to działa?' })).toBeVisible();
    }

    async expectCTASectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Gotowy na lepsze notatki?' })).toBeVisible();
    }

    async expectFooterVisible() {
        await expect(this.page.getByText(/© \d{4} Snipnote\. Wszelkie prawa zastrzeżone\./)).toBeVisible();
    }

    async goToLogin() {
        await this.page.getByRole('link', { name: /zaloguj/i }).click();
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

    async clickPrimaryCTA() {
        await this.page.getByRole('link', { name: 'Rozpocznij za darmo' }).click();
    }

    async clickSecondaryCTA() {
        await this.page.getByRole('link', { name: 'Zobacz możliwości' }).click();
    }

    async clickCTARegister() {
        await this.page.getByRole('link', { name: 'Załóż darmowe konto' }).click();
    }

    async clickCTALogin() {
        await this.page.getByRole('link', { name: 'Mam już konto' }).click();
    }

    async expectLoginFormVisible() {
        await expect(this.page.getByRole('heading', { name: 'Zaloguj się do Snipnote' })).toBeVisible();
        await expect(this.page.getByLabel('Adres email')).toBeVisible();
        await expect(this.page.getByLabel('Hasło')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Zaloguj się' })).toBeVisible();
    }

    async expectLogoHoverEffect() {
        const logo = this.page.locator('[data-test-id="logo-home-link"]');

        // Check if logo is visible and hoverable
        await expect(logo).toBeVisible();

        // Hover over logo
        await logo.hover();
        await this.page.waitForTimeout(300); // Wait for transition

        // Logo should still be visible after hover
        await expect(logo).toBeVisible();
    }

    async expectButtonHoverEffects() {
        // Test "Zobacz możliwości" button hover
        const secondaryButton = this.page.getByRole('link', { name: 'Zobacz możliwości' });
        await secondaryButton.hover();
        await this.page.waitForTimeout(300);

        // Test CTA buttons hover
        const primaryCTA = this.page.getByRole('link', { name: 'Załóż darmowe konto' });
        await primaryCTA.hover();
        await this.page.waitForTimeout(300);

        const secondaryCTA = this.page.getByRole('link', { name: 'Mam już konto' });
        await secondaryCTA.hover();
        await this.page.waitForTimeout(300);
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

