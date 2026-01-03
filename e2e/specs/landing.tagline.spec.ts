import { test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing page content', () => {
    test('shows main hero section with collaboration tagline', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectHeroVisible();
        await landing.expectHeroSubtitleVisible();
    });

    test('shows all main sections', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectFeaturesSectionVisible();
        await landing.expectHowItWorksSectionVisible();
        await landing.expectCTASectionVisible();
        await landing.expectFooterVisible();
    });

    test('shows login form in hero section', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectLoginFormVisible();
    });
});

