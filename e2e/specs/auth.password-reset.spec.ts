import { test, expect } from '@playwright/test';
import { ForgotPasswordPage } from '../page-objects/ForgotPasswordPage';
import { ResetPasswordPage } from '../page-objects/ResetPasswordPage';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { MailpitClient } from '../helpers/MailpitClient';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Authentication - Password Reset Flow', () => {
    const mailpit = new MailpitClient();
    let userEmail: string;
    const initialPass = 'InitialPass123!';
    const newPass = 'NewSecurePass456!';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('pwreset');
        // Create and verify user first
        await UserFactory.create(userEmail, initialPass);
        await mailpit.deleteAllMessages();
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
        await mailpit.deleteAllMessages();
    });

    test('should successfully reset password and validate new credentials', async ({ page }) => {
        const forgotPage = new ForgotPasswordPage(page);
        const resetPage = new ResetPasswordPage(page);
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);

        // Step 1: Request password reset
        await forgotPage.goto();
        await forgotPage.requestReset(userEmail);
        await forgotPage.expectSuccessMessage();

        // Step 2: Fetch reset link from Mailpit
        await page.waitForTimeout(3000);
        let resetLink = await mailpit.extractLinkFromEmail(
            userEmail,
            /https?:\/\/[^\/]+\/reset-password\/[^"'\s<>]+/
        );
        resetLink = resetLink.replace(/&amp;/g, '&');

        // Step 3: Navigate to reset link and set new password
        await page.goto(resetLink);
        await resetPage.reset(newPass);
        await resetPage.expectSuccessMessage(); // This confirms we are back on /login

        // Step 4: RED PATH - Verify old password NO LONGER works
        await loginPage.login(userEmail, initialPass);
        await expect(page.locator('body')).toContainText(/Nieprawidłowy/i);

        // Step 5: GREEN PATH - Verify new password works
        await loginPage.login(userEmail, newPass);
        await dashboardPage.expectPageLoaded();
        await dashboardPage.expectUserLoggedIn(userEmail);
    });
});