import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { PublicNotePage } from '../page-objects/PublicNotePage';

test.describe('Note Visibility & Permissions', () => {
    
    test('should allow public access to public notes', async ({ authedPage: page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Public Note ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Public Content');
        await editorPage.setVisibility('public');
        await editorPage.save();
        
        await dashboardPage.clickEditNote(title);
        const publicUrl = await editorPage.getPublicUrl();

        // Check as Anonymous
        const anonPage = await context.browser()!.newPage();
        const publicPage = new PublicNotePage(anonPage);
        await anonPage.goto(publicUrl);
        await publicPage.expectTitle(title);
        await anonPage.close();
    });

    test('should restrict access to private notes', async ({ authedPage: page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Private Note ${Date.now()}`;

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

    test('should deny all public access to draft notes', async ({ authedPage: page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Draft Note ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Draft Content');
        await editorPage.setVisibility('draft');
        await editorPage.save();
        
        await dashboardPage.clickEditNote(title);
        const publicUrl = await editorPage.getPublicUrl();

        // Check as Owner (Should NOT see via public URL, drafts are strictly private view only via editor/preview)
        // Note: The original test expected error for Owner too, maintaining this logic.
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
