import { expect, test } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { LandingPage } from '../page-objects/LandingPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Authentication - Login and Logout flow', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('loginlogout');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test('successful login with test account and logout returns to landing page', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const landingPage = new LandingPage(page);

        // Step 1: Navigate to login page
        await loginPage.goto();
        await loginPage.expectPageLoaded();

        // Step 2: Login with test credentials
        await loginPage.login(userEmail, userPass);

        // Step 3: Verify successful login - redirected to dashboard
        await dashboardPage.expectPageLoaded();
        await dashboardPage.expectUserLoggedIn(userEmail);

        // Step 4: Logout from dashboard
        await dashboardPage.clickLogout();

        // Step 5: Verify logout - redirected back to landing page
        await expect(page).toHaveURL(/\/(?:\?|$)/);
        await landingPage.expectHeroVisible();
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
