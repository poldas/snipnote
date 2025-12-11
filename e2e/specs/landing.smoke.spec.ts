import { test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing smoke', () => {
    test('opens landing page', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectHeroVisible();
    });
});

