import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('UI Interactions & JS Logic', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('js-logic');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    // Autofocus test removed as the controller is not currently used in the tested views

    test('Markdown Toolbar should insert formatting correctly', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();

        const textarea = page.getByTestId('note-description-textarea');

        // Test Bold (default text insertion)
        await textarea.clear();
        await page.locator('button[data-md-action="bold"]').click();
        await expect(textarea).toHaveValue('**pogrubiony tekst**');

        // Test List
        await textarea.clear();
        await page.locator('button[data-md-action="list"]').click();
        await expect(textarea).toHaveValue('\n- Element listy\n');
    });

    test('Tag Input should handle addition, deduplication and removal', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();

        // Add "javascript"
        await editorPage.addLabel('javascript');

        // Add "Python" (mixed case)
        await editorPage.addLabel('Python');

        // Try add "JAVASCRIPT" again (should be deduplicated by controller)
        await page.getByTestId('tag-input').fill('JAVASCRIPT');
        await page.getByTestId('tag-add-btn').click();

        // Verify chips count (should be 2: javascript, Python)
        await expect(page.getByTestId('tag-chip')).toHaveCount(2);
        await expect(page.getByTestId('tag-chip').first()).toContainText('javascript');

        // Verify hidden input value (JSON)
        const hiddenInput = page.locator('input[data-labels-input]');
        const value = await hiddenInput.inputValue();
        expect(JSON.parse(value)).toEqual(['javascript', 'Python']);

        // Remove tag
        await editorPage.removeLabel('javascript');
        await expect(page.getByTestId('tag-chip')).toHaveCount(1);
    });

    test('Public Todo Controller should persist local tasks in LocalStorage', async ({ page, context }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // Prepare a public note with 'todo' tag
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle('Todo Local Test');
        await editorPage.fillDescription('Opis notatki');
        await editorPage.addLabel('todo');
        await editorPage.setVisibility('public');
        await editorPage.save();

        // Get public link
        await dashboardPage.clickEditNote('Todo Local Test');
        const publicUrl = await editorPage.getPublicUrl();
        await dashboardPage.clickLogout();

        // Open public note as anonymous user
        await page.goto(publicUrl);

        // Wait for the TODO input to appear (controller initialization)
        const addInput = page.locator('[data-public-todo-target="input"]');
        await addInput.waitFor({ state: 'visible', timeout: 10000 });

        await addInput.fill('Local Task Persist');
        await addInput.press('Enter');

        await expect(page.getByText('Local Task Persist')).toBeVisible();

        // Reload page to verify LocalStorage persistence
        await page.reload();
        await expect(page.getByText('Local Task Persist')).toBeVisible();
    });

    test('Client-side validation should prevent submit without request', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();

        // Try to submit empty form
        const submitBtn = page.locator('button[data-submit-btn]');

        // Monitor network
        let requestSent = false;
        page.on('request', request => {
            if (request.url().includes('/api/notes') && request.method() === 'POST') {
                requestSent = true;
            }
        });

        // Click and wait for JS validation to reflect in DOM
        await submitBtn.click();

        // Use a more flexible check for validation messages
        await expect(page.locator('body')).toContainText(/Tytuł jest wymagany/i);
        await expect(page.locator('body')).toContainText(/Opis jest wymagany/i);

        expect(requestSent).toBe(false);
    });
});
