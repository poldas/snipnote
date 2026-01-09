import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { PublicNotePage } from '../page-objects/PublicNotePage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Visibility - Private', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('vis_priv');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test('should restrict access to private notes', async ({ page, context }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Private Note ${Date.now()}`;

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Private Content');
        await editorPage.setVisibility('private');
        await editorPage.save();
        
        await dashboardPage.clickEditNote(title);
        const publicUrl = await editorPage.getPublicUrl();

        // Check as Owner (Should see)
        await page.goto(publicUrl);
        await expect(page.getByTestId('public-note-title')).toContainText(title);

        // Check as Anonymous (Should NOT see)
        const anonPage = await context.browser()!.newPage();
        const publicPage = new PublicNotePage(anonPage);
        await anonPage.goto(publicUrl);
        await publicPage.expectErrorVisible();
        await anonPage.close();
    });
});
