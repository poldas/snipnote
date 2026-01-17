import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Editor Shortcuts', () => {
    
    test('should save note on Ctrl+S and redirect', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const title = `Shortcut Note ${Date.now()}`;
        
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content saved via shortcut');
        
        // Trigger Ctrl+S
        await page.keyboard.press('Control+s');
        
        // Should show success toast
        await expect(page.getByText('Notatka utworzona')).toBeVisible();
        
        // Should redirect to dashboard (or list)
        await expect(page).toHaveURL(/\/notes/);
        
        // Verify existence
        await expect(page.getByText(title)).toBeVisible();
    });

    test('should save changes on Ctrl+S in edit mode', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        // Create note first
        const title = `Edit Shortcut ${Date.now()}`;
        
        // Manual creation flow instead of non-existent createNote helper
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Initial content');
        await editorPage.save();
        
        // Go to edit
        await dashboardPage.clickEditNote(title);
        
        // Change content
        await editorPage.fillDescription('Updated content via shortcut');
        
        // Trigger Ctrl+S (Meta+S for Mac, but Ctrl+S works usually in Playwright)
        // Let's try Meta+S as well just in case of MacOS runner
        if (process.platform === 'darwin') {
            await page.keyboard.press('Meta+s');
        } else {
            await page.keyboard.press('Control+s');
        }
        
        // Should show success toast
        await expect(page.getByText('Zapisano zmiany')).toBeVisible();
        
        // Should NOT redirect (remain on edit page)
        await expect(page).toHaveURL(/\/edit/);
    });

    test('should save visibility changes on Ctrl+S and reload to update UI', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        // Create note first
        const title = `Visibility Shortcut ${Date.now()}`;
        
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Initial content');
        await editorPage.setVisibility('private');
        await editorPage.save();
        
        // Go to edit
        await dashboardPage.clickEditNote(title);
        
        // Change visibility to public and update content
        await editorPage.setVisibility('public');
        await editorPage.fillDescription('Updated content with public visibility');
        
        // Trigger Ctrl+S
        if (process.platform === 'darwin') {
            await page.keyboard.press('Meta+s');
        } else {
            await page.keyboard.press('Control+s');
        }
        
        // Should show success toast
        await expect(page.getByText('Zapisano zmiany')).toBeVisible();

        // Wait for reload (page load event or checking element re-appearance)
        await page.waitForLoadState('domcontentloaded');

        // Should still be on edit page
        await expect(page).toHaveURL(/\/edit/);
        
        // Verify content persisted
        await expect(page.locator('textarea[name="description"]')).toHaveValue('Updated content with public visibility');
        
        // Verify visibility badge updated in the header (outside the form)
        // Assuming the badge text matches "Publiczna" or "Public" depending on locale/logic.
        // Based on templates/notes/edit.html.twig, it uses view.initialVisibility|capitalize.
        await expect(page.getByText('Public', { exact: true })).toBeVisible();
    });

    test('should navigate to new note page when pressing "n" on dashboard', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        
        // Ensure we are on dashboard and focused on body (click somewhere safe if needed, or just reload)
        await page.goto('/notes');
        await dashboardPage.expectPageLoaded();
        
        // Press 'n'
        await page.keyboard.press('n');
        
        // Verify URL
        await expect(page).toHaveURL(/\/notes\/new/);
        
        // Verify title focused (checking integration with previous task)
        await expect(page.locator('input[name="title"]')).toBeFocused();
    });

    test('should focus search input when pressing "/" on dashboard', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await page.goto('/notes');
        await dashboardPage.expectPageLoaded();
        
        // Press '/'
        await page.keyboard.press('/');
        
        // Verify focus on search input
        const searchInput = page.locator('input[name="q"]');
        await expect(searchInput).toBeFocused();
    });

    test('should NOT trigger shortcuts when typing in an input field', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        
        await page.goto('/notes');
        await dashboardPage.expectPageLoaded();
        
        // Focus search input first
        const searchInput = page.locator('input[name="q"]');
        await searchInput.focus();
        
        // Type "n" into the input
        await page.keyboard.type('n');
        
        // Should remain on dashboard, NOT go to /notes/new
        await expect(page).toHaveURL(/\/notes$/);
        
        // Input should contain "n"
        await expect(searchInput).toHaveValue('n');
    });
});
