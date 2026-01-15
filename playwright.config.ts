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
    // CI/CD BEST PRACTICES: Parallel execution based on test isolation requirements
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0, // Retry once in CI for stability
    // Global setup/teardown removed - user creation moved to individual test fixtures
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
        process.env.CI ? ['github'] : null, // GitHub Actions reporter in CI
    ].filter(Boolean),
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
        // ========================================================================================
        // STATELESS PROJECTS: No authentication required - safe for parallel execution
        // ========================================================================================

        {
            name: 'stateless-visual',
            testMatch: '**/auth.visual.spec.ts',
            workers: process.env.CI ? 3 : 2, // Multiple workers for visual regression tests
            use: {
                ...devices['Desktop Chrome'],
                // Isolate browser context for each test
                launchOptions: {
                    args: ['--disable-web-security', '--disable-features=VizDisplayCompositor']
                }
            },
        },
        {
            name: 'stateless-navigation',
            testMatch: '**/auth.navigation.spec.ts',
            workers: process.env.CI ? 2 : 1, // Moderate parallelism for navigation tests
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'stateless-landing',
            testMatch: '**/landing.*.spec.ts',
            workers: process.env.CI ? 4 : 2, // High parallelism for landing page tests (9 tests total)
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'stateless-hover',
            testMatch: '**/ui.hover-effects*/**/*.spec.ts',
            workers: process.env.CI ? 3 : 2, // Parallel hover effect tests (15 tests total)
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'stateless-hover-main',
            testMatch: '**/ui.hover-effects.spec.ts',
            workers: process.env.CI ? 2 : 1, // Main hover effects test (3 tests)
            use: { ...devices['Desktop Chrome'] },
        },

        // ========================================================================================
        // STATEFUL PROJECTS: Authentication required - sequential execution to avoid interference
        // ========================================================================================

        {
            name: 'stateful-auth',
            testMatch: '**/auth.*.spec.ts',
            testIgnore: ['**/auth.visual.spec.ts', '**/auth.navigation.spec.ts'], // Exclude stateless auth tests
            workers: 1, // Sequential execution - auth tests interfere with each other (5 tests)
            use: {
                ...devices['Desktop Chrome'],
                // Fresh browser context per test to avoid session interference
                launchOptions: {
                    args: ['--disable-web-security']
                }
            },
        },
        {
            name: 'stateful-notes',
            testMatch: '**/notes.*.spec.ts',
            workers: process.env.CI ? 2 : 1, // Parallel execution - each test has isolated user/data (23 tests)
            use: {
                ...devices['Desktop Chrome'],
                // Consistent viewport for note editing
                viewport: { width: 1440, height: 900 },
                // Additional isolation for parallel execution
                launchOptions: {
                    args: ['--disable-web-security', '--disable-features=VizDisplayCompositor', '--no-sandbox']
                }
            },
        },
        {
            name: 'stateful-ui-logic',
            testMatch: '**/ui.interaction.spec.ts',
            workers: 1, // Sequential - UI interactions may have side effects (4 tests)
            use: {
                ...devices['Desktop Chrome'],
                // Enable JavaScript source maps for debugging
                launchOptions: {
                    args: ['--disable-web-security', '--enable-source-maps']
                }
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