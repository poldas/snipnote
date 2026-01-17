import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';
import { TEST_CONFIG } from '../config/test-constants';

test.describe('Dashboard Search & Filtering', () => {
    let userEmail: string;
    let otherUserEmail: string;
    const userPass = TEST_CONFIG.DEFAULT_PASSWORD;

    // Seed Data Configuration
    const notes = [
        { title: 'Zażółć gęślą jaźń', desc: 'Polski tekst testowy', label: 'pl', visibility: 'private' },
        { title: 'Simple English Note', desc: 'Searchable content', label: 'en', visibility: 'public' },
        { title: 'Katakana テスト', desc: 'Unicode test content', label: 'jp', visibility: 'draft' },
        { title: 'Work Task Priority', desc: 'Meeting', label: 'priority_high', visibility: 'private' },
        { title: 'różowa piłka do grania w bubbleż', desc: 'Sport', label: 'sport', visibility: 'public' }
    ];

    const sharedNoteTitle = 'Shared with me note';

    test.beforeAll(async ({ browser }) => {
        userEmail = UserFactory.generateEmail('search');
        otherUserEmail = UserFactory.generateEmail('sharer');
        await UserFactory.create(userEmail, userPass);
        await UserFactory.create(otherUserEmail, userPass);

        const context = await browser.newContext();
        const page = await context.newPage();
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // --- User 1: Create notes with different visibilities ---
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        
        for (const note of notes) {
            await dashboardPage.clickAddNote();
            await editorPage.fillTitle(note.title);
            await editorPage.fillDescription(note.desc);
            await editorPage.addLabel(note.label);
            await editorPage.setVisibility(note.visibility as any);
            await editorPage.save();
            await page.waitForTimeout(500);
        }
        await dashboardPage.clickLogout();

        // --- User 2: Create a note and share it with User 1 ---
        await loginPage.login(otherUserEmail, userPass);
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(sharedNoteTitle);
        await editorPage.fillDescription('This note is shared');
        await editorPage.save();
        
        await dashboardPage.clickEditNote(sharedNoteTitle);
        await editorPage.addCollaborator(userEmail);
        await editorPage.save();

        await context.close();
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
        await UserFactory.delete(otherUserEmail);
    });

    test.beforeEach(async ({ page }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
    });

    test('should search by simple text match (English)', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.searchFor('English');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Simple English Note');
    });

    test('should search by description content', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.searchFor('content');
        
        // "content" appears in English Note (desc) and Japanese Note (desc)
        await dashboardPage.expectSearchResultsCount(2);
        await dashboardPage.expectNoteVisible('Simple English Note');
        await dashboardPage.expectNoteVisible('Katakana テスト');
    });

    test('should handle Polish characters in search', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        // Search for "gęślą"
        await dashboardPage.searchFor('gęślą');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Zażółć gęślą jaźń');
    });

    test('should handle Unicode/Japanese characters', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        // Search for "テスト" (Test)
        await dashboardPage.searchFor('テスト');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Katakana テスト');
    });

    test('should filter by label syntax', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        // Search using "label:" syntax
        await dashboardPage.searchFor('label:priority_high');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Work Task Priority');
    });

    test('should search by multi-word phrase with Polish characters', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.searchFor('różowa piłka');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('różowa piłka do grania w bubbleż');
    });

    test('should search specifically in Public notes', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.selectVisibility('public');
        await dashboardPage.searchFor('Simple');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Simple English Note');
        
        // Negative test: Search for a private note title while in Public view
        await dashboardPage.searchFor('Zażółć');
        await dashboardPage.expectEmptySearchResult();
    });

    test('should search specifically in Draft notes', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.selectVisibility('draft');
        await dashboardPage.searchFor('Katakana');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Katakana テスト');
    });

    test('should search specifically in Private notes', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.selectVisibility('private');
        await dashboardPage.searchFor('Zażółć');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible('Zażółć gęślą jaźń');
        
        // Negative test: Search for a public note title while in Private view
        await dashboardPage.searchFor('Simple');
        await dashboardPage.expectEmptySearchResult();
    });

    test('should search specifically in Shared (For Me) notes', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.selectVisibility('shared');
        await dashboardPage.searchFor('Shared');
        
        await dashboardPage.expectSearchResultsCount(1);
        await dashboardPage.expectNoteVisible(sharedNoteTitle);
        
        // Negative test: Search for one of own private notes in shared view
        await dashboardPage.searchFor('Work');
        await dashboardPage.expectEmptySearchResult();
    });

    test('should show empty state for no results', async ({ page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.searchFor('NonExistentTerm123');
        
        await dashboardPage.expectEmptySearchResult();
        await dashboardPage.expectSearchResultsCount(0);
        
        // Test "Clear Filters" button
        await dashboardPage.clickClearFilters();
        await dashboardPage.expectSearchResultsCount(5); // Should see all 5 seeded notes
    });
});