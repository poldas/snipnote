import { expect, Page } from '@playwright/test';

export class ToastComponent {
    constructor(private readonly page: Page) {}

    async expectVisible(message: string | RegExp) {
        const toast = this.page.locator('#toast-stack > div').filter({ hasText: message }).first();
        await expect(toast).toBeVisible({ timeout: 10000 });
    }

    async expectSuccess(message: string | RegExp) {
        const toast = this.page.locator('#toast-stack > .bg-emerald-50, #toast-stack > .toast-success').filter({ hasText: message }).first();
        await expect(toast).toBeVisible({ timeout: 10000 });
    }

    async expectError(message: string | RegExp) {
        const toast = this.page.locator('#toast-stack > .bg-red-50, #toast-stack > .toast-error').filter({ hasText: message }).first();
        await expect(toast).toBeVisible({ timeout: 10000 });
    }
}
