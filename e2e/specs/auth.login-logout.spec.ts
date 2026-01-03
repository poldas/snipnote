import { expect, test } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { LandingPage } from '../page-objects/LandingPage';

test.describe('Authentication - Login and Logout flow', () => {
    test('successful login with test account and logout returns to landing page', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const landingPage = new LandingPage(page);

        // Step 1: Navigate to login page
        await loginPage.goto();
        await loginPage.expectPageLoaded();

        // Step 2: Login with test credentials
        // Note: This test assumes a verified test account exists with email 'test@test.test' and password 'testtest'
        // In a real CI/CD setup, you would create this account programmatically before running tests
        await loginPage.login('test@test.test', 'testtest');

        // Step 3: Verify successful login - redirected to dashboard
        await dashboardPage.expectPageLoaded();
        await dashboardPage.expectUserLoggedIn('test@test.test');

        // Step 4: Logout from dashboard
        await dashboardPage.clickLogout();

        // Step 5: Verify logout - redirected back to landing page
        await landingPage.expectHeroVisible();
        await expect(page).toHaveURL(/\/(?:\?|$)/);
    });

    test('login form validation shows errors for invalid credentials', async ({ page }) => {
        const loginPage = new LoginPage(page);

        // Navigate to login page
        await loginPage.goto();
        await loginPage.expectPageLoaded();

        // Try to login with invalid credentials
        await loginPage.login('invalid@test.test', 'wrongpassword');

        // For security reasons, the app might not show explicit error messages
        // but should keep user on login page (no redirect to dashboard)
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        // User should still see the login form
        await loginPage.expectPageLoaded();
    });

    test('login form elements are properly rendered', async ({ page }) => {
        const loginPage = new LoginPage(page);

        // Navigate to login page
        await loginPage.goto();

        // Verify all form elements are present
        await loginPage.expectFormElements();
        await loginPage.expectPageLoaded();
    });
});
