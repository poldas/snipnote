import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Editor Shortcuts', () => {
    
    test('should save note on Ctrl+S and redirect', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const title = `Shortcut Note ${Date.now()}`;
        
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content saved via shortcut');
        
        // Trigger Ctrl+S
        await page.keyboard.press('Control+s');
        
        // Should show success toast
        await expect(page.getByText('Notatka utworzona')).toBeVisible();
        
        // Should redirect to dashboard (or list)
        await expect(page).toHaveURL(/\/notes/);
        
        // Verify existence
        await expect(page.getByText(title)).toBeVisible();
    });

    test('should save changes on Ctrl+S in edit mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        // Create note first
        const title = `Edit Shortcut ${Date.now()}`;
        
        // Manual creation flow instead of non-existent createNote helper
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Initial content');
        await editorPage.save();
        
        // Go to edit
        await dashboardPage.clickEditNote(title);
        
        // Change content
        await editorPage.fillDescription('Updated content via shortcut');
        
        // Trigger Ctrl+S (Meta+S for Mac, but Ctrl+S works usually in Playwright)
        // Let's try Meta+S as well just in case of MacOS runner
        if (process.platform === 'darwin') {
            await page.keyboard.press('Meta+s');
        } else {
            await page.keyboard.press('Control+s');
        }
        
        // Should show success toast
        await expect(page.getByText('Zapisano zmiany')).toBeVisible();
        
        // Should NOT redirect (remain on edit page)
        await expect(page).toHaveURL(/\/edit/);
    });
});
