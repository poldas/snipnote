import { test as base } from '@playwright/test';
import { LoginPage } from '../page-objects/LoginPage';
import { DashboardPage } from '../page-objects/DashboardPage';
import { UserFactory } from '../helpers/UserFactory';
import { TEST_CONFIG } from '../config/test-constants';

type AuthFixtures = {
    authedPage: any; // Page + logged in user context
    userEmail: string;
};

export const test = base.extend<AuthFixtures>({
    userEmail: async ({ }, use) => {
        // Generate unique email
        const email = UserFactory.generateEmail('fixt');
        // Create user in DB
        await UserFactory.create(email, TEST_CONFIG.DEFAULT_PASSWORD);
        
        await use(email);

        // Cleanup
        await UserFactory.delete(email);
    },

    authedPage: async ({ page, userEmail }, use) => {
        const loginPage = new LoginPage(page);
        const dashboardPage = new DashboardPage(page);

        await loginPage.goto();
        await loginPage.login(userEmail, TEST_CONFIG.DEFAULT_PASSWORD);
        await dashboardPage.expectPageLoaded();

        await use(page);
    },
});

export { expect } from '@playwright/test';
