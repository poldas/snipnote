import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { PublicNotePage } from '../page-objects/PublicNotePage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Visibility - Draft', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('vis_draft');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test('should deny all public access to draft notes', async ({ page, context }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Draft Note ${Date.now()}`;

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Draft Content');
        await editorPage.setVisibility('draft');
        await editorPage.save();
        
        await dashboardPage.clickEditNote(title);
        const publicUrl = await editorPage.getPublicUrl();

        // Check as Owner (Should NOT see via public URL)
        await page.goto(publicUrl);
        const publicPage = new PublicNotePage(page);
        await publicPage.expectErrorVisible();

        // Check as Anonymous (Should NOT see)
        const anonPage = await context.browser()!.newPage();
        await anonPage.goto(publicUrl);
        await publicPage.expectErrorVisible();
        await anonPage.close();
    });
});
