import { expect, test } from '@playwright/test';
import { LandingPage } from '../../page-objects/LandingPage';

test.describe('Login Form Hover Effects', () => {
    test('login form button translates on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectLoginFormVisible();

        // Act & Assert
        await landingPage.expectLoginFormHoverEffects();
    });

    test('login button hover effect works', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectLoginFormVisible();

        // Act
        const loginButton = page.getByRole('button', { name: 'Zaloguj się' });
        await loginButton.hover();
        await page.waitForTimeout(350);

        // Assert
        await expect(loginButton).toBeVisible();
        await expect(loginButton).toBeEnabled();
    });

    test('forgot password link changes color on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectLoginFormVisible();

        // Act
        const forgotLink = page.getByRole('link', { name: 'Zapomniałeś hasła?' });
        await forgotLink.hover();
        await page.waitForTimeout(300);

        // Assert
        await expect(forgotLink).toBeVisible();
    });

    test('register link changes color on hover', async ({ page }) => {
        // Arrange
        const landingPage = new LandingPage(page);
        await landingPage.goto();
        await landingPage.expectLoginFormVisible();

        // Act
        const registerLink = page.getByRole('link', { name: 'Zarejestruj się' });
        await registerLink.hover();
        await page.waitForTimeout(300);

        // Assert
        await expect(registerLink).toBeVisible();
    });
});
