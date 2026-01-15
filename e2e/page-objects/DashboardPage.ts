import { expect, Page } from '@playwright/test';
import { SearchComponent } from './components/SearchComponent';
import { DeleteModal } from './components/DeleteModal';
import { ToastComponent } from './components/ToastComponent';

/**
 * Dashboard page (/notes) - authenticated user dashboard with notes.
 */
export class DashboardPage {
    readonly search: SearchComponent;
    readonly deleteModal: DeleteModal;
    readonly toast: ToastComponent;

    constructor(private readonly page: Page) {
        this.search = new SearchComponent(page);
        this.deleteModal = new DeleteModal(page);
        this.toast = new ToastComponent(page);
    }

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
        // Use toast component instead of waitForTimeout
        await this.toast.expectSuccess(/Skopiowano/i);
    }

    async clickEditNote(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-edit-btn').click({ force: true });
    }

    async clickDeleteNote(title: string) {
        const card = await this.getNoteCard(title);
        await card.getByTestId('note-delete-btn').click({ force: true });
    }

    // Delegated methods
    async cancelDelete() {
        await this.deleteModal.cancel();
    }

    async clickLogout() {
        // Find logout button in the header (desktop or mobile)
        const logoutBtn = this.page.locator('button').filter({ hasText: /Wyloguj/i }).filter({ visible: true }).first();
        await logoutBtn.click();
    }

    async selectVisibility(visibility: 'owner' | 'public' | 'private' | 'draft' | 'shared') {
        await this.search.selectVisibility(visibility);
        await this.expectPageLoaded();
    }

    async searchFor(query: string) {
        await this.search.searchFor(query);
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
        await this.search.clearFilters();
    }

    async takeScreenshot(name: string) {
        await this.page.screenshot({ path: `e2e/artifacts/screenshots/${name}.png`, fullPage: true });
    }
}