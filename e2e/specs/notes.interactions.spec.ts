import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Dashboard Interactions', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('actions');
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

    test('should show empty state initially', async ({ page }) => {
        await expect(page.getByText('Nie ma jeszcze notatek')).toBeVisible();
    });

    test('should handle cancel delete', async ({ page }) => {
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
