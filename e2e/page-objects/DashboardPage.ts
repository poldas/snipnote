import { expect, Page } from '@playwright/test';

/**
 * Dashboard page (/notes) - authenticated user dashboard with notes.
 */
export class DashboardPage {
    constructor(private readonly page: Page) { }

    async goto() {
        const response = await this.page.goto('/notes');
        expect(response?.ok()).toBeTruthy();
    }

    async expectPageLoaded() {
        await expect(this.page).toHaveURL(/\/notes/);
        // Logo link should be visible in either mobile or desktop version
        await expect(this.page.getByTestId('logo-home-link').filter({ visible: true }).first()).toBeVisible();
        // The button text might be split or have hidden spans for responsiveness, use a more flexible matcher
        // Try finding by text directly if role + name is tricky with hidden spans
        await expect(this.page.locator('button').filter({ hasText: /Dodaj/ }).filter({ visible: true }).first()).toBeVisible();
    }

    async expectUserLoggedIn(email: string) {
        // Look specifically in the header area, not in debug toolbar
        // Might have multiple instances (mobile/desktop), pick visible one
        await expect(this.page.locator('header').getByText(email).filter({ visible: true }).first()).toBeVisible();
    }

    async clickAddNote() {
        await this.page.locator('button').filter({ hasText: /Dodaj/ }).filter({ visible: true }).first().click();
    }

    async getNoteCard(title: string) {
        return this.page.locator('div.group', { has: this.page.getByRole('heading', { name: title }) });
    }

    async expectNoteVisible(title: string, excerpt?: string) {
        const card = await this.getNoteCard(title);
        await expect(card).toBeVisible();
        if (excerpt) {
            await expect(card.getByText(excerpt)).toBeVisible();
        }
    }

    async clickOpenNote(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-open-link').click({ force: true });
    }

    async clickCopyLink(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-copy-link-btn').click({ force: true });
        // Brief wait for toast to appear
        await this.page.waitForTimeout(500);
    }

    async clickEditNote(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-edit-btn').click({ force: true });
    }

    async clickDeleteNote(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-delete-btn').click({ force: true });
    }

    async cancelDelete() {
        await this.page.getByTestId('modal-cancel-btn').first().click();
    }

    async clickLogout() {
        // Find logout button in the header (desktop or mobile)
        const logoutBtn = this.page.locator('button').filter({ hasText: /Wyloguj/i }).filter({ visible: true }).first();
        await logoutBtn.click();
    }

    async selectVisibility(visibility: 'owner' | 'public' | 'private' | 'draft' | 'shared') {
        await this.page.locator('#visibility').selectOption(visibility);
        // The change triggers a form submit, so we wait for the page to reload/update
        await expect(this.page).toHaveURL(new RegExp(`visibility=${visibility}`));
        await this.expectPageLoaded();
    }

    async searchFor(query: string) {
        const input = this.page.locator('input[name="q"]');
        await input.fill(query);
        await this.page.getByRole('button', { name: 'Szukaj' }).click();
        
        // Wait for reload (URL change or search params update)
        if (query) {
            // Use a more flexible regex that accounts for other parameters and the full URL
            const escaped = encodeURIComponent(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp(`q=${escaped.replace(/%20/g, '(\\+|%20)')}`);
            await expect(this.page).toHaveURL(pattern);
        }
    }

    async expectSearchResultsCount(count: number) {
        // Wait for the grid to stabilize
        await expect(this.page.locator('div.grid > div.group')).toHaveCount(count);
    }

    async expectEmptySearchResult() {
        await expect(this.page.getByText('Brak wyników')).toBeVisible();
        await expect(this.page.getByText('Nie znaleziono notatek dla podanych filtrów')).toBeVisible();
    }

    async clickClearFilters() {
        await this.page.getByRole('link', { name: 'Wyczyść filtry' }).click();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}
