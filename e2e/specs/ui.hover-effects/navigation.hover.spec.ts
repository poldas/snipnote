import { expect, test } from '@playwright/test';
import { LandingPage } from '../../page-objects/LandingPage';

test.describe('Navigation Hover Effects', () => {
    test('navigation links change color on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectHeroVisible();

        // Act & Assert
        await landingPage.expectNavigationLinksHoverEffects();
    });

    test('primary CTA button translates up on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectHeroVisible();

        // Act & Assert
        await landingPage.expectPrimaryCTAHoverEffect();
    });

    test('secondary CTA button scales and shows blur on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectHeroVisible();

        // Act & Assert
        await landingPage.expectSecondaryCTAHoverEffect();
    });
});
