import { test, expect } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Landing Page - Comprehensive Suite', () => {
    let landingPage: LandingPage;

    test.beforeEach(async ({ page }) => {
        landingPage = new LandingPage(page);
        await landingPage.goto();
    });

    test('should have correct structure and essential copy', async () => {
        await landingPage.expectHeroVisible();
        await landingPage.expectHeroSubtitleVisible();
        await landingPage.expectLoginFormHeadersVisible();
        await landingPage.expectBenefitTagsVisible();
        await landingPage.expectFeaturesSectionVisible();
        await landingPage.expectHowItWorksSectionVisible();
        await landingPage.expectCTASectionVisible();
        await landingPage.expectFooterVisible();
    });

    test('should display exactly 6 feature cards with correct content', async () => {
        await landingPage.expectFeatureCardsCount(6);
        
        // Verify key feature content
        await landingPage.expectFeatureCardDetails(5, 'Wspólne notatki', 'Edytuj notatki z innymi. Dodaj osobę i udostępnij link.');
        await landingPage.expectFeatureCardDetails(2, 'Bezpieczne i prywatne', 'Kontroluj dostęp do swoich notatek. Zmień widoczność w dowolnym momencie.');
    });

    test('should have correct visual identity (colors and gradients)', async () => {
        await landingPage.expectBrandVisuals();
        
        // Ensure standard UI elements like logo are correct
        const logo = landingPage.page.getByTestId('logo-home-link').first();
        await expect(logo).toBeVisible();
    });

    test('should demonstrate hover and focus animations', async () => {
        // Test primary button hover indirectly by ensuring it's interactive
        await landingPage.expectPrimaryCTAHoverEffect();
        
        // Test form input focus animations (functional check)
        await landingPage.expectInputFocusEffect('email');
    });

    test('should be responsive (mobile viewport layout)', async () => {
        // Switch to mobile viewport
        await landingPage.page.setViewportSize({ width: 375, height: 667 });
        
        // Verify mobile layout adjustments
        await landingPage.expectMobileLayout();
        
        // Benefit tags should still be visible on mobile
        await landingPage.expectBenefitTagsVisible();
    });
});