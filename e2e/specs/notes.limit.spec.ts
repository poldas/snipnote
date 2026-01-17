import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('Note Size Limits', () => {
    let userEmail: string;
    const userPass = 'StrongPass123!';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('limit-test');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test('Should create and display a large note (near 100k chars)', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // Generate a large content string (approx 90k chars)
        const contentSize = 90000;
        const largeContent = 'A'.repeat(contentSize); 

        try {
            await loginPage.goto();
            await loginPage.login(userEmail, userPass);
        } catch (err) {
            console.error('Login failed in limit test. Capturing state.');
            await page.screenshot({ path: `e2e/artifacts/test-results/login-fail-${Date.now()}.png` });
            throw err;
        }

        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();

        await editorPage.fillTitle('Large Note Test');
        
        // Use evaluate to fill textarea quickly as .fill() can be slow for huge texts
        await page.getByTestId('note-description-textarea').evaluate((el: HTMLTextAreaElement, content) => {
            el.value = content;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }, largeContent);

        await editorPage.setVisibility('public');
        await editorPage.save();

        // We are now on the dashboard. Go back to edit mode to check content and get link.
        await dashboardPage.clickEditNote('Large Note Test');
        await editorPage.waitForReady();

        // Verify content in editor (manual fetch to avoid timeout/diff issues with huge strings)
        const editorContent = await page.getByTestId('note-description-textarea').inputValue();
        expect(editorContent.length).toBe(largeContent.length);
        expect(editorContent).toBe(largeContent);

        // Verify public view
        const publicUrl = await editorPage.getPublicUrl();
        await page.goto(publicUrl);

        // Verify content is rendered
        const publicContent = await page.getByTestId('public-note-content').innerText();
        expect(publicContent.length).toBeGreaterThanOrEqual(largeContent.length);
        expect(publicContent).toContain(largeContent.substring(0, 1000));
    });
});