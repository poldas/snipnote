import { Page, expect } from '@playwright/test';

export class ResetPasswordPage {
    constructor(private readonly page: Page) { }

    async reset(password: string) {
        await this.page.getByTestId('input-password').fill(password);
        await this.page.getByTestId('input-passwordConfirm').fill(password);
        await this.page.getByTestId('reset-submit-btn').click();
    }

    async expectSuccessMessage() {
        await expect(this.page).toHaveURL(/\/login/);
        await expect(this.page.locator('body')).toContainText(/hasło zostało zmienione/i);
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png` });
    }
}
