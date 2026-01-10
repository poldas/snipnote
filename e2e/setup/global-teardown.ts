import { FullConfig } from '@playwright/test';

async function globalTeardown(config: FullConfig) {
  console.log(`\nGlobal Teardown: Cleanup completed (no shared resources to clean)`);
}

export default globalTeardown;