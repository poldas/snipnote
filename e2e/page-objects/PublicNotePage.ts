import { expect, Page } from '@playwright/test';

/**
 * Public Note view page (/n/{urlToken}).
 */
export class PublicNotePage {
    constructor(private readonly page: Page) { }

    async expectTitle(title: string) {
        // Use containText because some themes might add emojis/icons to the h1
        await expect(this.page.getByTestId('public-note-title')).toContainText(title);
    }

    async expectDefaultTheme() {
        // Default theme uses standard header and common layout
        await expect(this.page.locator('.pn-card')).toBeVisible();
        await expect(this.page.locator('.pn-meta-row')).toBeVisible();
        // Should NOT have todo-specific elements
        await expect(this.page.locator('.todo-header')).not.toBeVisible();
    }

    async expectTodoTheme() {
        // TODO theme has a specific header and stats section
        await expect(this.page.locator('.todo-header')).toBeVisible();
        await expect(this.page.locator('.todo-stats')).toBeVisible();
        await expect(this.page.locator('.todo-add-section')).toBeVisible();
        await expect(this.page.getByTestId('public-note-content')).toHaveAttribute('data-public-todo-target', 'markdownContent');
    }

    async expectRecipeTheme() {
        // Recipe theme loads recipe_view.css
        await expect(this.page.locator('.pn-card')).toBeVisible();
        // Check for stylesheet in head - use a more robust check for AssetMapper filenames
        const head = this.page.locator('head');
        await expect(this.page.locator('link[rel="stylesheet"][href*="recipe_view"]')).toHaveCount(1);
    }

    async expectErrorVisible() {
        await expect(this.page.locator('#public-note-shell')).toBeVisible();
        await expect(this.page.locator('h2')).toContainText(/Notatka niedostępna/i);
    }
}
