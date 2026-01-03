import { expect, test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';
import { RegisterPage } from '../page-objects/RegisterPage';
import { LoginPage } from '../page-objects/LoginPage';
import { ForgotPasswordPage } from '../page-objects/ForgotPasswordPage';
import { VerifyNoticePage } from '../page-objects/VerifyNoticePage';

test.describe('Auth navigation and forms', () => {
    test('walks through all auth pages navigation', async ({ page }) => {
        const landing = new LandingPage(page);
        const register = new RegisterPage(page);
        const login = new LoginPage(page);
        const forgot = new ForgotPasswordPage(page);
        const verify = new VerifyNoticePage(page);

        // Start from landing page
        await landing.goto();
        await landing.expectHeroVisible();

        // Test header navigation links
        await landing.goToLogin();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);
        await login.expectPageLoaded();

        await login.clickLogoHome();
        await expect(page).toHaveURL(/\/(?:\?|$)/);

        // Test CTA buttons from landing
        await landing.clickCTARegister();
        await expect(page).toHaveURL(/\/register(?:\?|$)/);
        await register.expectPageLoaded();

        await register.clickLogoHome();
        await expect(page).toHaveURL(/\/(?:\?|$)/);

        await landing.clickCTALogin();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        // Test auth form navigation
        await login.clickRegisterLink();
        await expect(page).toHaveURL(/\/register(?:\?|$)/);

        await register.clickLoginLink();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        await login.clickForgotPasswordLink();
        await expect(page).toHaveURL(/\/forgot-password(?:\?|$)/);
        await forgot.expectPageLoaded();

        await forgot.clickLoginLink();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        // Test verify notice page (simulated)
        await verify.goto('pending', 'test@example.com');
        await verify.expectPageLoaded();
        await verify.expectPendingState();

        await verify.clickBackToLogin();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);
    });

    test('validates all auth forms have required elements', async ({ page }) => {
        const landing = new LandingPage(page);
        const register = new RegisterPage(page);
        const login = new LoginPage(page);
        const forgot = new ForgotPasswordPage(page);
        const verify = new VerifyNoticePage(page);

        // Test landing page form
        await landing.goto();
        await landing.expectLoginFormVisible();

        // Test login page
        await landing.goToLogin();
        await login.expectFormElements();

        // Test register page
        await login.clickRegisterLink();
        await register.expectFormElements();

        // Test forgot password page
        await register.clickLoginLink();
        await login.clickForgotPasswordLink();
        await forgot.expectFormElements();

        // Test verify notice page
        await verify.goto('success', 'test@example.com');
        await verify.expectFormElements();
    });

    test('tests hover effects and visual elements', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();

        // Test logo hover effect
        await landing.expectLogoHoverEffect();

        // Test button hover effects
        await landing.expectButtonHoverEffects();

        // Take screenshots for visual regression
        await landing.takeScreenshot('landing-initial');
    });

    test('tests CTA button navigation from landing', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();

        // Test primary CTA button
        await landing.clickPrimaryCTA();
        await expect(page).toHaveURL(/\/register(?:\?|$)/);

        // Go back and test secondary CTA
        await page.goto('/');
        await landing.clickSecondaryCTA();
        // Should scroll to features section - check if features section is visible
        await expect(page.locator('#features')).toBeInViewport();
    });
});

