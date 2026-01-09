import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Creation - Atomic Specs', () => {
    // Note: 'authedPage' fixture automatically handles user creation, login and cleanup.
    // It provides a logged-in page instance.

    test('should create a private note successfully (Green Path)', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const uniqueId = Date.now().toString() + Math.random().toString(36).substring(2, 8);
        const title = `New Note ${uniqueId}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content of the note');
        await editorPage.save();

        await dashboardPage.expectNoteVisible(title);
    });

    test('should show validation error for empty title (Red Path)', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(''); // Empty title
        await editorPage.fillDescription('Content');
        
        // Form should not submit, stay on page
        const submitBtn = page.locator('button[data-submit-btn]');
        await expect(submitBtn).toBeEnabled();
        await submitBtn.click({ force: true });
        
        // Wait for validation summary
        const errorContainer = page.locator('[data-form-errors]');
        await expect(errorContainer).toBeVisible({ timeout: 15000 });
        await expect(errorContainer).toContainText(/Wystąpiły błędy walidacji|Tytuł jest wymagany/i);
    });
});