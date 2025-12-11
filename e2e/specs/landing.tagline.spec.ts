import { test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing tagline', () => {
    test('shows main collaboration tagline', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.expectTaglineVisible();
    });
});

