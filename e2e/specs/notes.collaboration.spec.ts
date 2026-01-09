import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { PublicNotePage } from '../page-objects/PublicNotePage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Collaboration & Sharing', () => {
    let ownerEmail: string;
    let collaboratorEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        ownerEmail = UserFactory.generateEmail('owner');
        collaboratorEmail = UserFactory.generateEmail('collab');
        
        await UserFactory.create(ownerEmail, userPass);
        await UserFactory.create(collaboratorEmail, userPass);
        
        // Ensure both users are fully committed to DB
        await new Promise(resolve => setTimeout(resolve, 1000));
    });

    test.afterAll(async () => {
        await UserFactory.delete(ownerEmail);
        await UserFactory.delete(collaboratorEmail);
    });

    test('should share private note and verify collaborator access', async ({ page, context }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const uniqueId = Math.random().toString(36).substring(2, 8);
        const noteTitle = `Collab Note ${uniqueId}`;
        const content = 'Test collaboration content';

        // --- Step 1: Owner creates a PRIVATE note ---
        await loginPage.goto();
        await loginPage.login(ownerEmail, userPass);
        await dashboardPage.expectPageLoaded();

        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(noteTitle);
        await editorPage.fillDescription(content);
        await editorPage.setVisibility('private');
        await editorPage.save();
        
        // --- Step 2: Owner adds collaborator ---
        await dashboardPage.clickEditNote(noteTitle);
        await editorPage.waitForReady();
        await editorPage.addCollaborator(collaboratorEmail);
        await editorPage.save();
        
        // Get URL and Logout
        await dashboardPage.clickEditNote(noteTitle);
        const publicUrl = await editorPage.getPublicUrl();
        await dashboardPage.clickLogout();

        // --- Step 3: Collaborator logs in and verifies access ---
        await loginPage.login(collaboratorEmail, userPass);
        await dashboardPage.goto(); // Fresh load
        await dashboardPage.selectVisibility('shared');
        await dashboardPage.expectNoteVisible(noteTitle);
        await dashboardPage.clickLogout();

        // --- Step 4: Verify visibility for Anonymous (Should NOT see) ---
        const anonPage = await context.browser()!.newPage();
        const publicPage = new PublicNotePage(anonPage);
        await anonPage.goto(publicUrl);
        await publicPage.expectErrorVisible();
        await anonPage.close();
    });
});
