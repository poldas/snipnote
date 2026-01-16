import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Editor Accessibility', () => {

    test('should auto-focus title field on new note page', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await dashboardPage.clickAddNote();
        
        // Wait for potential animation/JS
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(100); 

        // Check focus
        await expect(page.locator('input[name="title"]')).toBeFocused();
    });

    test('should follow logical tab order in create mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await dashboardPage.clickAddNote();
        await page.waitForLoadState('domcontentloaded');

        // 1. Start at Title
        await expect(page.locator('input[name="title"]')).toBeFocused();

        // 2. Tab -> Description
        await page.keyboard.press('Tab');
        await expect(page.locator('textarea[name="description"]')).toBeFocused();

        // 3. Tab -> Tags
        await page.keyboard.press('Tab');
        await expect(page.locator('input[data-tag-input]')).toBeFocused();

        // 4. Tab -> Tags Add Button (Not in main list but naturally next) OR next specialized area
        // Note: The plan says "Tags" then "Collaborators".
        // Let's check where the tab goes after Tag Input. 
        // Based on DOM, tag add button is next. 
        // But we assigned tabindex="3" to tag input.
        // We assigned tabindex="4" to collaborator input.
        // Elements with tabindex > 0 come before elements with tabindex=0.
        // So hitting Tab from tabindex=3 should go to tabindex=4.
        
        await page.keyboard.press('Tab');
        // It might skip the "Add" button if it doesn't have a positive tabindex lower than 4 but higher than 3.
        // The Add button has no tabindex assigned, so it defaults to 0. 
        // So it should be skipped in the positive tabindex sequence and visited later.
        
        // Check if we jumped to Collaborator Email (tabindex=4)
        // Wait, Collaborator panel is NOT in Create mode usually?
        // Let's check templates. Collaborators panel is in edit.html.twig but NOT in new.html.twig!
        // So in Create mode, tabindex=4 doesn't exist.
        
        // Next available positive tabindex is Danger Zone (tabindex=5).
        // Danger Zone is also ONLY in edit.html.twig (for owner).
        
        // Next is Logo (tabindex=6).
        // So in Create mode: Title(1) -> Desc(2) -> Tags(3) -> Logo(6) -> ...
        
        // Let's verify this flow for Create Mode.
        
        // However, I can't easily test "Logo" focus if it's not a standard form element, but links are focusable.
        // We assigned tabindex="6" to the logo link.
        
        // Let's check what is focused.
        // await expect(page.getByTestId('logo-home-link')).toBeFocused(); 
        
        // NOTE: Testing exact tab order with missing intermediate indexes (4, 5) might be tricky depending on browser behavior,
        // but generally it jumps to next highest positive tabindex.
        
         await expect(page.getByTestId('logo-home-link')).toBeFocused();
    });

    test('should follow logical tab order in edit mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // Create a note first
        const title = `Access Note ${Date.now()}`;
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content');
        await editorPage.save();

        // Go to Edit
        await dashboardPage.clickEditNote(title);
        
        // Wait for load
        await page.waitForLoadState('domcontentloaded');

        // Focus title to start (auto-focus works in edit too?)
        // Controller connects in edit mode too.
        await expect(page.locator('input[name="title"]')).toBeFocused();

        // 1. Title -> 2. Description
        await page.keyboard.press('Tab');
        await expect(page.locator('textarea[name="description"]')).toBeFocused();

        // 2. Description -> 3. Tags
        await page.keyboard.press('Tab');
        await expect(page.locator('input[data-tag-input]')).toBeFocused();

        // 3. Tags -> 4. Collaborator Email (Exists in Edit)
        await page.keyboard.press('Tab');
        await expect(page.locator('input[name="email"]')).toBeFocused();

        // 4. Collaborator -> 5. Danger Zone (Delete) (Exists for Owner)
        await page.keyboard.press('Tab');
        // The delete button
        await expect(page.locator('button[data-open-delete-modal]')).toBeFocused();

        // 5. Danger -> 6. Logo
        await page.keyboard.press('Tab');
        await expect(page.getByTestId('logo-home-link')).toBeFocused();
        
        // 6. Logo -> 7. Dashboard Link
        await page.keyboard.press('Tab');
        await expect(page.getByRole('link', { name: 'Dashboard' })).toBeFocused();
    });
});
