import { expect, test } from '@playwright/test';
import { LandingPage } from '../../page-objects/LandingPage';

test.describe('CTA Section Hover Effects', () => {
    test('CTA section buttons have hover animations', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectCTASectionVisible();

        // Act & Assert
        await landingPage.expectCTASectionHoverEffects();
    });

    test('CTA section primary button hover effect', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectCTASectionVisible();

        // Act
        const primaryButton = page.getByRole('link', { name: 'Załóż darmowe konto' });
        await primaryButton.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(primaryButton).toBeVisible();
        await expect(primaryButton).toBeEnabled();
    });

    test('CTA section secondary button hover effect', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectCTASectionVisible();

        // Act
        const secondaryButton = page.getByRole('link', { name: 'Mam już konto' });
        await secondaryButton.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(secondaryButton).toBeVisible();
        await expect(secondaryButton).toBeEnabled();
    });
});
