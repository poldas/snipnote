import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
const headless = (process.env.HEADLESS ?? 'true') !== 'false';

export default defineConfig({
    testDir: './e2e/specs',
    timeout: 30_000,
    expect: {
        timeout: 5_000,
    },
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    outputDir: 'e2e/artifacts/test-results',
    snapshotDir: path.join(__dirname, 'e2e', 'artifacts', 'snapshots'),
    use: {
        baseURL,
        headless,
        testIdAttribute: 'data-testid',
        viewport: { width: 1280, height: 720 },
        trace: process.env.CI ? 'on-first-retry' : 'retain-on-failure',
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
            },
        },
    ],
    webServer: process.env.E2E_WEB_SERVER_CMD
        ? {
            command: process.env.E2E_WEB_SERVER_CMD,
            url: baseURL,
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
        }
        : undefined,
});

