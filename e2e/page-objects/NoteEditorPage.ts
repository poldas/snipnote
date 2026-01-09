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
        await this.page.getByTestId('tag-input').fill(label);
        await this.page.getByTestId('tag-add-btn').click();
        // Wait for the chip to appear in the container
        await expect(this.page.locator('[data-testid="tag-chip"]').filter({ hasText: label })).toBeVisible();
    }

    async removeLabel(label: string) {
        // Find the remove button that is a child of a chip containing the label text
        const chip = this.page.locator('[data-testid="tag-chip"]').filter({ hasText: label }).first();
        await chip.getByTestId('tag-remove-btn').click({ force: true });
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
    }

    async save() {
        const submitBtn = this.page.locator('button[data-submit-btn]');
        await expect(submitBtn).toBeVisible();
        
        await submitBtn.click({ force: true });
        
        // Wait for the toast and then for redirect
        await this.toast.expectSuccess(/Zapisano|Notatka utworzona/i);
        
        // Wait for the redirect to dashboard. 
        await expect(this.page).toHaveURL(/\/notes(?:\?|$)/, { timeout: 30000 });
    }

    async expectDescriptionContent(text: string) {
        await expect(this.page.getByTestId('note-description-textarea')).toHaveValue(text);
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
