import { expect, test } from '@playwright/test';
import { LandingPage } from '../../page-objects/LandingPage';

test.describe('Logo Hover Effects', () => {
    test('logo scales and shows blur effect on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectHeroVisible();

        // Act & Assert
        await landingPage.expectLogoHoverEffect();
    });

    test('footer logo has hover animation', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectFooterVisible();

        // Act & Assert
        await landingPage.expectFooterLogoHoverEffect();
    });
});
