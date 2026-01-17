import { test, expect } from '@playwright/test';
import { RegisterPage } from '../page-objects/RegisterPage';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { MailpitClient } from '../helpers/MailpitClient';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Authentication - Registration Flow', () => {
    const mailpit = new MailpitClient();
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        // Clean mailbox before starting
        await mailpit.deleteAllMessages();
    });

    test.afterAll(async () => {
        if (userEmail) {
            await UserFactory.delete(userEmail);
        }
        await mailpit.deleteAllMessages();
    });

    test('successful registration with email verification (Green Path)', async ({ page }) => {
        const registerPage = new RegisterPage(page);
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        
        userEmail = UserFactory.generateEmail('newuser');

        // Step 1: Fill registration form
        await registerPage.goto();
        await registerPage.register(userEmail, userPass);

        // Step 2: Expect redirect to verification notice
        await registerPage.expectVerificationNotice();

        // Step 3: Fetch verification link from Mailpit
        await page.waitForTimeout(3000); // Wait for the email
        let verificationLink = await mailpit.extractLinkFromEmail(
            userEmail, 
            /https?:\/\/[^\/]+\/verify\/email\?[^"'\s<>]+/
        );
        // Ensure all HTML entities are decoded
        verificationLink = verificationLink.replace(/&amp;/g, '&');

        // Step 4: Follow verification link
        await page.goto(verificationLink);

        // Step 5: Should be redirected to login with success message
        await expect(page).toHaveURL(/\/login/);
        await expect(page.locator('body')).toContainText(/potwierdzony/i);

        // Step 6: Log in with newly created account
        await loginPage.login(userEmail, userPass);
        await dashboardPage.expectPageLoaded();
        await dashboardPage.expectUserLoggedIn(userEmail);
    });
});
