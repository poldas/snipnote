import { test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';
import { LoginPage } from '../page-objects/LoginPage';
import { RegisterPage } from '../page-objects/RegisterPage';
import { ForgotPasswordPage } from '../page-objects/ForgotPasswordPage';
import { VerifyNoticePage } from '../page-objects/VerifyNoticePage';

test.describe('Auth visual regression', () => {
    test('landing page visual check', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();
        await landing.takeScreenshot('landing-fullpage');
    });

    test('login page visual check', async ({ page }) => {
        const login = new LoginPage(page);

        await login.goto();
        await login.takeScreenshot('login-page');
    });

    test('register page visual check', async ({ page }) => {
        const register = new RegisterPage(page);

        await register.goto();
        await register.takeScreenshot('register-page');
    });

    test('forgot password page visual check', async ({ page }) => {
        const forgot = new ForgotPasswordPage(page);

        await forgot.goto();
        await forgot.takeScreenshot('forgot-password-page');
    });

    test('verify notice page visual check', async ({ page }) => {
        const verify = new VerifyNoticePage(page);

        await verify.goto('pending');
        await verify.takeScreenshot('verify-notice-pending');

        await verify.goto('success');
        await verify.takeScreenshot('verify-notice-success');
    });

    test('hover effects screenshots', async ({ page }) => {
        const landing = new LandingPage(page);

        await landing.goto();

        // Initial state
        await landing.takeScreenshot('landing-initial');

        // Hover logo
        await page.getByTestId('logo-home-link').first().hover();
        await page.waitForTimeout(500);
        await landing.takeScreenshot('landing-logo-hover');

        // Hover secondary button
        await page.getByRole('link', { name: 'Zobacz możliwości' }).hover();
        await page.waitForTimeout(500);
        await landing.takeScreenshot('landing-secondary-button-hover');

        // Hover CTA button
        await page.getByRole('link', { name: 'Załóż darmowe konto' }).hover();
        await page.waitForTimeout(500);
        await landing.takeScreenshot('landing-cta-button-hover');
    });
});
