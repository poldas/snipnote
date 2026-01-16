import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Note Form Copy', () => {

    test('should display correct visibility descriptions and link copy', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        await dashboardPage.clickAddNote();

        // 1. Private (Default)
        await expect(page.getByText('Notatka widoczna dla Ciebie lub udostępnionych osób')).toBeVisible();

        // 2. Change to Public
        await editorPage.setVisibility('public');
        await expect(page.getByText('Notatka będzie widoczna publicznie pod unikalnym linkiem')).toBeVisible();

        // Save to see Public Link Section
        await editorPage.fillTitle('Public Copy Test');
        await editorPage.fillDescription('Content');
        await editorPage.save();

        // Edit to verify Link Section Copy (Initially Private)
        await dashboardPage.clickEditNote('Public Copy Test');
        await expect(page.getByText('Każdy kto ma ten link, może zobaczyć notatkę')).toBeVisible();
        await expect(page.getByText('Dostęp publiczny')).toBeVisible();

        // 3. Change to Private in Edit Mode
        await editorPage.setVisibility('private');
        await expect(page.getByText('Notatka widoczna dla Ciebie lub udostępnionych osób')).toBeVisible();

        // 4. Change to Draft
        await editorPage.setVisibility('draft');
        await expect(page.getByText('Tylko Ty widzisz tę notatkę (szkic)')).toBeVisible();
    });

    test('should verify tag input and labels copy', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);

        await dashboardPage.clickAddNote();

        await expect(page.getByText('Etykiety', { exact: true })).toBeVisible();
        await expect(page.getByText('Oddziel etykiety naciskając Enter lub przecinek')).toBeVisible();
        await expect(page.getByText('Użyj etykiet recipe lub todo, aby zmienić wygląd notatki')).toBeVisible();
    });

    test('should verify danger zone copy on edit', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        const title = `Copy Test ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription('Content');
        await editorPage.save();

        await dashboardPage.clickEditNote(title);

        // Danger Zone Header
        await expect(page.getByText('Strefa zagrożenia')).toBeVisible();
        // Warning Text
        await expect(page.getByText('Operacje nieodwracalne. Używaj ostrożnie.')).toBeVisible();
        // Delete Button
        await expect(page.getByText('Usuń notatkę')).toBeVisible();
    });
});