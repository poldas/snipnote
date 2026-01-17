import { test, expect } from '@playwright/test';
import { UserFactory } from '../helpers/UserFactory';
import { LoginPage } from '../page-objects/LoginPage';

test.describe('Public Note Error Masking', () => {
    
    test('should show 404 status and masked message for non-existent note', async ({ page }) => {
        const response = await page.goto('/n/00000000-0000-0000-0000-000000000000');
        
        expect(response?.status()).toBe(404);
        await expect(page.locator('h2')).toContainText(/Notatka niedostępna lub nieprawidłowy link/i);
        await expect(page.locator('body')).toContainText(/Sprawdź, czy adres URL jest poprawny/i);
    });

    test('should show 403 status and SAME masked message for unauthorized authenticated user', async ({ browser }) => {
        const userA = UserFactory.generateEmail('userA');
        const userB = UserFactory.generateEmail('userB');
        const pass = 'Password123!';
        
        await UserFactory.create(userA, pass);
        await UserFactory.create(userB, pass);

        const contextA = await browser.newContext();
        const pageA = await contextA.newPage();
        const loginPageA = new LoginPage(pageA);
        
        // 1. User A creates a PRIVATE note
        await loginPageA.goto();
        await loginPageA.login(userA, pass);
        await pageA.goto('/notes/new');
        await pageA.getByTestId('note-title-input').fill('Secret Note');
        await pageA.getByTestId('note-description-textarea').fill('Secret Content');
        await pageA.locator('label[data-visibility-label="private"]').click();
        await pageA.locator('button[data-submit-btn]').click();
        
        // Wait for redirect to dashboard
        await expect(pageA).toHaveURL(/\/notes(?:\?|$)/);
        
        // Go to edit page
        await pageA.locator('div.group', { has: pageA.getByRole('heading', { name: 'Secret Note' }) }).getByTestId('note-edit-btn').click();
        await expect(pageA).toHaveURL(/\/notes\/\d+\/edit/);
        
        const publicLinkEl = pageA.locator('[data-public-link]');
        const publicUrl = await publicLinkEl.getAttribute('href') || '';
        
        // 2. User B tries to access User A's private note
        const contextB = await browser.newContext();
        const pageB = await contextB.newPage();
        const loginPageB = new LoginPage(pageB);
        
        await loginPageB.goto();
        await loginPageB.login(userB, pass);
        
        const response = await pageB.goto(publicUrl);
        
        // SECURITY CHECK: 
        // Status code must be 403 (Forbidden)
        expect(response?.status()).toBe(403);
        // But UI message must be identical to 404 (Masking)
        await expect(pageB.locator('h2')).toContainText(/Notatka niedostępna lub nieprawidłowy link/i);
        
        await UserFactory.delete(userA);
        await UserFactory.delete(userB);
    });
});
