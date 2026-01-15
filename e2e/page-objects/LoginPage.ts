import { expect, Page } from '@playwright/test';

/**
 * Login page (auth layout).
 */
export class LoginPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/login');
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page.getByRole('heading', { name: 'Wróć do swoich notatek' })).toBeVisible();
        await expect(this.page.getByLabel('Adres email')).toBeVisible();
        await expect(this.page.getByLabel('Hasło')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Zaloguj się' })).toBeVisible();
    }

    async expectFormElements() {
        const form = this.page.locator('form');
        await expect(form.locator('input[name="email"]')).toBeVisible();
        await expect(form.locator('input[name="password"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Zaloguj się' })).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Zapomniałeś hasła?' }).first()).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Zarejestruj się' }).first()).toBeVisible();
    }

    async clickRegisterLink() {
        await this.page.getByRole('link', { name: 'Zarejestruj się' }).first().click();
    }

    async clickForgotPasswordLink() {
        await this.page.getByRole('link', { name: 'Zapomniałeś hasła?' }).first().click();
    }

    async clickLogoHome() {
        await this.page.getByTestId('logo-home-link').click();
    }

    async fillLoginForm(email: string, password: string) {
        await this.page.getByLabel('Adres email').fill(email);
        await this.page.getByLabel('Hasło').fill(password);
    }

    async submitLoginForm() {
        // Click the submit button instead of programmatic submit for better compatibility
        await this.page.getByRole('button', { name: 'Zaloguj się' }).click();
    }

    async login(email: string, password: string) {
        await this.fillLoginForm(email, password);

        // Listen for network responses to catch authentication issues (silent mode)
        const responsePromise = this.page.waitForResponse(response =>
            response.url().includes('/login') && response.request().method() === 'POST'
        ).catch(() => null); // Don't fail if response not captured

        await this.submitLoginForm();

        // Wait for potential redirect or page load
        await this.page.waitForTimeout(2000);
    }

    async expectLoginSuccess() {
        // After successful login, user should be redirected to /notes
        await expect(this.page).toHaveURL(/\/notes(?:\?|$)/);
    }

    async expectLoginError() {
        // Check for error message - try multiple selectors
        await expect(
            this.page.locator('.text-red-600').or(
                this.page.locator('.text-red-700')
            ).or(
                this.page.getByText('Błąd logowania')
            ).or(
                this.page.getByText('Nieprawidłowe dane logowania')
            ).or(
                this.page.getByText('Nieprawidłowy adres email lub hasło')
            )
        ).toBeVisible();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}

