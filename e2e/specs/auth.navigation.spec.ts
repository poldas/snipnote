import { expect, test } from '@playwright/test';
import { LandingPage } from '../page-objects/LandingPage';
import { RegisterPage } from '../page-objects/RegisterPage';
import { LoginPage } from '../page-objects/LoginPage';
import { ForgotPasswordPage } from '../page-objects/ForgotPasswordPage';

test.describe('Auth navigation links', () => {
    test('walks through auth links and back home', async ({ page }) => {
        const landing = new LandingPage(page);
        const register = new RegisterPage(page);
        const login = new LoginPage(page);
        const forgot = new ForgotPasswordPage(page);

        await landing.goto();

        await landing.clickRegisterLink();
        await expect(page).toHaveURL(/\/register(?:\?|$)/);

        await register.clickLoginLink();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        await login.clickForgotPasswordLink();
        await expect(page).toHaveURL(/\/forgot-password(?:\?|$)/);

        await forgot.clickLoginLink();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);

        await login.clickLogoHome();
        await expect(page).toHaveURL(/\/(?:\?|$)/);
    });
});

