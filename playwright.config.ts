import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
const headless = (process.env.HEADLESS ?? 'true') !== 'false';

export default defineConfig({
    testDir: './e2e/specs',
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    // STRICT STABILITY: Disable internal parallelism within files
    fullyParallel: false, 
    forbidOnly: !!process.env.CI,
    retries: 0,
    // Base workers: use 1 for total stability
    workers: 1, 
    globalSetup: require.resolve('./e2e/setup/global-setup'),
    globalTeardown: require.resolve('./e2e/setup/global-teardown'),
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
        trace: 'on-first-retry',
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 20_000,
    },
    projects: [
        {
            name: 'ui',
            testMatch: /.*(visual|navigation|smoke|hover|comprehensive|tagline).*\.spec\.ts/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'functional',
            testIgnore: /.*(visual|navigation|smoke|hover|comprehensive|tagline).*\.spec\.ts/,
            use: { ...devices['Desktop Chrome'] },
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