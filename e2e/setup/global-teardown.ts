import { FullConfig } from '@playwright/test';

async function globalTeardown(config: FullConfig) {
  const sharedEmail = 'shared_base@test.test';
  console.log(`\nGlobal Teardown: Final cleanup...`);
  try {
    await UserFactory.delete(sharedEmail);
  } catch (e) {}
}

export default globalTeardown;