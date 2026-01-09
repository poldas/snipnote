import { expect } from '@playwright/test';

/**
 * MailpitClient provides methods to interact with Mailpit API.
 * This is used to verify emails sent during E2E tests (e.g., registration).
 */
export class MailpitClient {
    private readonly apiUrl: string;

    constructor() {
        this.apiUrl = process.env.MAILPIT_API_URL || 'http://127.0.0.1:8025/api/v1';
    }

    async deleteAllMessages() {
        await fetch(`${this.apiUrl}/messages`, { method: 'DELETE' });
    }

    async getLatestMessageFor(email: string) {
        const response = await fetch(`${this.apiUrl}/messages`);
        const data = await response.json();
        
        // Find message by recipient
        const message = data.messages.find((m: any) => 
            m.To.some((to: any) => to.Address === email)
        );

        if (!message) return null;

        // Fetch full message content
        const detailResponse = await fetch(`${this.apiUrl}/message/${message.ID}`);
        return await detailResponse.json();
    }

    async extractLinkFromEmail(email: string, regex: RegExp): Promise<string> {
        const message = await this.getLatestMessageFor(email);
        if (!message) throw new Error(`No email found for ${email}`);

        const body = message.HTML || message.Text;
        const match = body.match(regex);
        
        if (!match) {
            console.error('DEBUG - Email Body:', body);
            throw new Error(`Could not find link in email body with regex: ${regex}`);
        }
        
        return match[0].replace(/&amp;/g, '&');
    }
}
