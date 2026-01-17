import { test, expect } from '@playwright/test';
import { UserFactory } from '../helpers/UserFactory';
import { LoginPage } from '../page-objects/LoginPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { DashboardPage } from '../page-objects/DashboardPage';

test.describe('Security - XSS Protection', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('security');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test('should sanitize XSS payloads in note description and not execute them', async ({ page }) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // Track dialogs (alerts) - if any alert pops up, the test should fail
        let dialogAppeared = false;
        page.on('dialog', dialog => {
            dialogAppeared = true;
            dialog.dismiss();
        });

        // Arrange: Create note with malicious payloads
        const xssPayload = `
# XSS Test
This is a safe line.
<script>alert('XSS Script')</script>
<img src="x" onerror="alert('XSS Img')">
[Malicious Link](javascript:alert('XSS Link'))
<iframe src="javascript:alert('XSS Iframe')"></iframe>
        `;

        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        await dashboardPage.expectPageLoaded();
        
        await dashboardPage.clickAddNote();
        await editorPage.waitForReady();
        await editorPage.fillTitle('XSS Security Test');
        await editorPage.fillDescription(xssPayload);
        await editorPage.save();

        // Act: Open the note public view (where sanitization is critical)
        // This opens in a NEW TAB according to the dashboard template
        const [publicPage] = await Promise.all([
            page.context().waitForEvent('page'),
            dashboardPage.clickOpenNote('XSS Security Test')
        ]);

        await publicPage.waitForLoadState('networkidle');
        await expect(publicPage).toHaveURL(/\/n\/[a-z0-9-]+/);

        // Assert: 
        // 1. No dialog should have appeared
        expect(dialogAppeared).toBe(false);

        // 2. Dangerous elements should not exist in the rendered content area
        const contentArea = publicPage.getByTestId('public-note-content');
        
        // We check if dangerous patterns are NOT present as raw HTML tags/attributes
        const maliciousScripts = await contentArea.locator('script').count();
        expect(maliciousScripts).toBe(0);

        const imagesWithOnerror = await contentArea.locator('img[onerror]').count();
        expect(imagesWithOnerror).toBe(0);

        const linksWithJavascript = await contentArea.locator('a[href^="javascript:"]').count();
        expect(linksWithJavascript).toBe(0);

        const iframesWithJavascript = await contentArea.locator('iframe[src^="javascript:"]').count();
        expect(iframesWithJavascript).toBe(0);

        // 3. Verify that safe content IS present
        await expect(publicPage.getByRole('heading', { name: 'XSS Test' })).toBeVisible();
        await expect(publicPage.getByText('This is a safe line.')).toBeVisible();
    });
});
