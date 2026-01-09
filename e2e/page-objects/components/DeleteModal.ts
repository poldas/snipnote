import { Page } from '@playwright/test';

export class DeleteModal {
    constructor(private readonly page: Page) {}

    async confirm() {
        // Wait for modal to be fully visible and interactive
        const btn = this.page.getByTestId('modal-confirm-btn').first();
        await btn.waitFor({ state: 'visible' });
        await btn.click();
    }

    async cancel() {
        const btn = this.page.getByTestId('modal-cancel-btn').first();
        await btn.waitFor({ state: 'visible' });
        await btn.click();
    }
}
