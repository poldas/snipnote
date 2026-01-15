import { test, expect } from '@playwright/test';
import { ForgotPasswordPage } from '../page-objects/ForgotPasswordPage';
import { RegisterPage } from '../page-objects/RegisterPage';
import { MailpitClient } from '../helpers/MailpitClient';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Forgot Password Advanced Scenarios', () => {
    const mailpit = new MailpitClient();
    const userPass = 'StrongPass123!';

    test.beforeEach(async () => {
        await mailpit.deleteAllMessages();
    });

    test('unverified user should receive verification link instead of reset link', async ({ page }) => {
        const registerPage = new RegisterPage(page);
        const forgotPage = new ForgotPasswordPage(page);
        const userEmail = UserFactory.generateEmail('unverified');

        // Step 1: Register but DO NOT verify
        await registerPage.goto();
        await registerPage.register(userEmail, userPass);
        await registerPage.expectVerificationNotice();
        await mailpit.deleteAllMessages(); // Clear the initial reg email

        // Step 2: Request password reset
        await forgotPage.goto();
        await forgotPage.requestReset(userEmail);
        await forgotPage.expectSuccessMessage();

        // Step 3: Check Mailpit - should contain verification link, not reset link
        await page.waitForTimeout(2000);
        const message = await mailpit.getLatestMessageFor(userEmail);
        expect(message).not.toBeNull();
        
        const body = message.HTML || message.Text;
        expect(body).toContain('/verify/email');
        expect(body).not.toContain('/reset-password');
        
        await UserFactory.delete(userEmail);
    });

    test('verified user should receive reset password link', async ({ page }) => {
        const forgotPage = new ForgotPasswordPage(page);
        const userEmail = UserFactory.generateEmail('verified');
        
        // Step 1: Create a pre-verified user via factory (it creates verified users by default)
        await UserFactory.create(userEmail, userPass);

        // Step 2: Request password reset
        await forgotPage.goto();
        await forgotPage.requestReset(userEmail);
        await forgotPage.expectSuccessMessage();

        // Step 3: Check Mailpit - should contain reset link
        await page.waitForTimeout(2000);
        const message = await mailpit.getLatestMessageFor(userEmail);
        expect(message).not.toBeNull();
        
        const body = message.HTML || message.Text;
        expect(body).toContain('/reset-password');
        
        await UserFactory.delete(userEmail);
    });

    test('non-existent account should not trigger any email', async ({ page }) => {
        const forgotPage = new ForgotPasswordPage(page);
        const nonExistentEmail = `notfound_${Date.now()}@example.test`;

        // Step 1: Request reset for non-existent email
        await forgotPage.goto();
        await forgotPage.requestReset(nonExistentEmail);
        await forgotPage.expectSuccessMessage(); // System should still say "success"

        // Step 2: Wait and check Mailpit - should be empty for this recipient
        await page.waitForTimeout(3000);
        const message = await mailpit.getLatestMessageFor(nonExistentEmail);
        expect(message).toBeNull();
    });
});
