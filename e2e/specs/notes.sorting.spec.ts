import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Dashboard Sorting Logic', () => {
    
    test('should sort notes by modification date (updatedAt DESC)', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const titles = {
            first: `Note A (Created First) ${Date.now()}`,
            second: `Note B (Created Second) ${Date.now()}`
        };

        // 1. Create first note
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(titles.first);
        await editorPage.fillDescription('Content A');
        await editorPage.save();

        // 2. Create second note (should be at the top now)
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle(titles.second);
        await editorPage.fillDescription('Content B');
        await editorPage.save();

        // Verify initially second note is first in the list
        let firstCardTitle = await page.locator('div.grid > div.group h3').first().innerText();
        expect(firstCardTitle).toBe(titles.second);

        // 3. Edit the FIRST note (to update its modification date)
        await dashboardPage.clickEditNote(titles.first);
        await editorPage.waitForReady();
        await editorPage.fillTitle(titles.first + ' (Updated)');
        await editorPage.save();

        // 4. Verify that the updated first note is now at the TOP
        await dashboardPage.expectPageLoaded();
        firstCardTitle = await page.locator('div.grid > div.group h3').first().innerText();
        expect(firstCardTitle).toBe(titles.first + ' (Updated)');
    });
});
