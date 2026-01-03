import { expect, test } from '@playwright/test';
import { LandingPage } from '../../page-objects/LandingPage';

test.describe('Feature Cards Hover Effects', () => {
    test('all feature cards show border and shadow changes on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectFeaturesSectionVisible();

        // Act & Assert
        await landingPage.expectFeatureCardsHoverEffects();
    });

    test('feature card 1 hover effect works', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectFeaturesSectionVisible();

        // Act
        const card = page.locator('[data-testid="feature-card-1"]');
        await card.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/hover:shadow-xl/);
    });

    test('feature card 2 hover effect works', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectFeaturesSectionVisible();

        // Act
        const card = page.locator('[data-testid="feature-card-2"]');
        await card.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/hover:shadow-xl/);
    });

    test('feature card 3 hover effect works', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectFeaturesSectionVisible();

        // Act
        const card = page.locator('[data-testid="feature-card-3"]');
        await card.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(card).toBeVisible();
        await expect(card).toHaveClass(/hover:shadow-xl/);
    });
});
