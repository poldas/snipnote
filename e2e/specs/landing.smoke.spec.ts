import { test, expect } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing smoke tests', () => {
    test('opens landing page and shows all content', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectHeroVisible();
        await landing.expectHeroSubtitleVisible();
        await landing.expectFeaturesSectionVisible();
        await landing.expectHowItWorksSectionVisible();
        await landing.expectCTASectionVisible();
        await landing.expectFooterVisible();
    });

    test('all navigation links are present and clickable', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();

        // Check header navigation links exist
        await landing.goToLogin();
        await page.goto('/'); // Go back

        // Check CTA buttons exist
        await landing.clickPrimaryCTA();
        await page.goto('/'); // Go back

        await landing.clickSecondaryCTA();
        // Should scroll to features section - check if features section is visible
        await expect(page.locator('#features')).toBeInViewport();
    });
});

