import { expect, Page } from '@playwright/test';

export class SearchComponent {
    constructor(private readonly page: Page) {}

    async searchFor(query: string) {
        const input = this.page.locator('input[name="q"]');
        await input.fill(query);
        await this.page.getByRole('button', { name: 'Szukaj' }).click();
        
        if (query) {
            // Use a more flexible regex that accounts for other parameters and the full URL
            const escaped = encodeURIComponent(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp(`q=${escaped.split('%20').join('(\\+|%20)')}`);
            await expect(this.page).toHaveURL(pattern);
        }
    }

    async selectVisibility(visibility: 'owner' | 'public' | 'private' | 'draft' | 'shared') {
        await this.page.locator('#visibility').selectOption(visibility);
        await expect(this.page).toHaveURL(new RegExp(`visibility=${visibility}`));
    }

    async clearFilters() {
        await this.page.getByRole('link', { name: 'Wyczyść filtry' }).click();
    }
}
