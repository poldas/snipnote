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

    async expectPrimaryCTAHoverEffect() {
        const primaryCTA = this.page.getByRole('link', { name: 'Rozpocznij za darmo' });

        // Take baseline screenshot
        await this.takeScreenshot('primary-cta-baseline');

        // Hover and take screenshot during animation
        await primaryCTA.hover();
        await this.page.waitForTimeout(350); // Wait for transition
        await this.takeScreenshot('primary-cta-hover');

        // Verify button is still visible and accessible
        await expect(primaryCTA).toBeVisible();
        await expect(primaryCTA).toBeEnabled();
    }

    async expectSecondaryCTAHoverEffect() {
        const secondaryCTA = this.page.getByRole('link', { name: 'Zobacz możliwości' });

        // Take baseline screenshot
        await this.takeScreenshot('secondary-cta-baseline');

        // Hover and take screenshot during animation
        await secondaryCTA.hover();
        await this.page.waitForTimeout(350); // Wait for transition
        await this.takeScreenshot('secondary-cta-hover');

        // Verify button is still visible and accessible
        await expect(secondaryCTA).toBeVisible();
        await expect(secondaryCTA).toBeEnabled();
    }

    async expectFeatureCardsHoverEffects() {
        // Test hover effects on all feature cards
        for (let i = 1; i <= 6; i++) {
            const card = this.page.locator(`[data-testid="feature-card-${i}"]`);

            if (await card.isVisible()) {
                // Take baseline
                await this.takeScreenshot(`feature-card-${i}-baseline`);

                // Hover and take screenshot
                await card.hover();
                await this.page.waitForTimeout(350);
                await this.takeScreenshot(`feature-card-${i}-hover`);

                // Verify card is still visible
                await expect(card).toBeVisible();
            }
        }
    }

    async expectLoginFormHoverEffects() {
        const loginButton = this.page.getByRole('button', { name: 'Zaloguj się' });
        const forgotPasswordLink = this.page.getByRole('link', { name: 'Zapomniałeś hasła?' });

        // Test login button hover
        await this.takeScreenshot('login-button-baseline');
        await loginButton.hover();
        await this.page.waitForTimeout(350);
        await this.takeScreenshot('login-button-hover');
        await expect(loginButton).toBeVisible();

        // Test forgot password link hover
        await this.takeScreenshot('forgot-password-baseline');
        await forgotPasswordLink.hover();
        await this.page.waitForTimeout(300);
        await this.takeScreenshot('forgot-password-hover');
        await expect(forgotPasswordLink).toBeVisible();

        // Test register link hover
        const registerLink = this.page.getByRole('link', { name: 'Zarejestruj się' });
        await this.takeScreenshot('register-link-baseline');
        await registerLink.hover();
        await this.page.waitForTimeout(300);
        await this.takeScreenshot('register-link-hover');
        await expect(registerLink).toBeVisible();
    }

    async expectCTASectionHoverEffects() {
        const primaryCTA = this.page.getByRole('link', { name: 'Załóż darmowe konto' });
        const secondaryCTA = this.page.getByRole('link', { name: 'Mam już konto' });

        // Test primary CTA in CTA section
        await this.takeScreenshot('cta-section-primary-baseline');
        await primaryCTA.hover();
        await this.page.waitForTimeout(350);
        await this.takeScreenshot('cta-section-primary-hover');
        await expect(primaryCTA).toBeVisible();

        // Test secondary CTA in CTA section
        await this.takeScreenshot('cta-section-secondary-baseline');
        await secondaryCTA.hover();
        await this.page.waitForTimeout(350);
        await this.takeScreenshot('cta-section-secondary-hover');
        await expect(secondaryCTA).toBeVisible();
    }

    async expectNavigationLinksHoverEffects() {
        const loginLink = this.page.getByRole('link', { name: /zaloguj/i });
        const registerLink = this.page.getByRole('link', { name: 'Załóż konto' });

        // Test login link hover
        await this.takeScreenshot('nav-login-baseline');
        await loginLink.hover();
        await this.page.waitForTimeout(300);
        await this.takeScreenshot('nav-login-hover');
        await expect(loginLink).toBeVisible();

        // Test register link hover
        await this.takeScreenshot('nav-register-baseline');
        await registerLink.hover();
        await this.page.waitForTimeout(300);
        await this.takeScreenshot('nav-register-hover');
        await expect(registerLink).toBeVisible();
    }

    async expectFooterLogoHoverEffect() {
        // Footer logo might have different structure
        const footerLogo = this.page.locator('footer').locator('a').filter({ hasText: 'Snipnote' });

        if (await footerLogo.isVisible()) {
            await this.takeScreenshot('footer-logo-baseline');
            await footerLogo.hover();
            await this.page.waitForTimeout(350);
            await this.takeScreenshot('footer-logo-hover');
            await expect(footerLogo).toBeVisible();
        }
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

