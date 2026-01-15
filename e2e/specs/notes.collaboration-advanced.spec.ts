import { test, expect } from '@playwright/test';
import { UserFactory } from '../helpers/UserFactory';
import { LoginPage } from '../page-objects/LoginPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { DashboardPage } from '../page-objects/DashboardPage';

test.describe('Advanced Collaboration', () => {
    let ownerEmail: string;
    let collabEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        ownerEmail = UserFactory.generateEmail('owner');
        collabEmail = UserFactory.generateEmail('collab');
        await UserFactory.create(ownerEmail, userPass);
        await UserFactory.create(collabEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(ownerEmail);
        await UserFactory.delete(collabEmail);
    });

    test('Collaborator can remove themselves from a note', async ({ browser }) => {
        const title = 'Shared Note for Self Removal';
        
        // --- Setup: Owner creates note and adds collaborator ---
        const ownerContext = await browser.newContext();
        const ownerPage = await ownerContext.newPage();
        const ownerLogin = new LoginPage(ownerPage);
        const ownerDashboard = new DashboardPage(ownerPage);
        const ownerEditor = new NoteEditorPage(ownerPage);

        await ownerLogin.goto();
        await ownerLogin.login(ownerEmail, userPass);
        
        // Create Note
        await ownerDashboard.clickAddNote();
        await ownerEditor.fillTitle(title);
        await ownerEditor.fillDescription('Content');
        await ownerEditor.save();

        // Re-open for editing to add collaborator
        await ownerDashboard.clickEditNote(title);
        await ownerEditor.addCollaborator(collabEmail);
        await ownerContext.close();

        // --- Act: Collaborator removes self ---
        const collabContext = await browser.newContext();
        const collabPage = await collabContext.newPage();
        const collabLogin = new LoginPage(collabPage);
        const collabDashboard = new DashboardPage(collabPage);
        
        await collabLogin.goto();
        await collabLogin.login(collabEmail, userPass);
        
        // Go to "Shared with me" (For Me) to find the note easily
        await collabDashboard.selectVisibility('shared');
        await collabDashboard.clickEditNote(title);

        // Inside Editor - find self row and click remove
        const removeSelfBtn = collabPage.locator('[data-collaborator-row][data-collaborator-email="' + collabEmail.toLowerCase() + '"] [data-remove-collaborator]');
        await expect(removeSelfBtn).toBeVisible();
        await removeSelfBtn.click();

        // Confirm in Modal
        const modalConfirmBtn = collabPage.locator('[data-modal-confirm]');
        await modalConfirmBtn.click();

        // --- Assert: Redirected to dashboard and note is gone ---
        await expect(collabPage).toHaveURL(/\/notes/);
        await collabDashboard.selectVisibility('shared');
        
        // Verify note is gone from the list
        const card = await collabDashboard.getNoteCard(title);
        await expect(card).not.toBeVisible();

        await collabContext.close();
    });

    test('Owner can remove a collaborator', async ({ browser }) => {
        const title = 'Shared Note for Owner Removal';

        // --- Setup: Owner creates note and adds collaborator ---
        const ownerContext = await browser.newContext();
        const ownerPage = await ownerContext.newPage();
        const ownerLogin = new LoginPage(ownerPage);
        const ownerDashboard = new DashboardPage(ownerPage);
        const ownerEditor = new NoteEditorPage(ownerPage);

        await ownerLogin.goto();
        await ownerLogin.login(ownerEmail, userPass);
        
        // Create Note
        await ownerDashboard.clickAddNote();
        await ownerEditor.fillTitle(title);
        await ownerEditor.fillDescription('Content');
        await ownerEditor.save();

        // Re-open for editing to add collaborator
        await ownerDashboard.clickEditNote(title);
        await ownerEditor.addCollaborator(collabEmail);

        // --- Act: Owner removes collaborator ---
        // Verify collaborator row exists
        const collabRow = ownerPage.locator('[data-collaborator-row][data-collaborator-email="' + collabEmail.toLowerCase() + '"]');
        await expect(collabRow).toBeVisible();

        // Click Remove
        const removeBtn = collabRow.locator('[data-remove-collaborator]');
        await removeBtn.click();

        // Confirm in Modal
        const modalConfirmBtn = ownerPage.locator('[data-modal-confirm]');
        await expect(modalConfirmBtn).toBeVisible();
        await modalConfirmBtn.click();

        // --- Assert: Collaborator row disappears ---
        await expect(collabRow).not.toBeVisible();
        
        // Verify "Empty" message or just absence
        // The list might verify "Brak współpracowników" if empty, but checking row absence is safer
        
        await ownerContext.close();
    });
});
