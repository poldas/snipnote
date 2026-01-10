import { FullConfig } from '@playwright/test';

async function globalSetup(config: FullConfig) {
  console.log(`\nGlobal Setup: Environment ready (user creation moved to fixtures)`);
}

export default globalSetup;