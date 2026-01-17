import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Editor Accessibility', () => {

    test('should auto-focus title field on new note page', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await dashboardPage.clickAddNote();
        
        // Wait for potential animation/JS
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(100); 

        // Check focus
        await expect(page.locator('input[name="title"]')).toBeFocused();
    });

    test('should follow logical tab order in create mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.clickAddNote();
        await page.waitForLoadState('domcontentloaded');

        // 1. Start at Title (tabindex=1)
        await expect(page.locator('input[name="title"]')).toBeFocused();

        // 2. Tab -> Description (tabindex=2)
        await page.keyboard.press('Tab');
        await expect(page.locator('textarea[name="description"]')).toBeFocused();

        // 3. Tab -> Tags (tabindex=3)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[data-tag-input]')).toBeFocused();

        // 4. Tab -> Visibility (tabindex=4)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[id="visibility-private"]')).toBeFocused();
    });

    test('should follow logical tab order in edit mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // Create a note first
        const title = `Access Note ${Date.now()}`;
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content');
        await editorPage.save();

        // Go to Edit
        await dashboardPage.clickEditNote(title);
        
        // Wait for load
        await page.waitForLoadState('domcontentloaded');

        // 1. Title (tabindex=1)
        await expect(page.locator('input[name="title"]')).toBeFocused();

        // 2. Tab -> Description (tabindex=2)
        await page.keyboard.press('Tab');
        await expect(page.locator('textarea[name="description"]')).toBeFocused();

        // 3. Tab -> Tags (tabindex=3)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[data-tag-input]')).toBeFocused();

        // 4. Tab -> Visibility (tabindex=4)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[id="visibility-private"]')).toBeFocused();

        // 5. Tab -> Collaborator Email (tabindex=5)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[name="email"]')).toBeFocused();

        // 6. Tab -> Danger Zone (Delete) (tabindex=6)
        await page.keyboard.press('Tab');
        await expect(page.locator('button[data-open-delete-modal]')).toBeFocused();
    });
});
