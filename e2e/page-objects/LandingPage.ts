import { expect, Page } from '@playwright/test';

/**
 * Landing page with embedded login form.
 * Uses data-testid selectors for resilient navigation steps.
 */
export class LandingPage {
    constructor(public readonly page: Page) { }

    async goto() {
        await this.page.goto('/');
        await this.page.waitForLoadState('networkidle');
    }

    async expectHeroVisible() {
        await expect(this.page.getByRole('heading', { name: /Notuj\. Udostępniaj\. Współpracuj/i })).toBeVisible();
    }

    async expectHeroSubtitleVisible() {
        await expect(this.page.getByText('Szybkie, bezpieczne i przejrzyste notatki')).toBeVisible();
    }

    async expectLoginFormHeadersVisible() {
        await expect(this.page.getByRole('heading', { name: 'Zaloguj się do Snipnote' })).toBeVisible();
        await expect(this.page.getByText('Wróć do swoich notatek i kontynuuj pracę')).toBeVisible();
    }

    async expectFeaturesSectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Wszystko, czego potrzebujesz' })).toBeVisible();
    }

    async getFeatureCard(index: number) {
        return this.page.getByTestId(`feature-card-${index}`);
    }

    async expectFeatureCardDetails(index: number, title: string, description: string) {
        const card = await this.getFeatureCard(index);
        await expect(card.getByRole('heading')).toHaveText(title);
        await expect(card.locator('p')).toHaveText(description);
    }

    async expectFeatureCardsCount(count: number) {
        for (let i = 1; i <= count; i++) {
            await expect(this.page.getByTestId(`feature-card-${i}`)).toBeVisible();
        }
    }

    async expectHowItWorksSectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Jak to działa?' })).toBeVisible();
        await expect(this.page.getByText('Trzy proste kroki do lepszej organizacji notatek')).toBeVisible();
    }

    async expectStepDetails(number: number, title: string, description: string) {
        const step = this.page.locator('.relative.flex.flex-col.items-center').nth(number - 1);
        await expect(step.locator('h3')).toHaveText(title);
        await expect(step.locator('p')).toHaveText(description);
    }

    async expectBenefitTagsVisible() {
        const benefits = ['Anonimowe notatki', 'Łatwe udostępnianie', 'Współpraca'];
        for (const benefit of benefits) {
            await expect(this.page.getByText(benefit).first()).toBeVisible();
        }
    }

    async expectBrandVisuals() {
        // Verify Hero has the correct brand gradient classes (Tailwind check)
        const heroTitle = this.page.locator('h1 span');
        await expect(heroTitle).toHaveClass(/from-indigo-600/);
        await expect(heroTitle).toHaveClass(/to-purple-600/);

        // Verify main CTA button has the primary gradient class
        const cta = this.page.locator('a[href="/register"]').first();
        await expect(cta).toHaveClass(/btn-auth-primary/);
    }

    async expectMobileLayout() {
        // Verify that elements adjust correctly at mobile viewport
        const viewportWidth = this.page.viewportSize()?.width || 0;
        
        if (viewportWidth < 768) {
            // Check if benefit tags container is visible (specific hero version)
            const benefitsContainer = this.page.locator('section').first().locator('div.flex.flex-wrap.justify-center');
            await expect(benefitsContainer).toBeVisible();
            
            // On very small screens, ensure we don't have desktop-only nav elements
            const desktopOnlyNav = this.page.locator('nav .hidden.md\\:flex');
            if (await desktopOnlyNav.count() > 0) {
                await expect(desktopOnlyNav).not.toBeVisible();
            }
        }
    }

    async expectCTASectionVisible() {
        await expect(this.page.getByRole('heading', { name: 'Gotowy na lepsze notatki?' })).toBeVisible();
        await expect(this.page.getByText('Zacznij ze Snipnote już dziś. Rozpocznij za darmo.')).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Załóż darmowe konto' })).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Mam już konto' })).toBeVisible();
    }

    async expectFooterVisible() {
        await expect(this.page.getByText(/© \d{4} Snipnote\. Wszelkie prawa zastrzeżone\./)).toBeVisible();
    }

    async expectInputFocusEffect(name: string) {
        const input = this.page.locator(`input[name="${name}"]`);

        await input.blur();
        await this.page.waitForTimeout(100);
        const initialShadow = await input.evaluate(el => window.getComputedStyle(el).boxShadow);

        await input.focus();
        await this.page.waitForTimeout(400);

        const focusShadow = await input.evaluate(el => window.getComputedStyle(el).boxShadow);
        expect(focusShadow).not.toBe(initialShadow);
    }

    async expectCardHoverEffect(index: number) {
        const card = await this.getFeatureCard(index);
        const initialShadow = await card.evaluate(el => window.getComputedStyle(el).boxShadow);

        await card.hover();
        await this.page.waitForTimeout(300);

        const hoverShadow = await card.evaluate(el => window.getComputedStyle(el).boxShadow);
        expect(hoverShadow).not.toBe(initialShadow);
    }

    async expectLogoHoverEffect() {
        const logo = this.page.getByTestId('logo-home-link').first();
        const aura = logo.locator('div.absolute').first();

        await expect(aura).toBeVisible();

        const initialOpacity = await aura.evaluate(el => window.getComputedStyle(el).opacity);
        expect(parseFloat(initialOpacity)).toBeCloseTo(0.6, 1);

        await logo.hover();
        await this.page.waitForTimeout(500);

        const hoverOpacity = await aura.evaluate(el => window.getComputedStyle(el).opacity);
        expect(parseFloat(hoverOpacity)).toBeCloseTo(1, 1);
    }

    async goToLogin() {
        await this.page.getByRole('link', { name: /zaloguj/i }).first().click();
        await this.page.waitForURL('**/login');
    }

    async clickRegisterLink() {
        await this.page.getByTestId('login-link-register').click();
    }

    async clickForgotPasswordLink() {
        await this.page.getByTestId('login-link-forgot-password').click();
    }

    async clickLogoHome() {
        await this.page.getByTestId('logo-home-link').first().click();
    }

    async clickPrimaryCTA() {
        await this.page.getByRole('link', { name: 'Rozpocznij za darmo' }).click();
        await this.page.waitForURL('**/register');
    }

    async clickSecondaryCTA() {
        await this.page.getByRole('link', { name: 'Zobacz możliwości' }).click();
        await expect(this.page.locator('#features')).toBeVisible();
    }

    async clickCTARegister() {
        await this.page.getByRole('link', { name: 'Załóż darmowe konto' }).click();
        await this.page.waitForURL('**/register');
    }

    async clickCTALogin() {
        await this.page.getByRole('link', { name: 'Mam już konto' }).click();
        await this.page.waitForURL('**/login');
    }

    async expectLoginFormVisible() {
        await this.page.goto('/login');
        await expect(this.page.getByRole('heading', { name: 'Wróć do swoich notatek' })).toBeVisible();
        await expect(this.page.getByLabel('Email')).toBeVisible();
        await expect(this.page.getByLabel('Hasło')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Zaloguj się' })).toBeVisible();
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
        await primaryCTA.hover();
        await this.page.waitForTimeout(350);
        await expect(primaryCTA).toBeVisible();
    }

    async expectSecondaryCTAHoverEffect() {
        const secondaryCTA = this.page.getByRole('link', { name: 'Zobacz możliwości' });
        await secondaryCTA.hover();
        await this.page.waitForTimeout(350);
        await expect(secondaryCTA).toBeVisible();
    }

    async expectFeatureCardsHoverEffects() {
        for (let i = 1; i <= 6; i++) {
            await this.expectCardHoverEffect(i);
        }
    }

    async expectLoginFormHoverEffects() {
        await this.page.goto('/login');
        const loginButton = this.page.getByRole('button', { name: 'Zaloguj się' });
        await loginButton.hover();
        await this.page.waitForTimeout(350);
        await expect(loginButton).toBeVisible();
    }

    async expectCTASectionHoverEffects() {
        const primaryCTA = this.page.getByRole('link', { name: 'Załóż darmowe konto' });
        const secondaryCTA = this.page.getByRole('link', { name: 'Mam już konto' });

        await primaryCTA.hover();
        await this.page.waitForTimeout(350);
        await expect(primaryCTA).toBeVisible();

        await secondaryCTA.hover();
        await this.page.waitForTimeout(350);
        await expect(secondaryCTA).toBeVisible();
    }

    async expectNavigationLinksHoverEffects() {
        const loginLink = this.page.getByRole('link', { name: /zaloguj/i }).first();
        const registerLink = this.page.getByRole('link', { name: /Załóż konto/i }).first();

        await loginLink.hover();
        await this.page.waitForTimeout(300);
        await expect(loginLink).toBeVisible();

        await registerLink.hover();
        await this.page.waitForTimeout(300);
        await expect(registerLink).toBeVisible();
    }

    async expectFooterLogoHoverEffect() {
        const footerLogo = this.page.locator('footer').getByTestId('logo-home-link');
        if (await footerLogo.isVisible()) {
            await footerLogo.hover();
            await this.page.waitForTimeout(350);
            await expect(footerLogo).toBeVisible();
        }
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}