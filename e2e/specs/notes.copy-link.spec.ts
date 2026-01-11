import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Copy Link Functionality', () => {
    
    test('should copy public link for Public and Private notes, and edit link for Draft notes', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const timestamp = Date.now();
        const noteTitles = {
            public: `Public Note ${timestamp}`,
            private: `Private Note ${timestamp}`,
            draft: `Draft Note ${timestamp}`
        };

        // --- Step 1: Create three notes with different visibilities ---
        
        // 1. Public Note
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(noteTitles.public);
        await editorPage.fillDescription('Public content');
        await editorPage.setVisibility('public');
        await editorPage.save();

        // 2. Private Note
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(noteTitles.private);
        await editorPage.fillDescription('Private content');
        await editorPage.setVisibility('private');
        await editorPage.save();

        // 3. Draft Note
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(noteTitles.draft);
        await editorPage.fillDescription('Draft content');
        await editorPage.setVisibility('draft');
        await editorPage.save();

        await dashboardPage.expectPageLoaded();

        // --- Step 2: Verify Copy Link behavior for each note ---

        const getCopyLink = async (title: string) => {
            const card = await dashboardPage.getNoteCard(title);
            const btn = card.getByTestId('note-copy-link-btn');
            return await btn.getAttribute('data-link');
        };

        // 1. Check Public Note (Should be /n/...)
        const publicCopyLink = await getCopyLink(noteTitles.public);
        expect(publicCopyLink).toMatch(/\/n\/[a-zA-Z0-9_-]+/);

        // 2. Check Private Note (Should also be /n/...)
        const privateCopyLink = await getCopyLink(noteTitles.private);
        expect(privateCopyLink).toMatch(/\/n\/[a-zA-Z0-9_-]+/);

        // 3. Check Draft Note (Should be /notes/.../edit)
        const draftCopyLink = await getCopyLink(noteTitles.draft);
        expect(draftCopyLink).toMatch(/\/notes\/\d+\/edit/);
        expect(draftCopyLink).not.toMatch(/\/n\//);
    });
});