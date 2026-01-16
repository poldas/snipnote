import { test, expect } from '../fixtures/auth.fixture';
import { DashboardPage } from '../page-objects/DashboardPage';
import { NoteEditorPage } from '../page-objects/NoteEditorPage';

test.describe('Public Catalog', () => {
    
    test('should show correct notes based on viewer identity', async ({ authedPage: page, context }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);

        // 0. Verify Empty State first
        const catalogUrl = await page.getByTestId('nav-public-catalog-link').getAttribute('href');
        await page.goto(catalogUrl!);
        await expect(page.getByText('Notatki niedostępne lub nieprawidłowy link')).toBeVisible();
        await expect(page.getByText('Sprawdź, czy adres URL jest poprawny lub zapytaj właściciela o nowy link')).toBeVisible();
        
        await page.goto('/notes'); // Go back to dashboard
        
        // 1. Create Public Note
        const publicTitle = `Public Note ${Date.now()}`;
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(publicTitle);
        await editorPage.fillDescription('Public Content');
        await editorPage.setVisibility('public');
        await editorPage.save();

        // 2. Create Private Note
        const privateTitle = `Private Note ${Date.now()}`;
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(privateTitle);
        await editorPage.fillDescription('Private Content');
        await editorPage.setVisibility('private');
        await editorPage.save();

        // 3. Get Catalog URL from Nav
        const catalogLink = page.getByTestId('nav-public-catalog-link');
        const currentUrl = await catalogLink.getAttribute('href');
        expect(currentUrl).not.toBeNull();

        // 4. Verify as Owner (Should see ONLY public)
        await page.goto(currentUrl!);
        await expect(page.getByText(publicTitle)).toBeVisible();
        await expect(page.getByText(privateTitle)).not.toBeVisible();
        await expect(page.getByText('To jest podgląd Twojego profilu publicznego')).toBeVisible();

        // 5. Verify as Anonymous (Should only see public)
        const anonPage = await context.browser()!.newPage();
        await anonPage.goto(catalogUrl!); // Playwright uses baseURL
        await expect(anonPage.getByText(publicTitle)).toBeVisible();
        await expect(anonPage.getByText(privateTitle)).not.toBeVisible();
        await expect(anonPage.getByText('To jest podgląd Twojego profilu publicznego')).not.toBeVisible();
        await anonPage.close();
    });

    test('should show friendly error for invalid UUID', async ({ page }) => {
        // SQL Error Regression Test
        await page.goto('/u/invalid-uuid-d');
        
        // Should NOT show raw SQL error, but the friendly empty state
        await expect(page.getByText('Notatki niedostępne lub nieprawidłowy link')).toBeVisible();
    });

    test('should support searching in catalog', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const title1 = `Searchable Note SQL ${Date.now()}`;
        const title2 = `Other Note ${Date.now()}`;

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title1);
        await editorPage.fillDescription('Content with SQL word');
        await editorPage.setVisibility('public');
        await editorPage.save();

        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title2);
        await editorPage.fillDescription('Content with nothing special');
        await editorPage.setVisibility('public');
        await editorPage.save();

        const catalogUrl = await page.getByTestId('nav-public-catalog-link').getAttribute('href');
        await page.goto(catalogUrl!);
        
        // Ensure page is ready
        await expect(page.locator('input[name="q"]')).toBeVisible();

        // 1. Search for SQL (Green Path)
        const searchInput = page.locator('input[name="q"]');
        // Type slowly to trigger HTMX debounce
        await searchInput.pressSequentially('SQL', { delay: 200 });
        
        // Wait for HTMX (indicator should appear and disappear, or just wait for text)
        await expect(page.getByText(title1)).toBeVisible();
        await expect(page.getByText(title2)).not.toBeVisible();

        // Verify URL updates (Deep Linking / URL Sync)
        expect(page.url()).toContain('q=SQL');
        
        // 2. Security: XSS Attempt (Red Path)
        await searchInput.fill('<script>alert(1)</script>');
        await searchInput.press('Enter');
        
        // Should NOT display an alert dialog (Playwright auto-dismisses but logs it; we can check if content is escaped)
        // Ideally, the text should be displayed as text, not executed.
        // We verify the input value contains the raw string (meaning it wasn't stripped/executed)
        await expect(page.locator('input[name="q"]')).toHaveValue('<script>alert(1)</script>');
        // And results should likely be empty (unless we have a note with that text)
        await expect(page.getByText('Brak wyników wyszukiwania')).toBeVisible();

        // 3. Security: SQL Injection Attempt (Red Path)
        await searchInput.fill("' OR '1'='1");
        await searchInput.press('Enter');
        // Should NOT show all notes (title2 should remain hidden if filter works properly, or both hidden if no match)
        // If SQLi worked, it might dump all DB or crash (500).
        // Expecting "No results" or just no crash.
        await expect(page.getByText('Brak wyników wyszukiwania')).toBeVisible();
    });

    test('should have correct card structure and actions for owner in catalog', async ({ authedPage: page }) => {
        const dashboardPage = new DashboardPage(page);
        const editorPage = new NoteEditorPage(page);
        
        const title = `Catalog Card Test ${Date.now()}`;
        const excerpt = 'This is a test excerpt for catalog card structure verification.';

        // 1. Create a public note
        await dashboardPage.clickAddNote();
        await editorPage.fillTitle(title);
        await editorPage.fillDescription(excerpt);
        await editorPage.setVisibility('public');
        await editorPage.save();

        // 2. Go to catalog
        const catalogUrl = await page.getByTestId('nav-public-catalog-link').getAttribute('href');
        await page.goto(catalogUrl!);

        // 3. Find the card
        const card = page.getByTestId('note-card').filter({ hasText: title });
        await expect(card).toBeVisible();

        // 4. Verify data arrangement (Date, Title, Excerpt)
        // Note: Logic in template places date at the bottom, title in h3, excerpt in p.
        await expect(card.getByTestId('note-card-title')).toHaveText(title);
        await expect(card.getByTestId('note-card-excerpt')).toContainText(excerpt);
        await expect(card.getByTestId('note-card-date')).toBeVisible();
        // Regex for DD.MM.YYYY
        await expect(card.getByTestId('note-card-date')).toHaveText(/^\d{2}\.\d{2}\.\d{4}$/);

        // 5. Verify Buttons (Should have Edit, Open, Copy; Should NOT have Delete)
        await expect(card.getByTestId('note-edit-btn')).toBeVisible();
        await expect(card.getByTestId('note-open-link')).toBeVisible();
        await expect(card.getByTestId('note-copy-link-btn')).toBeVisible();
        await expect(card.getByTestId('note-delete-btn')).not.toBeVisible();

        // 6. Verify Labels (Should NOT have "Publiczne" badge as it's redundant)
        await expect(card.getByText('Publiczne')).not.toBeVisible();

        // 7. Verify Actions
        // A. Copy Link (should show feedback or just verify attribute)
        const link = await card.getByTestId('note-copy-link-btn').getAttribute('data-link');
        expect(link).toContain('/n/');

        // B. Edit Button (should navigate to editor)
        await card.getByTestId('note-edit-btn').click();
        await expect(page).toHaveURL(/\/notes\/\d+\/edit/);
        await expect(page.locator('input[name="title"]')).toHaveValue(title);

        // C. Open Link (should open in new tab)
        await page.goto(catalogUrl!); // Go back
        const [newPage] = await Promise.all([
            page.context().waitForEvent('page'),
            card.getByTestId('note-open-link').click()
        ]);
        await newPage.waitForLoadState();
        await expect(newPage.getByText(title)).toBeVisible();
        await newPage.close();
    });
});
