import { test, expect } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';
import { UserFactory } from '../helpers/UserFactory';

test.describe('UI Visual Consistency - Note Form', () => {
    let userEmail: string;
    const userPass = 'K7pL9mW3xR8vT2q';

    test.beforeAll(async () => {
        userEmail = UserFactory.generateEmail('visual-cons');
        await UserFactory.create(userEmail, userPass);
    });

    test.afterAll(async () => {
        await UserFactory.delete(userEmail);
    });

    test.beforeEach(async ({ page }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.login(userEmail, userPass);
        const dashboardPage = new DashboardPage(page);
        await dashboardPage.clickAddNote();
        const editorPage = new NoteEditorPage(page);
        await editorPage.waitForReady();
    });

    test('Add buttons should have correct brand gradient and hover effects', async ({ page }) => {
        // Tag Add Button
        const tagAddBtn = page.getByTestId('tag-add-btn');
        
        // Verify background gradient (intended branding)
        // linear-gradient(135deg, rgb(102, 126, 234) 0%, rgb(118, 75, 162) 100%)
        const background = await tagAddBtn.evaluate(el => window.getComputedStyle(el).backgroundImage);
        expect(background).toContain('linear-gradient(135deg, rgb(102, 126, 234)');
        expect(background).toContain('rgb(118, 75, 162)');

        // Hover effect check
        await tagAddBtn.hover();
        await page.waitForTimeout(350); // Wait for transition
        const hoverShadow = await tagAddBtn.evaluate(el => window.getComputedStyle(el).boxShadow);
        // Expect a significant shadow
        expect(hoverShadow).not.toBe('none');
        expect(hoverShadow.length).toBeGreaterThan(20);
        
        // Save button (Dashboard context)
        const saveBtn = page.locator('button[data-submit-btn]');
        const saveBackground = await saveBtn.evaluate(el => window.getComputedStyle(el).backgroundImage);
        expect(saveBackground).toBe(background); // Must be exactly the same
    });

    test('Inputs should have correct hover and focus effects', async ({ page }) => {
        const titleInput = page.getByTestId('note-title-input');
        
        // Default state
        const defaultShadow = await titleInput.evaluate(el => window.getComputedStyle(el).boxShadow);
        
        // Hover state
        await titleInput.hover();
        await page.waitForTimeout(350); // Wait for transition
        const hoverShadow = await titleInput.evaluate(el => window.getComputedStyle(el).boxShadow);
        // shadow-xl should be present
        expect(hoverShadow).not.toBe('none');

        // Focus state
        await titleInput.focus();
        await page.waitForTimeout(350); // wait for transition
        const focusShadow = await titleInput.evaluate(el => window.getComputedStyle(el).boxShadow);
        
        // Should have brand glow color (rgba(102, 126, 234, 0.3))
        expect(focusShadow).toContain('rgba(102, 126, 234, 0.3)');
        
        // Transform translateY(-2px)
        const transform = await titleInput.evaluate(el => window.getComputedStyle(el).transform);
        expect(transform).toContain('matrix'); // matrix(1, 0, 0, 1, 0, -2) roughly
    });

    test('Collaborator Add button should be consistent in Edit mode', async ({ page }) => {
        const editorPage = new NoteEditorPage(page);
        const dashboardPage = new DashboardPage(page);
        
        // Create note to access Edit mode
        const title = `Visual Collab ${Date.now()}`;
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content');
        await editorPage.save();
        await dashboardPage.clickEditNote(title);
        
        const collabAddBtn = page.getByTestId('collaborator-add-btn');
        const background = await collabAddBtn.evaluate(el => window.getComputedStyle(el).backgroundImage);
        
        // Match brand gradient
        expect(background).toContain('linear-gradient(135deg, rgb(102, 126, 234)');
        expect(background).toContain('rgb(118, 75, 162)');
    });
});
