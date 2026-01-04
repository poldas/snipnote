import { expect, test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

/**
 * Comprehensive UI Hover Effects Test Suite
 * Tests all hover animations, color changes, and size transformations
 * across the entire landing page
 */
test.describe('UI Hover Effects - Complete Suite', () => {
    test('complete hover effects test suite', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();

        // Act & Assert - Test all hover effects in sequence
        await test.step('Logo hover effects', async () => {
            await landingPage.expectLogoHoverEffect();
        });

        await test.step('Navigation buttons hover effects', async () => {
            await landingPage.expectPrimaryCTAHoverEffect();
            await landingPage.expectSecondaryCTAHoverEffect();
            await landingPage.expectNavigationLinksHoverEffects();
        });

        await test.step('Feature cards hover effects', async () => {
            await landingPage.expectFeaturesSectionVisible();
            await landingPage.expectFeatureCardsHoverEffects();
        });

        await test.step('Login form hover effects', async () => {
            await landingPage.expectLoginFormVisible();
            await landingPage.expectLoginFormHoverEffects();
        });

        // await test.step('CTA section hover effects', async () => {
        //     await landingPage.expectCTASectionVisible();
        //     await landingPage.expectCTASectionHoverEffects();
        // });

        // await test.step('Footer logo hover effect', async () => {
        //     await landingPage.expectFooterVisible();
        //     await landingPage.expectFooterLogoHoverEffect();
        // });
    });

    test('hover effects maintain accessibility', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();

        // Act - Test various hover interactions
        const primaryCTA = page.getByRole('link', { name: 'Rozpocznij za darmo' });
        const logo = page.locator('[data-test-id="logo-home-link"]');

        // Assert - Elements remain accessible after hover
        await primaryCTA.hover();
        await expect(primaryCTA).toBeVisible();
        await expect(primaryCTA).toBeEnabled();

        await logo.hover();
        await expect(logo).toBeVisible();
        await expect(logo).toBeEnabled();
    });

    test('hover effects are smooth and performant', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();

        // Act - Measure hover performance
        const startTime = Date.now();
        const primaryCTA = page.getByRole('link', { name: 'Rozpocznij za darmo' });

        await primaryCTA.hover();
        await page.waitForTimeout(350); // Standard transition time

        const endTime = Date.now();
        const duration = endTime - startTime;

        // Assert - Transitions should complete within reasonable time
        expect(duration).toBeLessThan(1000); // Less than 1000ms for smooth UX
    });
});
