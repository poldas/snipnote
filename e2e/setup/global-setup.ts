import { FullConfig } from '@playwright/test';
import { UserFactory } from '../helpers/UserFactory';

async function globalSetup(config: FullConfig) {
  const sharedEmail = 'shared_base@test.test';
  console.log(`\nGlobal Setup: Preparing environment...`);
  
  try {
    // Ensure shared base user exists for UI tests
    await UserFactory.create(sharedEmail);
    console.log(`Global Setup: Shared base user ${sharedEmail} ready.`);
  } catch (error) {
    console.warn('Global Setup: Could not create shared base user. Spec-level creation will fallback.');
  }
}

export default globalSetup;