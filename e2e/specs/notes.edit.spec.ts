import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Editing & Collaboration', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('edit');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test.beforeEach(async ({ page }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
    });

    test('should update note title and add a collaborator', async ({ page }) => {
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
