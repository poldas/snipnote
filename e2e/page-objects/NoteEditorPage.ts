import { expect, Page } from '@playwright/test';
import { ToastComponent } from './components/ToastComponent';

/**
 * Note Editor page (/notes/new or /notes/{id}/edit).
 */
export class NoteEditorPage {
    readonly toast: ToastComponent;

    constructor(private readonly page: Page) {
        this.toast = new ToastComponent(page);
    }

    async waitForReady() {
        // Wait for any previous toasts to disappear
        await this.toast.expectHidden();

        // Wait for the form element to be visible and initialized by Stimulus
        // The attribute is set specifically on the element with data-controller="note-form"
        const form = this.page.locator('[data-controller="note-form"]').first();
        await form.waitFor({ state: 'visible', timeout: 15000 });
        // Use the attribute set by note_form_controller.js
        await expect(form).toHaveAttribute('data-note-form-ready', 'true', { timeout: 10000 });
    }

    async fillTitle(title: string) {
        await this.waitForReady();
        await this.page.getByTestId('note-title-input').fill(title);
    }

    async fillDescription(description: string) {
        await this.waitForReady();
        await this.page.getByTestId('note-description-textarea').fill(description);
    }

    async addLabel(label: string) {
        const input = this.page.getByTestId('tag-input');
        const btn = this.page.getByTestId('tag-add-btn');

        await expect(input).toBeVisible();
        await input.fill(label);
        // Trigger blur to add the tag (as per controller logic)
        // We focus the title input which is always present
        await this.page.getByTestId('note-title-input').focus();
        
        // Wait for the chip to appear in the container
        await expect(this.page.locator('[data-testid="tag-chip"]').filter({ hasText: label })).toBeVisible();
    }

    async removeLabel(label: string) {
        // Find the remove button that is a child of a chip containing the label text
        const chip = this.page.locator('[data-testid="tag-chip"]').filter({ hasText: label }).first();
        await expect(chip).toBeVisible();
        await chip.getByTestId('tag-remove-btn').click();
        // Wait for the chip to disappear
        await expect(chip).not.toBeVisible();
    }

    async setVisibility(visibility: 'private' | 'public' | 'draft') {
        const radio = this.page.locator(`input[value="${visibility}"]`);
        // Force the check via JS property to be 100% sure it's set
        await radio.evaluate((node: HTMLInputElement) => {
            node.checked = true;
            node.dispatchEvent(new Event('change', { bubbles: true }));
        });
        
        // Also click the label just to trigger any visual Stimulus effects
        const label = this.page.locator(`label[data-visibility-label="${visibility}"]`);
        await label.click({ force: true });

        await expect(radio).toBeChecked();
    }

    async addCollaborator(email: string) {
        await this.page.getByTestId('collaborator-email-input').fill(email);
        await this.page.getByTestId('collaborator-add-btn').click();
        
        // Wait for the new collaborator to appear in the list
        await expect(this.page.locator('[data-collaborator-row]').filter({ hasText: email })).toBeVisible({ timeout: 10000 });
    }

    async save() {
        const submitBtn = this.page.locator('button[data-submit-btn]');
        
        // Ensure not currently saving (spinner should be hidden/transparent)
        const spinner = this.page.locator('[data-saving-spinner]');
        if (await spinner.count() > 0) {
             await expect(spinner).toHaveClass(/opacity-0/);
        }

        await expect(submitBtn).toBeVisible();
        await expect(submitBtn).toBeEnabled();
        
        // Ensure button is in view
        await submitBtn.scrollIntoViewIfNeeded();

        // Small delay to ensure Stimulus has processed all events
        await this.page.waitForTimeout(300);

        // Setup response listener
        const responsePromise = this.page.waitForResponse(response => 
            response.url().includes('/api/notes') && 
            response.request().method() !== 'OPTIONS',
            { timeout: 30000 }
        );

        // Attempt click
        await submitBtn.click({ force: true });

        const response = await responsePromise;

        // 1. Check API Response first
        if (!response.ok()) {
            const errorText = await response.text();
            throw new Error(`Failed to save note. Status: ${response.status()}. Response: ${errorText}`);
        }

        // 2. If API was OK, then wait for navigation or toast
        // We use a race here: either we redirect OR we see a success toast (for edit mode stay)
        await Promise.race([
            this.page.waitForURL(/\/notes(?:\?|$)/, { timeout: 20000 }), // Redirect to list
            this.page.waitForURL(/\/edit/, { timeout: 20000 }),          // Stay on edit page (if logic changes)
            this.toast.expectSuccess(/Zapisano|Notatka utworzona/i)      // Toast appearance
        ]);
    }

    async expectDescriptionContent(text: string) {
        const textarea = this.page.getByTestId('note-description-textarea');
        // If dealing with large content (e.g. 90k chars), increase timeout for comparison
        await expect(textarea).toHaveValue(text, { timeout: 15000 });
    }

    async expectTitle(title: string) {
        await expect(this.page.getByTestId('note-title-input')).toHaveValue(title);
    }

    async getPublicUrl(): Promise<string> {
        // Find the link in the Public Link Section
        const linkEl = this.page.locator('[data-public-link]');
        const url = await linkEl.getAttribute('href');
        return url || '';
    }
}
