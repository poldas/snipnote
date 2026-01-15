import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Dashboard Interactions', () => {
    // Note: The fixture creates a NEW user for each test, so the dashboard is guaranteed to be empty initially.

    test('should show empty state initially', async ({ authedPage: page }) => {
        await expect(page.getByText('Nie ma jeszcze notatek')).toBeVisible();
    });

    test('should handle cancel delete', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const uniqueId = Date.now().toString();
        const title = `Interaction Test ${uniqueId}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Desc');
        await editorPage.save();

        // Cancel Delete
        await dashboardPage.clickDeleteNote(title);
        await dashboardPage.cancelDelete();
        await dashboardPage.expectNoteVisible(title);
    });
});