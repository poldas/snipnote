import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Editing & Collaboration', () => {
    test('should update note title and add a collaborator', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const uniqueId = Date.now().toString() + Math.random().toString(36).substring(2, 8);
        const initialTitle = `Original Title ${uniqueId}`;
        const updatedTitle = `Updated Title ${uniqueId}`;

        // Setup: create a note
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(initialTitle);
        await editorPage.fillDescription('Desc');
        await editorPage.save();

        // Act: Edit
        await dashboardPage.clickEditNote(initialTitle);
        await editorPage.fillTitle(updatedTitle);
        await editorPage.addCollaborator('collab@example.com');
        await editorPage.setVisibility('draft');
        await editorPage.save();

        // Assert
        await dashboardPage.goto(); // Force refresh to get latest data
        await dashboardPage.expectNoteVisible(updatedTitle);
        const card = await dashboardPage.getNoteCard(updatedTitle);
        await expect(card.getByText(/Draft/i)).toBeVisible({ timeout: 15000 });
    });
});